<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\ReminderLog;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(ReminderService $reminderService): View
    {
        return view('reminders.index', [
            'upcomingItems' => $reminderService->upcomingItems(),
            'reminderLogs' => ReminderLog::query()->with('remindable')->latest()->paginate(15),
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
}
