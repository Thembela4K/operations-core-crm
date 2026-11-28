<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\ReminderLog;
use App\Services\DeadlineService;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(Request $request, ReminderService $reminderService, DeadlineService $deadlines): View
    {
        $user = $request->user();

        // Assignments (tenders/quotations) plus week-plan entries, using the
        // same scope rules as the dashboard.
        $items = $deadlines
            ->filterAssignments($reminderService->upcomingItems(), $user)
            ->concat($deadlines->taskItems($user))
            ->sortBy('days_left')
            ->values();

        return view('reminders.index', [
            'upcomingItems' => $this->paginateCollection($items, 'deadlines_page'),
            'reminderLogs' => ReminderLog::query()
                ->with(['remindable', 'assignment.department'])
                ->latest()
                ->paginate(15, ['*'], 'logs_page')
                ->withQueryString(),
            'emailLogs' => EmailLog::query()->where('category', 'reminder')->latest()->take(20)->get(),
            'tenderReminderDays' => ReminderService::TENDER_REMINDER_DAYS_BEFORE,
            'quotationReminderHours' => ReminderService::QUOTATION_REMINDER_HOURS_BEFORE,
        ]);
    }

    public function sendDue(Request $request, ReminderService $reminderService): RedirectResponse
    {
        if (! $request->user()->canManage()) {
            abort(403);
        }

        $overdue = $reminderService->markOverdueQuotations();
        $sent = $reminderService->sendDueReminders(markOverdue: false);

        return back()->with('success', "{$sent} due reminder emails sent. {$overdue} overdue quotation(s) marked.");
    }

    private function paginateCollection(Collection $items, string $pageName): LengthAwarePaginator
    {
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ],
        ))->withQueryString();
    }
}
