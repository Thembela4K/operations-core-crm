<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\EmailLog;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AssignmentEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(): View
    {
        return view('assignments.index', [
            'projects' => Project::query()->with('latestAssignment.department')->orderBy('name')->get(),
            'quotations' => Quotation::query()->with('latestAssignment.department')->orderBy('opportunity')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->with('department')->orderBy('name')->get(),
            'assignments' => Assignment::query()
                ->with(['assignable', 'department', 'assignedUser'])
                ->latest()
                ->paginate(15),
            'emailLogs' => EmailLog::query()
                ->where('category', 'assignment')
                ->latest()
                ->take(20)
                ->get(),
        ]);
    }

    public function store(Request $request, AssignmentEmailService $emailService): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', 'string'],
            'department_id' => ['required', 'exists:departments,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'assignee_name' => ['nullable', 'string', 'max:255'],
            'assignee_email' => ['nullable', 'email', 'max:255'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        [$module, $recordId] = array_pad(explode(':', $data['target'], 2), 2, null);
        abort_unless(in_array($module, ['project', 'quotation'], true) && is_numeric($recordId), 422);

        $record = $module === 'project'
            ? Project::query()->findOrFail((int) $recordId)
            : Quotation::query()->findOrFail((int) $recordId);
        $assignedUser = isset($data['assigned_user_id']) ? User::query()->find($data['assigned_user_id']) : null;
        $assigneeName = $data['assignee_name'] ?: $assignedUser?->name;
        $assigneeEmail = $data['assignee_email'] ?: $assignedUser?->email;

        if (! $assigneeName || ! $assigneeEmail) {
            return back()
                ->withErrors(['assignee_name' => 'Choose an active user or enter assignee name and email.'])
                ->withInput();
        }

        $assignment = $record->assignments()->create([
            'department_id' => $data['department_id'],
            'assigned_user_id' => $assignedUser?->id,
            'assignee_name' => $assigneeName,
            'assignee_email' => $assigneeEmail,
            'status' => 'Assigned',
            'assigned_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        if ($request->boolean('send_email')) {
            $status = $emailService->send($assignment);

            return redirect()->route('assignments.index')->with(
                $status === 'Assignment Email Sent' ? 'success' : 'warning',
                $status === 'Assignment Email Sent'
                    ? 'Assignment saved and email sent.'
                    : 'Assignment saved, but the email failed. Check the email log.',
            );
        }

        return redirect()->route('assignments.index')->with('success', 'Assignment saved.');
    }
}
