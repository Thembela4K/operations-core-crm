<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\AttendanceType;
use App\Models\Department;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $todayRecord = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        $recordsQuery = AttendanceRecord::query()
            ->visibleTo($request->user())
            ->with(['user', 'department', 'corrector'])
            ->when($request->filled('department_id') && $request->user()->canViewReports(), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('user_id') && $request->user()->canViewReports(), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('work_date', '>=', $request->date('date_from')->toDateString()))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('work_date', '<=', $request->date('date_to')->toDateString()));

        $attendanceMetrics = (clone $recordsQuery)->get();
        $records = (clone $recordsQuery)
            ->latest('work_date')
            ->paginate(14)
            ->withQueryString();

        return view('attendance.index', [
            'todayRecord' => $todayRecord,
            'records' => $records,
            'attendanceRecordCount' => $attendanceMetrics->count(),
            'attendanceCompletedCount' => $attendanceMetrics->where('status', AttendanceRecord::STATUS_COMPLETED)->count(),
            'attendancePendingCount' => $attendanceMetrics->where('status', AttendanceRecord::STATUS_PENDING_REVIEW)->count(),
            'attendanceTotalHours' => round($attendanceMetrics->sum('total_minutes') / 60, 1),
            'statuses' => AttendanceRecord::STATUSES,
            'attendanceTypes' => AttendanceType::query()->where('is_active', true)->orderBy('name')->get(),
            'defaultAttendanceType' => AttendanceType::default(),
            'pendingCorrections' => $request->user()->canManageAttendance()
                ? AttendanceCorrection::query()
                    ->with(['record.user', 'requester'])
                    ->where('status', AttendanceCorrection::STATUS_PENDING)
                    ->latest()
                    ->get()
                : collect(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function clockIn(Request $request, AttendanceService $attendance, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string'],
            'attendance_type_id' => ['nullable', 'exists:attendance_types,id'],
        ]);

        $record = $attendance->clockIn(
            $request->user(),
            $data['note'] ?? null,
            isset($data['attendance_type_id']) ? (int) $data['attendance_type_id'] : null,
        );

        $audit->record('clock_in', $record, "{$request->user()->name} clocked in.");

        return back()->with('success', 'Clocked in.');
    }

    public function clockOut(Request $request, AttendanceService $attendance, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string']]);
        $record = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('work_date', now()->toDateString())
            ->firstOrFail();

        $attendance->clockOut($record, $data['note'] ?? null);
        $audit->record('clock_out', $record, "{$request->user()->name} clocked out.");

        return back()->with('success', 'Clocked out.');
    }

    /**
     * An employee asks for a recorded day to be corrected. The original clock
     * events stay untouched until a manager approves (report 5.6).
     */
    public function requestCorrection(Request $request, AttendanceRecord $attendanceRecord, AuditLogService $audit): RedirectResponse
    {
        $user = $request->user();

        if ($attendanceRecord->user_id !== $user->id && ! $user->canManageAttendance()) {
            abort(403);
        }

        $data = $request->validate([
            'proposed_in_at' => ['required', 'date'],
            'proposed_out_at' => ['nullable', 'date', 'after_or_equal:proposed_in_at'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $correction = AttendanceCorrection::query()->create([
            'attendance_record_id' => $attendanceRecord->id,
            'requested_by' => $user->id,
            'original_in_at' => $attendanceRecord->in_time,
            'original_out_at' => $attendanceRecord->out_time,
            'proposed_in_at' => $request->date('proposed_in_at'),
            'proposed_out_at' => $request->date('proposed_out_at'),
            'reason' => $data['reason'],
            'status' => AttendanceCorrection::STATUS_PENDING,
        ]);

        $audit->record('attendance_correction_requested', $correction, "Attendance correction requested for {$attendanceRecord->user?->name}.");

        return back()->with('success', 'Correction requested for approval.');
    }

    public function decideCorrection(Request $request, AttendanceCorrection $correction, AttendanceService $attendance, AuditLogService $audit): RedirectResponse
    {
        if (! $request->user()->canManageAttendance()) {
            abort(403);
        }

        if ($correction->status !== AttendanceCorrection::STATUS_PENDING) {
            return back()->with('warning', 'That correction has already been decided.');
        }

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'decision_note' => ['nullable', 'string', 'max:255'],
        ]);

        $approved = $data['decision'] === 'approve';

        if ($approved && $correction->proposed_in_at) {
            $attendance->correct(
                $correction->record,
                $correction->proposed_in_at,
                $correction->proposed_out_at,
                $correction->reason,
                $request->user(),
            );
        }

        $correction->update([
            'status' => $approved ? AttendanceCorrection::STATUS_APPROVED : AttendanceCorrection::STATUS_REJECTED,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        $audit->record(
            $approved ? 'attendance_correction_approved' : 'attendance_correction_rejected',
            $correction,
            'Attendance correction '.($approved ? 'approved' : 'rejected').'.',
        );

        return back()->with('success', 'Correction '.($approved ? 'approved and applied' : 'rejected').'.');
    }

    public function correct(Request $request, AttendanceRecord $attendanceRecord, AttendanceService $attendance, AuditLogService $audit): RedirectResponse
    {
        if (! $request->user()->canManageAttendance()) {
            abort(403);
        }

        $data = $request->validate([
            'in_time' => ['required', 'date'],
            'out_time' => ['nullable', 'date', 'after_or_equal:in_time'],
            'correction_note' => ['nullable', 'string'],
        ]);

        $attendance->correct($attendanceRecord, $request->date('in_time'), $request->date('out_time'), $data['correction_note'] ?? null, $request->user());
        $audit->record('attendance_corrected', $attendanceRecord, "Attendance corrected for {$attendanceRecord->user->name}.");

        return back()->with('success', 'Attendance corrected.');
    }
}
