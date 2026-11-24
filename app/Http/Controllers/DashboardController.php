<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\CrmNotification;
use App\Models\CrmTask;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\Submission;
use App\Models\Supplier;
use App\Models\TenderProposal;
use App\Services\ReminderService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReminderService $reminders): View
    {
        $user = $request->user();
        $salesQuotations = SalesQuotation::query()->visibleTo($user)->get();
        $invoices = Invoice::query()->visibleTo($user)->get();
        $expenses = Expense::query()->visibleTo($user)->get();
        $requisitions = Requisition::query()->visibleTo($user)->get();
        $tasks = CrmTask::query()->visibleTo($user)->with(['department', 'assignee'])->get();
        $attendance = AttendanceRecord::query()->visibleTo($user)->get();
        $clientActivities = ClientActivity::query()->visibleTo($user)->get();
        $tenderProposals = TenderProposal::query()->visibleTo($user)->with('latestAssignment.department')->get();
        $quotations = Quotation::query()->visibleTo($user)->with('latestAssignment.department')->get();
        $assignments = Assignment::query()
            ->with(['assignable', 'department'])
            ->when(! $user->canViewPortfolio(), fn ($query) => $query->where('department_id', $user->department_id))
            ->latest('assigned_at')
            ->get();
        $visibleTenderIds = $tenderProposals->pluck('id');
        $visibleQuotationIds = $quotations->pluck('id');
        $upcomingItems = $reminders->upcomingItems(ReminderService::DASHBOARD_WINDOW_DAYS)
            ->filter(function (array $item) use ($visibleTenderIds, $visibleQuotationIds): bool {
                if ($item['model'] instanceof TenderProposal) {
                    return $visibleTenderIds->contains($item['model']->id);
                }

                if ($item['model'] instanceof Quotation) {
                    return $visibleQuotationIds->contains($item['model']->id);
                }

                return false;
            })
            ->values();

        $submissionCount = Submission::query()
            ->when(! ($user->canReviewSubmissions() || $user->canManage()), fn ($query) => $query->where('department_id', $user->department_id))
            ->count();

        // Week Plan entries (tasks) carry due dates but are not Assignment
        // records, so upcomingItems() never sees them. Build their deadline
        // rows from the already permission-scoped task collection.
        $taskDeadlines = $tasks
            ->reject(fn (CrmTask $task): bool => $task->isClosed())
            ->filter(fn (CrmTask $task): bool => $task->due_date !== null)
            ->filter(fn (CrmTask $task): bool => $task->due_date->copy()->startOfDay()
                ->lessThanOrEqualTo(now()->addDays(ReminderService::DASHBOARD_WINDOW_DAYS)->startOfDay()))
            ->map(function (CrmTask $task): array {
                $dueOn = $task->due_date->copy()->startOfDay();
                $daysLeft = (int) now()->startOfDay()->diffInDays($dueOn, false);

                return [
                    'type' => 'Week Plan Entry',
                    'model' => $task,
                    'reference' => $task->task_number,
                    'title' => $task->title,
                    'department' => $task->department?->name ?? 'Unassigned',
                    'owner' => $task->assignee?->name ?? 'Unassigned',
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_label' => 'Task Due Date',
                    'due_on' => $dueOn,
                    'days_left' => $daysLeft,
                    'portal_url' => route('tasks.show', $task),
                ];
            })
            ->values();

        $upcomingItems = $upcomingItems
            ->concat($taskDeadlines)
            ->sortBy('days_left')
            ->values();

        $deadlineBands = collect([
            'Overdue' => $upcomingItems->where('days_left', '<', 0)->count(),
            'Today' => $upcomingItems->where('days_left', 0)->count(),
            'Next 5 Days' => $upcomingItems->filter(fn (array $item): bool => $item['days_left'] > 0)->count(),
        ]);

        // Workload counts assignment-based work (tenders/quotations) and open
        // week-plan entries, so a busy department is not understated.
        $departmentWorkload = $assignments
            ->map(fn (Assignment $assignment): string => $assignment->department?->name ?? 'Unassigned')
            ->concat(
                $tasks
                    ->reject(fn (CrmTask $task): bool => $task->isClosed())
                    ->map(fn (CrmTask $task): string => $task->department?->name ?? 'Unassigned')
            )
            ->countBy()
            ->sortDesc()
            ->take(6)
            ->map(fn (int $count, string $department): array => [
                'department' => $department,
                'count' => $count,
            ])
            ->values();
        $maxDepartmentWorkload = max(1, $departmentWorkload->max('count') ?? 0);

        // Module visibility drives which cards and sections render at all.
        $showClients = $user->canAccessModule('clients');
        $showFinance = $user->canAccessModule('finance');
        $showOperations = $user->canAccessModule('operations');
        $showCompany = $user->seesAllDepartments();

        return view('dashboard', [
            'showClients' => $showClients,
            'showFinance' => $showFinance,
            'showOperations' => $showOperations,
            'showCompany' => $showCompany,
            'tenderCount' => $tenderProposals->count(),
            'quotationCount' => $quotations->count(),
            'clientCount' => $showClients ? Client::query()->count() : 0,
            'salesQuotationCount' => $salesQuotations->count(),
            'salesQuotationPipeline' => $salesQuotations->sum(fn (SalesQuotation $quotation): float => (float) $quotation->total),
            'approvedSalesQuotationPipeline' => $salesQuotations->whereIn('status', [SalesQuotation::STATUS_APPROVED, SalesQuotation::STATUS_SENT, SalesQuotation::STATUS_ACCEPTED, SalesQuotation::STATUS_CONVERTED])->sum(fn (SalesQuotation $quotation): float => (float) $quotation->total),
            'invoiceCount' => $invoices->count(),
            'invoiceTotal' => $invoices->where('status', '!=', Invoice::STATUS_CANCELLED)->sum(fn (Invoice $invoice): float => (float) $invoice->total),
            'outstandingTotal' => $invoices->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])->sum(fn (Invoice $invoice): float => (float) $invoice->balance_due),
            'paymentTotal' => $user->canViewReports() ? Payment::query()->sum('amount') : Payment::query()->whereHas('invoice', fn ($query) => $query->visibleTo($user))->sum('amount'),
            'expenseTotal' => $expenses->sum(fn (Expense $expense): float => (float) $expense->total_amount),
            'requisitionCount' => $requisitions->count(),
            'openRequisitions' => $requisitions->whereNotIn('status', [Requisition::STATUS_FUNDS_RELEASED, Requisition::STATUS_CANCELLED])->count(),
            'pendingRequisitionApprovals' => $requisitions->whereIn('status', [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW])->count(),
            'requisitionStatusCounts' => $requisitions->countBy('status'),
            'taskCount' => $tasks->count(),
            'openTaskCount' => $tasks->whereNotIn('status', [CrmTask::STATUS_DONE, CrmTask::STATUS_CANCELLED])->count(),
            'overdueTaskCount' => $tasks->whereNotIn('status', [CrmTask::STATUS_DONE, CrmTask::STATUS_CANCELLED])->where('due_date', '<', now()->startOfDay())->count(),
            'attendanceHours' => round($attendance->sum('total_minutes') / 60, 1),
            'supplierCount' => $showFinance ? Supplier::query()->count() : 0,
            'documentCount' => $user->canAccessModule('documents') ? Document::query()->count() : 0,
            'unreadCrmNotifications' => CrmNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count(),
            'openFollowUps' => $clientActivities->where('status', 'Open')->count(),
            'overdueFollowUps' => $clientActivities->where('status', 'Open')->where('next_follow_up_date', '<', now()->startOfDay())->count(),
            'taskStatusCounts' => $tasks->countBy('status'),
            'openTenders' => $tenderProposals->whereNotIn('status', ['Closed', 'Cancelled'])->count(),
            'openQuotations' => $quotations->whereNotIn('status', ['Accepted', 'Rejected', 'Expired'])->count(),
            'failedAssignments' => $assignments->where('status', 'Assignment Email Failed')->count(),
            'unreadAssignments' => $assignments->whereNull('read_at')->count(),
            'unreadAssignmentList' => $assignments->whereNull('read_at')->take(6),
            'submissionCount' => $submissionCount,
            'tenderStatusCounts' => $tenderProposals->countBy('status'),
            'quotationStatusCounts' => $quotations->countBy('status'),
            'salesQuotationStatusCounts' => $salesQuotations->countBy('status'),
            'invoiceStatusCounts' => $invoices->countBy('status'),
            'assignmentStatusCounts' => $assignments->countBy('workflow_status'),
            'departmentWorkload' => $departmentWorkload,
            'maxDepartmentWorkload' => $maxDepartmentWorkload,
            'deadlineBands' => $deadlineBands,
            'upcomingItems' => $upcomingItems->take(6),
            'sppraUrl' => $user->canAccessSppra() ? config('services.sppra.url') : null,
        ]);
    }
}
