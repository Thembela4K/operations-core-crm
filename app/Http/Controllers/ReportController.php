<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\ClientActivity;
use App\Models\CrmTask;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseRecord;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\Submission;
use App\Models\Supplier;
use App\Models\TenderProposal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        if (! $request->user()->canViewReports()) {
            abort(403);
        }

        $salesQuotations = SalesQuotation::query()->with('department')->get();
        $invoices = Invoice::query()->with('department')->get();
        $expenses = Expense::query()->with('department')->get();
        $payments = Payment::query()->with('invoice.client')->get();
        $assignments = Assignment::query()->with('department')->get();
        $requisitions = Requisition::query()->with('department')->get();
        $tasks = CrmTask::query()->with('department')->get();
        $attendance = AttendanceRecord::query()->with('department')->get();
        $clientActivities = ClientActivity::query()->get();
        $purchases = PurchaseRecord::query()->with('department')->get();

        $salesQuotationTotal = $salesQuotations->sum(fn (SalesQuotation $quotation): float => (float) $quotation->total);
        $approvedQuotationTotal = $salesQuotations
            ->whereIn('status', [SalesQuotation::STATUS_APPROVED, SalesQuotation::STATUS_SENT, SalesQuotation::STATUS_ACCEPTED, SalesQuotation::STATUS_CONVERTED])
            ->sum(fn (SalesQuotation $quotation): float => (float) $quotation->total);
        $invoiceTotal = $invoices
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->sum(fn (Invoice $invoice): float => (float) $invoice->total);
        $outstandingTotal = $invoices
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->sum(fn (Invoice $invoice): float => (float) $invoice->balance_due);
        $paymentsTotal = $payments->sum(fn (Payment $payment): float => (float) $payment->amount);
        $expenseTotal = $expenses->sum(fn (Expense $expense): float => (float) $expense->total_amount);
        $purchaseTotal = $purchases->sum(fn (PurchaseRecord $purchase): float => (float) $purchase->amount);
        $taskStatusCounts = $tasks->countBy('status');
        $quotationStatusCounts = $salesQuotations->countBy('status');
        $invoiceStatusCounts = $invoices->countBy('status');

        $departmentRevenue = $invoices
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->groupBy(fn (Invoice $invoice): string => $invoice->department?->name ?? 'Unassigned')
            ->map(fn ($rows, string $department): array => [
                'department' => $department,
                'total' => $rows->sum(fn (Invoice $invoice): float => (float) $invoice->total),
            ])
            ->sortByDesc('total')
            ->values();

        $departmentExpenses = $expenses
            ->groupBy(fn (Expense $expense): string => $expense->department?->name ?? 'Unassigned')
            ->map(fn ($rows, string $department): array => [
                'department' => $department,
                'total' => $rows->sum(fn (Expense $expense): float => (float) $expense->total_amount),
            ])
            ->sortByDesc('total')
            ->values();

        $operationCounts = [
            'Tender Proposals' => TenderProposal::query()->count(),
            'Assignments' => $assignments->count(),
            'Submissions' => Submission::query()->count(),
            'Requisitions' => $requisitions->count(),
            'Pending Requisitions' => $requisitions->whereIn('status', [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW])->count(),
            'Unread Assignments' => $assignments->whereNull('read_at')->count(),
        ];

        $approvalCounts = [
            'Sales Quotations' => $salesQuotations->where('status', SalesQuotation::STATUS_SUBMITTED)->count(),
            'Requisitions' => $requisitions->whereIn('status', [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW])->count(),
            'Funds Release' => $requisitions->where('status', Requisition::STATUS_APPROVED)->count(),
        ];

        $monthlyTrend = collect(range(5, 0))->map(function (int $monthsAgo) use ($invoices, $payments, $expenses, $salesQuotations): array {
            $month = now()->copy()->startOfMonth()->subMonths($monthsAgo);
            $monthKey = $month->format('Y-m');

            return [
                'label' => $month->format('M'),
                'revenue' => $invoices
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->filter(fn (Invoice $invoice): bool => $invoice->issue_date?->format('Y-m') === $monthKey)
                    ->sum(fn (Invoice $invoice): float => (float) $invoice->total),
                'payments' => $payments
                    ->filter(fn (Payment $payment): bool => $payment->payment_date?->format('Y-m') === $monthKey)
                    ->sum(fn (Payment $payment): float => (float) $payment->amount),
                'expenses' => $expenses
                    ->filter(fn (Expense $expense): bool => $expense->expense_date?->format('Y-m') === $monthKey)
                    ->sum(fn (Expense $expense): float => (float) $expense->total_amount),
                'quotations' => $salesQuotations
                    ->filter(fn (SalesQuotation $quotation): bool => $quotation->issue_date?->format('Y-m') === $monthKey)
                    ->count(),
            ];
        });

        $maxTrendAmount = max(1, $monthlyTrend->max(fn (array $row): float => max($row['revenue'], $row['payments'], $row['expenses'])) ?? 0);
        $maxTrendQuotes = max(1, $monthlyTrend->max('quotations') ?? 0);

        return view('reports.index', [
            'salesQuotationTotal' => $salesQuotationTotal,
            'approvedQuotationTotal' => $approvedQuotationTotal,
            'invoiceTotal' => $invoiceTotal,
            'outstandingTotal' => $outstandingTotal,
            'paymentsTotal' => $paymentsTotal,
            'expenseTotal' => $expenseTotal,
            'supplierCount' => Supplier::query()->count(),
            'purchaseTotal' => $purchaseTotal,
            'taskCount' => $tasks->count(),
            'overdueTaskCount' => $tasks->whereNotIn('status', [CrmTask::STATUS_DONE, CrmTask::STATUS_CANCELLED])->where('due_date', '<', now()->startOfDay())->count(),
            'attendanceHours' => round($attendance->sum('total_minutes') / 60, 1),
            'documentCount' => Document::query()->count(),
            'openFollowUps' => $clientActivities->where('status', 'Open')->count(),
            'overdueFollowUps' => $clientActivities->where('status', 'Open')->where('next_follow_up_date', '<', now()->startOfDay())->count(),
            'collectionRate' => $invoiceTotal > 0 ? min(100, round(($paymentsTotal / $invoiceTotal) * 100)) : 0,
            'expenseRatio' => $invoiceTotal > 0 ? min(100, round(($expenseTotal / $invoiceTotal) * 100)) : 0,
            'monthlyTrend' => $monthlyTrend,
            'maxTrendAmount' => $maxTrendAmount,
            'maxTrendQuotes' => $maxTrendQuotes,
            'quotationStatusCounts' => $quotationStatusCounts,
            'invoiceStatusCounts' => $invoiceStatusCounts,
            'taskStatusCounts' => $taskStatusCounts,
            'departmentRevenue' => $departmentRevenue,
            'departmentExpenses' => $departmentExpenses,
            'operationCounts' => $operationCounts,
            'maxQuotationStatus' => max(1, $quotationStatusCounts->max() ?? 0),
            'maxInvoiceStatus' => max(1, $invoiceStatusCounts->max() ?? 0),
            'maxTaskStatus' => max(1, $taskStatusCounts->max() ?? 0),
            'operationTotal' => max(1, array_sum($operationCounts)),
            'departmentTaskWorkload' => $tasks
                ->whereNotIn('status', [CrmTask::STATUS_DONE, CrmTask::STATUS_CANCELLED])
                ->groupBy(fn (CrmTask $task): string => $task->department?->name ?? 'Unassigned')
                ->map(fn ($rows, string $department): array => ['department' => $department, 'count' => $rows->count()])
                ->sortByDesc('count')
                ->values(),
            'departmentAttendance' => $attendance
                ->groupBy(fn (AttendanceRecord $record): string => $record->department?->name ?? 'Unassigned')
                ->map(fn ($rows, string $department): array => ['department' => $department, 'hours' => round($rows->sum('total_minutes') / 60, 1)])
                ->sortByDesc('hours')
                ->values(),
            'maxDepartmentAttendance' => max(1, $attendance->groupBy(fn (AttendanceRecord $record): string => $record->department?->name ?? 'Unassigned')->max(fn ($rows): float => round($rows->sum('total_minutes') / 60, 1)) ?? 0),
            'maxDepartmentTaskWorkload' => max(1, $tasks->whereNotIn('status', [CrmTask::STATUS_DONE, CrmTask::STATUS_CANCELLED])->groupBy(fn (CrmTask $task): string => $task->department?->name ?? 'Unassigned')->max(fn ($rows): int => $rows->count()) ?? 0),
            'approvalCounts' => $approvalCounts,
            'approvalTotal' => max(1, array_sum($approvalCounts)),
        ]);
    }
}
