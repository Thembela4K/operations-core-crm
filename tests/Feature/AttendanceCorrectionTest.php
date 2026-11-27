<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\AttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Attendance correction requests (report 5.6): employees request, managers
 * decide, and the original clock events are only changed on approval.
 */
class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AttendanceSeeder::class);
    }

    public function test_an_employee_can_request_a_correction_for_their_own_record(): void
    {
        $user = $this->user();
        $record = $this->record($user);

        $this->actingAs($user)->post(route('attendance.corrections.store', $record), [
            'proposed_in_at' => now()->setTime(8, 0)->format('Y-m-d H:i'),
            'proposed_out_at' => now()->setTime(17, 0)->format('Y-m-d H:i'),
            'reason' => 'I forgot to clock in on arrival.',
        ])->assertRedirect();

        $correction = AttendanceCorrection::query()->firstOrFail();

        $this->assertSame(AttendanceCorrection::STATUS_PENDING, $correction->status);
        $this->assertSame($user->id, $correction->requested_by);

        // Nothing changes until it is approved.
        $this->assertSame('09:30', $record->fresh()->in_time->format('H:i'));
    }

    public function test_an_employee_cannot_request_a_correction_for_someone_else(): void
    {
        $owner = $this->user();
        $other = $this->user('Other Person', 'other@example.com');
        $record = $this->record($owner);

        $this->actingAs($other)->post(route('attendance.corrections.store', $record), [
            'proposed_in_at' => now()->setTime(8, 0)->format('Y-m-d H:i'),
            'reason' => 'Not mine to change.',
        ])->assertForbidden();
    }

    public function test_approving_a_correction_applies_it_and_logs_an_event(): void
    {
        $user = $this->user();
        $manager = $this->manager();
        $record = $this->record($user);

        $this->actingAs($user)->post(route('attendance.corrections.store', $record), [
            'proposed_in_at' => now()->setTime(8, 0)->format('Y-m-d H:i'),
            'proposed_out_at' => now()->setTime(17, 0)->format('Y-m-d H:i'),
            'reason' => 'Late arrival not recorded.',
        ]);

        $correction = AttendanceCorrection::query()->firstOrFail();

        $this->actingAs($manager)->post(route('attendance.corrections.decide', $correction), [
            'decision' => 'approve',
            'decision_note' => 'Confirmed with the team.',
        ])->assertRedirect();

        $correction = $correction->fresh();
        $record = $record->fresh();

        $this->assertSame(AttendanceCorrection::STATUS_APPROVED, $correction->status);
        $this->assertSame($manager->id, $correction->decided_by);
        $this->assertNotNull($correction->decided_at);

        $this->assertSame('08:00', $record->in_time->format('H:i'));
        $this->assertSame(AttendanceRecord::STATUS_CORRECTED, $record->status);

        // The correction is an additional audited event, not a silent rewrite.
        $this->assertSame(1, AttendanceEvent::query()->where('event_type', AttendanceEvent::TYPE_CORRECTION)->count());
    }

    public function test_rejecting_a_correction_leaves_the_record_untouched(): void
    {
        $user = $this->user();
        $manager = $this->manager();
        $record = $this->record($user);

        $this->actingAs($user)->post(route('attendance.corrections.store', $record), [
            'proposed_in_at' => now()->setTime(7, 0)->format('Y-m-d H:i'),
            'reason' => 'Disagreement about start time.',
        ]);

        $correction = AttendanceCorrection::query()->firstOrFail();

        $this->actingAs($manager)->post(route('attendance.corrections.decide', $correction), [
            'decision' => 'reject',
            'decision_note' => 'No evidence for this change.',
        ])->assertRedirect();

        $this->assertSame(AttendanceCorrection::STATUS_REJECTED, $correction->fresh()->status);
        $this->assertSame('09:30', $record->fresh()->in_time->format('H:i'));
    }

    public function test_an_employee_cannot_decide_their_own_correction(): void
    {
        $user = $this->user();
        $record = $this->record($user);

        $this->actingAs($user)->post(route('attendance.corrections.store', $record), [
            'proposed_in_at' => now()->setTime(8, 0)->format('Y-m-d H:i'),
            'reason' => 'Trying to self-approve.',
        ]);

        $correction = AttendanceCorrection::query()->firstOrFail();

        $this->actingAs($user)->post(route('attendance.corrections.decide', $correction), [
            'decision' => 'approve',
        ])->assertForbidden();
    }

    private function record(User $user): AttendanceRecord
    {
        return AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'department_id' => $user->department_id,
            'work_date' => now()->toDateString(),
            'in_time' => now()->setTime(9, 30),
            'status' => AttendanceRecord::STATUS_CLOCKED_IN,
        ]);
    }

    private function user(string $name = 'Attendance Person', string $email = 'attendance.person@example.com'): User
    {
        $department = Department::query()->firstOrCreate(
            ['slug' => Str::slug('MIS Department')],
            ['name' => 'MIS Department', 'is_active' => true],
        );

        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'role' => User::ROLE_DEPARTMENT_USER,
                'level' => User::LEVEL_OFFICER,
                'department_id' => $department->id,
                'is_active' => true,
            ],
        );
    }

    private function manager(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'attendance.manager@example.com'],
            [
                'name' => 'Attendance Manager',
                'password' => 'password',
                'role' => User::ROLE_BUSINESS_ADMIN,
                'is_active' => true,
            ],
        );
    }
}
