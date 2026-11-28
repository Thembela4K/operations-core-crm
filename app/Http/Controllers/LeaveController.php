<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\LeaveService;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function index(Request $request, LeaveService $leaveService): View
    {
        $user = $request->user();

        $query = LeaveApplication::query()
            ->visibleTo($user)
            ->with(['user', 'department', 'decider'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('year'), fn ($q) => $q->where('leave_year', $request->integer('year')))
            ->when(
                $request->filled('user_id') && $user->canViewAllLeave(),
                fn ($q) => $q->where('user_id', $request->integer('user_id'))
            )
            ->when(
                $user->canViewAllLeave() === false,
                fn ($q) => $q->where('user_id', $user->id)
            );

        $records = (clone $query)->latest('from_date')->paginate(15)->withQueryString();

        $pendingCount = $user->canApproveLeave()
            ? LeaveApplication::query()->pendingReview()->count()
            : 0;

        $balanceUser = $user->canViewAllLeave() && $request->filled('user_id')
            ? User::query()->find($request->integer('user_id')) ?? $user
            : $user;

        $balance = $leaveService->balanceFor($balanceUser, $request->filled('year') ? $request->integer('year') : null);

        return view('leave.index', [
            'records' => $records,
            'balance' => $balance,
            'balanceUser' => $balanceUser,
            'pendingCount' => $pendingCount,
            'statuses' => LeaveApplication::STATUSES,
            'users' => $user->canViewAllLeave()
                ? User::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'holidays' => PublicHoliday::query()->orderBy('holiday_date')->get(),
            'canManageHolidays' => $user->canApproveLeave(),
            'canViewAll' => $user->canViewAllLeave(),
        ]);
    }

    public function create(Request $request, LeaveService $leaveService): View
    {
        $user = $request->user();

        return view('leave.form', [
            'balance' => $leaveService->balanceFor($user),
            'types' => LeaveApplication::TYPES,
            'users' => $user->canViewAllLeave()
                ? User::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'canApplyForOthers' => $user->isBusinessAdmin(),
        ]);
    }

    public function store(Request $request, LeaveService $leaveService, AuditLogService $audit): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'leave_type' => ['required', Rule::in(array_keys(LeaveApplication::TYPES))],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'back_at_work_on' => ['nullable', 'date', 'after_or_equal:to_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'certificate_attached' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $subject = $actor;

        if (! empty($data['user_id']) && (int) $data['user_id'] !== $actor->id) {
            if (! $actor->isBusinessAdmin()) {
                abort(403);
            }

            $subject = User::query()->findOrFail((int) $data['user_id']);
        }

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('leave-attachments');
            $data['certificate_attached'] = true;
        }

        $leave = $leaveService->apply($subject, $data);

        $audit->record('leave_applied', $leave, "{$subject->name} applied for {$leave->typeLabel()}.");

        return redirect()
            ->route('leave.show', $leave)
            ->with('success', 'Leave application submitted.');
    }

    public function show(Request $request, LeaveApplication $leave): View
    {
        $this->authorizeView($request, $leave);

        $leave->load(['user.department', 'department', 'decider']);

        return view('leave.show', [
            'leave' => $leave,
            'canDecide' => $request->user()->canApproveLeave()
                && $leave->canBeDecided()
                && $leave->user_id !== $request->user()->id,
            'canCancel' => $leave->isPending()
                && ($leave->user_id === $request->user()->id || $request->user()->canApproveLeave()),
        ]);
    }

    public function print(Request $request, LeaveApplication $leave): View
    {
        $this->authorizeView($request, $leave);

        $leave->load(['user.department', 'department', 'decider']);

        return view('leave.print', ['leave' => $leave]);
    }

    public function pdf(Request $request, LeaveApplication $leave): Response
    {
        $this->authorizeView($request, $leave);

        $leave->load(['user.department', 'department', 'decider']);

        // The laravel-dompdf provider is intentionally not auto-discovered in
        // this project, so the underlying Dompdf library is used directly.
        $dompdf = new Dompdf([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ]);
        $dompdf->loadHtml(view('leave.pdf', ['leave' => $leave])->render());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'leave-'.str_replace(['/', ' '], '-', (string) $leave->user->name).'-'.$leave->from_date->toDateString().'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function attachment(Request $request, LeaveApplication $leave)
    {
        $this->authorizeView($request, $leave);

        abort_unless($leave->attachment_path && Storage::disk('local')->exists($leave->attachment_path), 404);

        return Storage::disk('local')->download($leave->attachment_path);
    }

    public function approve(Request $request, LeaveApplication $leave, LeaveService $leaveService, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user()->canApproveLeave(), 403);

        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:1000']]);

        $leaveService->approve($leave, $request->user(), $data['decision_note'] ?? null);
        $audit->record('leave_approved', $leave, "Leave approved for {$leave->user->name}.");

        return back()->with('success', 'Leave application approved.');
    }

    public function reject(Request $request, LeaveApplication $leave, LeaveService $leaveService, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user()->canApproveLeave(), 403);

        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:1000']]);

        $leaveService->reject($leave, $request->user(), $data['decision_note'] ?? null);
        $audit->record('leave_rejected', $leave, "Leave rejected for {$leave->user->name}.");

        return back()->with('success', 'Leave application rejected.');
    }

    public function cancel(Request $request, LeaveApplication $leave, LeaveService $leaveService, AuditLogService $audit): RedirectResponse
    {
        abort_unless(
            $leave->user_id === $request->user()->id || $request->user()->canApproveLeave(),
            403,
        );

        $leaveService->cancel($leave, $request->user());
        $audit->record('leave_cancelled', $leave, "Leave application cancelled for {$leave->user->name}.");

        return back()->with('success', 'Leave application cancelled.');
    }

    public function storeHoliday(Request $request, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user()->canApproveLeave(), 403);

        $data = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:public_holidays,holiday_date'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $holiday = PublicHoliday::query()->create([
            'holiday_date' => $data['holiday_date'],
            'name' => $data['name'],
            'is_active' => true,
        ]);

        $audit->record('public_holiday_added', $holiday, "Public holiday added: {$holiday->name}.");

        return back()->with('success', 'Public holiday added.');
    }

    public function destroyHoliday(Request $request, PublicHoliday $holiday, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user()->canApproveLeave(), 403);

        $audit->record('public_holiday_removed', $holiday, "Public holiday removed: {$holiday->name}.");
        $holiday->delete();

        return back()->with('success', 'Public holiday removed.');
    }

    private function authorizeView(Request $request, LeaveApplication $leave): void
    {
        $user = $request->user();

        abort_unless(
            $user->canViewAllLeave() || $leave->user_id === $user->id,
            403,
        );
    }
}
