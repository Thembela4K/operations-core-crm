<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\EmailLog;
use App\Models\Quotation;
use App\Models\StaffMember;
use App\Models\TenderProposal;
use App\Models\User;
use App\Services\AssignmentEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        return view('assignments.index', [
            'tenderProposals' => TenderProposal::query()->with('latestAssignment.department')->orderBy('title')->get(),
            'quotations' => Quotation::query()->with('latestAssignment.department')->orderBy('opportunity')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'staffMembers' => StaffMember::query()
                ->where('is_active', true)
                ->with('department')
                ->orderBy('name')
                ->get(),
            'assignments' => Assignment::query()
                ->with(['assignable', 'department', 'assignedUser'])
                ->latest()
                ->paginate(15),
            'emailLogs' => EmailLog::query()
                ->where('category', 'assignment')
                ->latest()
                ->take(20)
                ->get(),
            'selectedTarget' => $request->string('target')->toString(),
        ]);
    }

    public function store(Request $request, AssignmentEmailService $emailService): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', 'string'],
            'department_id' => ['required', 'exists:departments,id'],
            'staff_member_id' => ['nullable', 'exists:staff_members,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'assignee_name' => ['nullable', 'string', 'max:255'],
            'assignee_email' => ['nullable', 'email', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        [$module, $recordId] = array_pad(explode(':', $data['target'], 2), 2, null);
        abort_unless(in_array($module, ['tender_proposal', 'quotation'], true) && is_numeric($recordId), 422);

        $record = $module === 'tender_proposal'
            ? TenderProposal::query()->findOrFail((int) $recordId)
            : Quotation::query()->findOrFail((int) $recordId);
        $department = Department::query()->findOrFail($data['department_id']);
        $staffMember = isset($data['staff_member_id'])
            ? StaffMember::query()->where('is_active', true)->find($data['staff_member_id'])
            : null;
        $assignedUser = isset($data['assigned_user_id'])
            ? User::query()->where('is_active', true)->find($data['assigned_user_id'])
            : null;

        if ($staffMember && (int) $staffMember->department_id !== (int) $department->id) {
            return back()
                ->withErrors(['staff_member_id' => 'Choose a staff member from the selected department.'])
                ->withInput();
        }

        if ($assignedUser && (int) $assignedUser->department_id !== (int) $department->id) {
            return back()
                ->withErrors(['assigned_user_id' => 'Choose a staff member from the selected department.'])
                ->withInput();
        }

        $assigneeName = $staffMember?->name ?: ($assignedUser?->name ?: ($data['assignee_name'] ?: $department->name));
        $assigneeEmail = $staffMember?->email ?: ($department->email ?: ($assignedUser?->email ?: ($data['assignee_email'] ?? null)));

        if (! $assigneeName || ! $assigneeEmail) {
            return back()
                ->withErrors(['assignee_name' => 'Choose a department staff member or set an email for the selected department.'])
                ->withInput();
        }

        $assignment = $record->assignments()->create([
            'department_id' => $data['department_id'],
            'assigned_user_id' => $assignedUser?->id,
            'assignee_name' => $assigneeName,
            'assignee_email' => $assigneeEmail,
            'status' => 'Assigned',
            'workflow_status' => 'Assigned',
            'assigned_at' => now(),
            'due_date' => $data['due_date'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if ($record instanceof TenderProposal && $record->status === 'Draft') {
            $record->update(['status' => 'Assigned']);
        }

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
