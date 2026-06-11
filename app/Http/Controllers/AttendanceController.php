<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
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
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function clockIn(Request $request, AttendanceService $attendance, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string']]);
        $record = $attendance->clockIn($request->user(), $data['note'] ?? null);
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

    public function correct(Request $request, string|int $attendanceRecord, AttendanceService $attendance, AuditLogService $audit): RedirectResponse
    {
        if (! $request->user()->canManageAttendance()) {
            abort(403);
        }

        $attendanceRecord = AttendanceRecord::query()->findOrFail($attendanceRecord);

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
