<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\CrmTask;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseRecord;
use App\Models\SalesQuotation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FinanceCalculatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImportOldCrmDataCommand extends Command
{
    protected $signature = 'crm:import-old-data {--database=datamatics_crm_old_import}';

    protected $description = 'Import useful records from the old RISE CRM database into Datamatics Eswatini.';

    public function handle(FinanceCalculatorService $calculator): int
    {
        $database = (string) $this->option('database');
        $connection = config('database.connections.mysql');
        $connection['database'] = $database;
        $connection['strict'] = false;
        config(['database.connections.old_crm_import' => $connection]);
        DB::purge('old_crm_import');
        $old = DB::connection('old_crm_import');

        if (! $this->hasOldTable($old, $database, 'rise_items')) {
            $this->error("Old CRM database [{$database}] is not available or has no RISE tables.");

            return self::FAILURE;
        }

        $counts = [
            'catalog_items' => $this->importCatalogItems($old),
            'invoices' => $this->importInvoices($old, $calculator),
            'expenses' => $this->importExpenses($old),
            'tasks' => $this->importTasks($old),
            'attendance' => $this->importAttendance($old),
        ];

        foreach ($counts as $label => $count) {
            $this->line(str_replace('_', ' ', $label).": {$count}");
        }

        return self::SUCCESS;
    }

    private function importCatalogItems($old): int
    {
        $count = 0;

        $old->table('rise_items')
            ->where('deleted', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($item) use (&$count): void {
                $name = $this->clean($item->title) ?: 'Imported Item '.$item->id;
                CatalogItem::query()->updateOrCreate(
                    ['name' => $name, 'unit_price' => round((float) $item->rate, 2)],
                    [
                        'type' => CatalogItem::TYPE_SERVICE,
                        'description' => $this->clean($item->description),
                        'taxable' => (bool) $item->taxable,
                        'is_active' => true,
                    ],
                );
                $count++;
            });

        return $count;
    }

    private function importInvoices($old, FinanceCalculatorService $calculator): int
    {
        $count = 0;

        $old->table('rise_invoices')
            ->where('deleted', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($oldInvoice) use (&$count, $old, $calculator): void {
                $client = Client::query()->where('client_code', 'OLD-CLT-'.str_pad((string) $oldInvoice->client_id, 4, '0', STR_PAD_LEFT))->first();
                if (! $client) {
                    return;
                }

                $number = 'OLD-INV-'.str_pad((string) $oldInvoice->id, 4, '0', STR_PAD_LEFT);
                $status = match ((string) $oldInvoice->status) {
                    'draft' => Invoice::STATUS_DRAFT,
                    'cancelled', 'credited' => Invoice::STATUS_CANCELLED,
                    default => Invoice::STATUS_ISSUED,
                };

                $invoice = Invoice::query()->updateOrCreate(
                    ['invoice_number' => $number],
                    [
                        'client_id' => $client->id,
                        'status' => $status,
                        'issue_date' => $this->date($oldInvoice->bill_date),
                        'due_date' => $this->date($oldInvoice->due_date, now()->addDays(30)->toDateString()),
                        'notes' => $this->clean($oldInvoice->note)."\nImported from old CRM invoice ID {$oldInvoice->id}.",
                        'issued_at' => $status === Invoice::STATUS_DRAFT ? null : now(),
                    ],
                );

                $items = $old->table('rise_invoice_items')
                    ->where('invoice_id', $oldInvoice->id)
                    ->where('deleted', 0)
                    ->orderBy('sort')
                    ->get()
                    ->map(fn ($item): array => [
                        'description' => trim(($this->clean($item->title) ?: 'Item').' '.($this->clean($item->description) ?: '')),
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->rate,
                        'discount_amount' => 0,
                        'taxable' => (bool) $item->taxable,
                    ])
                    ->all();

                if ($items) {
                    $calculator->syncInvoiceItems($invoice, $items);
                }

                $old->table('rise_invoice_payments')
                    ->where('invoice_id', $oldInvoice->id)
                    ->where('deleted', 0)
                    ->orderBy('id')
                    ->get()
                    ->each(function ($oldPayment) use ($invoice, $calculator): void {
                        $paymentNumber = 'OLD-REC-'.str_pad((string) $oldPayment->id, 4, '0', STR_PAD_LEFT);
                        if (Payment::query()->where('payment_number', $paymentNumber)->exists()) {
                            return;
                        }

                        $calculator->recordPayment($invoice, [
                            'recorded_by' => null,
                            'payment_number' => $paymentNumber,
                            'payment_date' => $this->date($oldPayment->payment_date),
                            'amount' => (float) $oldPayment->amount,
                            'method' => 'Other',
                            'reference' => $this->clean($oldPayment->transaction_id),
                            'notes' => $this->clean($oldPayment->note).' Imported from old CRM.',
                        ]);
                    });

                $count++;
            });

        return $count;
    }

    private function importExpenses($old): int
    {
        $count = 0;

        $old->table('rise_expenses')
            ->where('deleted', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($oldExpense) use (&$count): void {
                $payee = $this->clean($oldExpense->title) ?: 'Imported Expense '.$oldExpense->id;
                $supplier = Supplier::query()->firstOrCreate(
                    ['supplier_code' => 'OLD-SUP-'.str_pad((string) $oldExpense->id, 4, '0', STR_PAD_LEFT)],
                    ['name' => $payee, 'is_active' => true, 'notes' => 'Created from old CRM expense import.'],
                );
                $amount = round((float) $oldExpense->amount, 2);

                Expense::query()->updateOrCreate(
                    ['expense_number' => 'OLD-EXP-'.str_pad((string) $oldExpense->id, 4, '0', STR_PAD_LEFT)],
                    [
                        'supplier_id' => $supplier->id,
                        'category' => 'Other',
                        'payee' => $payee,
                        'expense_date' => $this->date($oldExpense->expense_date),
                        'amount' => $amount,
                        'vat_amount' => 0,
                        'total_amount' => $amount,
                        'status' => 'Recorded',
                        'notes' => $this->clean($oldExpense->description).' Imported from old CRM expense ID '.$oldExpense->id.'.',
                    ],
                );
                $count++;
            });

        return $count;
    }

    private function importTasks($old): int
    {
        $count = 0;
        $usersByOldId = $this->staffUsersByOldId($old);

        $old->table('rise_tasks')
            ->where('deleted', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($oldTask) use (&$count, $usersByOldId): void {
                $assignee = $usersByOldId[(int) $oldTask->assigned_to] ?? null;
                $creator = $usersByOldId[(int) $oldTask->created_by] ?? null;
                $status = match ((string) $oldTask->status) {
                    'in_progress' => CrmTask::STATUS_IN_PROGRESS,
                    'done' => CrmTask::STATUS_DONE,
                    default => CrmTask::STATUS_TO_DO,
                };

                CrmTask::query()->updateOrCreate(
                    ['task_number' => 'OLD-TSK-'.str_pad((string) $oldTask->id, 4, '0', STR_PAD_LEFT)],
                    [
                        'department_id' => $assignee?->department_id,
                        'assigned_to' => $assignee?->id,
                        'created_by' => $creator?->id,
                        'title' => $this->clean($oldTask->title) ?: 'Imported Task '.$oldTask->id,
                        'description' => trim(($this->clean($oldTask->description) ?: '')."\nImported from old CRM task ID {$oldTask->id}."),
                        'status' => $status,
                        'priority' => 'Medium',
                        'due_date' => $this->nullableDate($oldTask->deadline),
                        'started_at' => $status === CrmTask::STATUS_IN_PROGRESS ? now() : null,
                        'completed_at' => $status === CrmTask::STATUS_DONE ? now() : null,
                    ],
                );
                $count++;
            });

        return $count;
    }

    private function importAttendance($old): int
    {
        $count = 0;
        $usersByOldId = $this->staffUsersByOldId($old);

        $old->table('rise_attendance')
            ->where('deleted', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($oldAttendance) use (&$count, $usersByOldId): void {
                $user = $usersByOldId[(int) $oldAttendance->user_id] ?? null;
                if (! $user) {
                    return;
                }

                $in = Carbon::parse($oldAttendance->in_time);
                $out = $oldAttendance->out_time ? Carbon::parse($oldAttendance->out_time) : null;
                $minutes = $out ? max(0, $in->diffInMinutes($out)) : 0;

                AttendanceRecord::query()->updateOrCreate(
                    ['user_id' => $user->id, 'work_date' => $in->toDateString()],
                    [
                        'department_id' => $user->department_id,
                        'in_time' => $in,
                        'out_time' => $out,
                        'total_minutes' => $minutes,
                        'status' => $out ? AttendanceRecord::STATUS_COMPLETED : AttendanceRecord::STATUS_PENDING_REVIEW,
                        'note' => $this->clean($oldAttendance->note).' Imported from old CRM attendance ID '.$oldAttendance->id.'.',
                    ],
                );
                $count++;
            });

        return $count;
    }

    private function staffUsersByOldId($old): array
    {
        $map = [];
        $old->table('rise_users')
            ->where('user_type', 'staff')
            ->where('deleted', 0)
            ->get()
            ->each(function ($oldUser) use (&$map): void {
                $email = $this->clean($oldUser->email);
                $name = trim(($this->clean($oldUser->first_name) ?: '').' '.($this->clean($oldUser->last_name) ?: ''));
                $user = null;

                if ($email) {
                    $user = User::query()->where('email', $email)->first();
                }

                if (! $user && $name) {
                    $user = User::query()->where('name', $name)->first();
                }

                if ($user) {
                    $map[(int) $oldUser->id] = $user;
                }
            });

        return $map;
    }

    private function clean($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $value));
        $value = preg_replace('/[ \t]+/', ' ', $value);
        $value = trim($value ?? '');

        return $value === '' ? null : $value;
    }

    private function date($value, ?string $fallback = null): string
    {
        return $this->nullableDate($value) ?: ($fallback ?: now()->toDateString());
    }

    private function nullableDate($value): ?string
    {
        if (! $value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function hasOldTable($connection, string $database, string $table): bool
    {
        return (int) $connection->table('information_schema.tables')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->count() > 0;
    }
}
