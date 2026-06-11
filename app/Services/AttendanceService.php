<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function clockIn(User $user, ?string $note = null): AttendanceRecord
    {
        return DB::transaction(function () use ($user, $note): AttendanceRecord {
            return AttendanceRecord::query()->firstOrCreate(
                ['user_id' => $user->id, 'work_date' => now()->toDateString()],
                [
                    'department_id' => $user->department_id,
                    'in_time' => now(),
                    'status' => AttendanceRecord::STATUS_CLOCKED_IN,
                    'note' => $note,
                ],
            );
        });
    }

    public function clockOut(AttendanceRecord $record, ?string $note = null): AttendanceRecord
    {
        $out = now();
        $in = $record->in_time ?: $out;
        $minutes = max(0, $in->diffInMinutes($out));

        $record->update([
            'out_time' => $out,
            'total_minutes' => $minutes,
            'status' => AttendanceRecord::STATUS_COMPLETED,
            'note' => $note ?: $record->note,
        ]);

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

        return $record->fresh();
    }
}
