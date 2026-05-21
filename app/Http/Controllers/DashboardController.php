<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Quotation;
use App\Services\ReminderService;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ScoringService $scoring, ReminderService $reminders): View
    {
        $user = $request->user();
        $projects = Project::query()->visibleTo($user)->with('latestAssignment.department')->get();
        $quotations = Quotation::query()->visibleTo($user)->with('latestAssignment.department')->get();

        $projectScores = $projects->map(fn (Project $project): float => $scoring->projectHealth($project));
        $quotationScores = $quotations->map(fn (Quotation $quotation): float => $scoring->quotationScore($quotation));
        $failedAssignments = Assignment::query()
            ->where('status', 'Assignment Email Failed')
            ->when(! $user->canManage(), fn ($query) => $query->where('department_id', $user->department_id))
            ->count();

        return view('dashboard', [
            'projectCount' => $projects->count(),
            'quotationCount' => $quotations->count(),
            'averageProjectScore' => round($projectScores->avg() ?? 0, 1),
            'averageQuotationScore' => round($quotationScores->avg() ?? 0, 1),
            'openProjects' => $projects->where('status', '!=', 'Completed')->count(),
            'openQuotations' => $quotations->whereNotIn('status', ['Accepted', 'Rejected', 'Expired'])->count(),
            'failedAssignments' => $failedAssignments,
            'projectStatusCounts' => $projects->countBy('status'),
            'quotationStatusCounts' => $quotations->countBy('status'),
            'highRiskProjects' => $projects->where('risk', 'High')->sortBy('deadline')->take(5),
            'highRiskQuotations' => $quotations->where('risk', 'High')->sortBy('valid_until')->take(5),
            'upcomingItems' => $reminders->upcomingItems()->take(8),
        ]);
    }
}
