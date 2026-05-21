<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\ReminderLog;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(ReminderService $reminderService): View
    {
        return view('reminders.index', [
            'upcomingItems' => $this->paginateCollection($reminderService->upcomingItems(), 'deadlines_page'),
            'reminderLogs' => ReminderLog::query()
                ->with('remindable')
                ->latest()
                ->paginate(15, ['*'], 'logs_page')
                ->withQueryString(),
            'emailLogs' => EmailLog::query()->where('category', 'reminder')->latest()->take(20)->get(),
            'daysBefore' => ReminderService::DAYS_BEFORE,
        ]);
    }

    public function sendDue(Request $request, ReminderService $reminderService): RedirectResponse
    {
        if (! $request->user()->canManage()) {
            abort(403);
        }

        $sent = $reminderService->sendDueReminders();

        return back()->with('success', "{$sent} due reminder emails sent.");
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
