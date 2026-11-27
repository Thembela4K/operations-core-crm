<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendanceType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function clockIn(User $user, ?string $note = null, ?int $attendanceTypeId = null): AttendanceRecord
    {
        return DB::transaction(function () use ($user, $note, $attendanceTypeId): AttendanceRecord {
            $typeId = $attendanceTypeId ?: AttendanceType::default()?->id;

            $record = AttendanceRecord::query()->firstOrCreate(
                ['user_id' => $user->id, 'work_date' => now()->toDateString()],
                [
                    'department_id' => $user->department_id,
                    'attendance_type_id' => $typeId,
                    'in_time' => now(),
                    'status' => AttendanceRecord::STATUS_CLOCKED_IN,
                    'note' => $note,
                ],
            );

            if ($record->wasRecentlyCreated) {
                $this->logEvent($record, AttendanceEvent::TYPE_CLOCK_IN, $user, $typeId, $note);
            }

            return $record;
        });
    }

    public function clockOut(AttendanceRecord $record, ?string $note = null, ?User $actor = null): AttendanceRecord
    {
        $out = now();
        $in = $record->in_time ?: $out;
        $minutes = max(0, $in->diffInMinutes($out));

        // The original clock-in is never overwritten; the out event is appended.
        $record->update([
            'out_time' => $out,
            'total_minutes' => $minutes,
            'status' => AttendanceRecord::STATUS_COMPLETED,
            'note' => $note ?: $record->note,
        ]);

        $this->logEvent($record, AttendanceEvent::TYPE_CLOCK_OUT, $actor, $record->attendance_type_id, $note);

        return $record->fresh();
    }

    public function correct(AttendanceRecord $record, Carbon $in, ?Carbon $out, ?string $note, User $corrector): AttendanceRecord
    {
        $minutes = $out ? max(0, $in->diffInMinutes($out)) : 0;

        $record->update([
            'in_time' => $in,
            'out_time' => $out,
            'total_minutes' => $minutes,
            'status' => $out ? AttendanceRecord::STATUS_CORRECTED : AttendanceRecord::STATUS_PENDING_REVIEW,
            'correction_note' => $note,
            'corrected_by' => $corrector->id,
            'corrected_at' => now(),
        ]);

        // Corrections are additional audited actions, not silent rewrites.
        $this->logEvent($record, AttendanceEvent::TYPE_CORRECTION, $corrector, $record->attendance_type_id, $note);

        return $record->fresh();
    }

    private function logEvent(AttendanceRecord $record, string $eventType, ?User $actor, ?int $attendanceTypeId, ?string $note): void
    {
        AttendanceEvent::query()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $record->user_id,
            'event_type' => $eventType,
            'attendance_type' => $attendanceTypeId ? AttendanceType::query()->find($attendanceTypeId)?->name : null,
            'occurred_at' => now(),
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }
}
