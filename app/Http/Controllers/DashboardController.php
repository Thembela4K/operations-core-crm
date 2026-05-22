<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Assignment;
use App\Models\Quotation;
use App\Models\Submission;
use App\Models\TenderProposal;
use App\Services\ReminderService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReminderService $reminders): View
    {
        $user = $request->user();
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

        $deadlineBands = collect([
            'Overdue' => $upcomingItems->where('days_left', '<', 0)->count(),
            'Today' => $upcomingItems->where('days_left', 0)->count(),
            'Next 5 Days' => $upcomingItems->filter(fn (array $item): bool => $item['days_left'] > 0)->count(),
        ]);

        $departmentWorkload = $assignments
            ->groupBy(fn (Assignment $assignment): string => $assignment->department?->name ?? 'Unassigned')
            ->map(fn ($items, string $department): array => [
                'department' => $department,
                'count' => $items->count(),
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values();
        $maxDepartmentWorkload = max(1, $departmentWorkload->max('count') ?? 0);

        return view('dashboard', [
            'tenderCount' => $tenderProposals->count(),
            'quotationCount' => $quotations->count(),
            'openTenders' => $tenderProposals->whereNotIn('status', ['Closed', 'Cancelled'])->count(),
            'openQuotations' => $quotations->whereNotIn('status', ['Accepted', 'Rejected', 'Expired'])->count(),
            'failedAssignments' => $assignments->where('status', 'Assignment Email Failed')->count(),
            'unreadAssignments' => $assignments->whereNull('read_at')->count(),
            'unreadAssignmentList' => $assignments->whereNull('read_at')->take(6),
            'submissionCount' => $submissionCount,
            'tenderStatusCounts' => $tenderProposals->countBy('status'),
            'quotationStatusCounts' => $quotations->countBy('status'),
            'assignmentStatusCounts' => $assignments->countBy('workflow_status'),
            'departmentWorkload' => $departmentWorkload,
            'maxDepartmentWorkload' => $maxDepartmentWorkload,
            'deadlineBands' => $deadlineBands,
            'upcomingItems' => $upcomingItems->take(6),
            'sppraUrl' => $user->canAccessSppra() ? AppSetting::valueFor('sppra_url') : null,
        ]);
    }
}
