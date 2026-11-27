<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSchedule;
use App\Models\AttendanceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Attendance type administration (report 5.5): types live in a table and are
 * maintained by authorised administrators, with exactly one active default.
 */
class AttendanceTypeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('settings.attendance-types', [
            'types' => AttendanceType::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'schedules' => AttendanceSchedule::query()->orderBy('weekday')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:attendance_types,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        AttendanceType::query()->create($data + ['is_active' => true, 'is_default' => false]);

        return back()->with('success', 'Attendance type added.');
    }

    public function update(Request $request, AttendanceType $attendanceType): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:attendance_types,name,'.$attendanceType->id],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($attendanceType, $data, $request): void {
            $makeDefault = $request->boolean('is_default');

            $attendanceType->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]);

            if ($makeDefault && $attendanceType->is_active) {
                AttendanceType::query()->whereKeyNot($attendanceType->id)->update(['is_default' => false]);
                $attendanceType->update(['is_default' => true]);
            } elseif ($attendanceType->is_default) {
                // A type that is no longer the default must hand that role on.
                $attendanceType->update(['is_default' => false]);
                $this->ensureDefault();
            }
        });

        $this->ensureDefault();

        return back()->with('success', 'Attendance type updated.');
    }

    /**
     * There must always be exactly one active default type.
     */
    private function ensureDefault(): void
    {
        if (AttendanceType::query()->where('is_default', true)->where('is_active', true)->exists()) {
            return;
        }

        $fallback = AttendanceType::query()->where('is_active', true)->orderByRaw("name = 'Office' DESC")->orderBy('id')->first();

        if ($fallback) {
            AttendanceType::query()->update(['is_default' => false]);
            $fallback->update(['is_default' => true]);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()->canAccessModule('admin')) {
            abort(403);
        }
    }
}
