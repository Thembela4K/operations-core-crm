<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceType;
use App\Models\Department;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\AttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Attendance expansion (report section 5): types with Office as default,
 * append-only events, and the scheduled clock-out reminder time.
 */
class AttendanceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AttendanceSeeder::class);
    }

    public function test_clock_in_records_the_selected_type_and_an_event(): void
    {
        $user = $this->user();
        $fieldwork = AttendanceType::query()->where('name', 'Fieldwork')->firstOrFail();

        $record = app(AttendanceService::class)->clockIn($user, null, $fieldwork->id);

        $this->assertSame($fieldwork->id, $record->attendance_type_id);
        $this->assertSame(AttendanceRecord::STATUS_CLOCKED_IN, $record->status);

        $event = AttendanceEvent::query()->firstOrFail();
        $this->assertSame(AttendanceEvent::TYPE_CLOCK_IN, $event->event_type);
        $this->assertSame('Fieldwork', $event->attendance_type);
    }

    public function test_office_is_the_default_type_when_none_is_chosen(): void
    {
        $user = $this->user();

        $record = app(AttendanceService::class)->clockIn($user);

        $this->assertSame(AttendanceType::default()?->id, $record->attendance_type_id);
        $this->assertSame('Office', $record->attendanceType?->name);
    }

    public function test_only_one_default_type_exists(): void
    {
        $this->assertSame(1, AttendanceType::query()->where('is_default', true)->count());
        $this->assertSame('Office', AttendanceType::query()->where('is_default', true)->value('name'));
    }

    public function test_clock_out_appends_an_event_without_losing_the_clock_in(): void
    {
        $user = $this->user();
        $service = app(AttendanceService::class);

        $record = $service->clockIn($user);
        $service->clockOut($record, null, $user);

        $events = AttendanceEvent::query()->orderBy('id')->pluck('event_type')->all();

        $this->assertSame([AttendanceEvent::TYPE_CLOCK_IN, AttendanceEvent::TYPE_CLOCK_OUT], $events);
        $this->assertNotNull($record->fresh()->in_time);
        $this->assertNotNull($record->fresh()->out_time);
    }

    public function test_a_correction_is_an_extra_audited_event(): void
    {
        $user = $this->user();
        $admin = $this->user('Administrator', 'admin@example.com');
        $service = app(AttendanceService::class);

        $record = $service->clockIn($user);
        $service->clockOut($record, null, $user);

        $service->correct(
            $record->fresh(),
            now()->setTime(8, 0),
            now()->setTime(17, 0),
            'Forgot to clock in on time',
            $admin,
        );

        $this->assertSame(
            [AttendanceEvent::TYPE_CLOCK_IN, AttendanceEvent::TYPE_CLOCK_OUT, AttendanceEvent::TYPE_CORRECTION],
            AttendanceEvent::query()->orderBy('id')->pluck('event_type')->all(),
        );
    }

    public function test_the_clock_out_reminder_is_ten_minutes_before_the_scheduled_end(): void
    {
        // Wednesday: 17:00 end, reminder 16:50.
        $wednesday = now()->startOfWeek()->addDays(2);
        $schedule = AttendanceSchedule::forDate($wednesday);

        $this->assertNotNull($schedule);
        $this->assertSame('16:50', $schedule->reminderAt($wednesday)->format('H:i'));

        // Friday: 16:00 end, reminder 15:50.
        $friday = now()->startOfWeek()->addDays(4);
        $this->assertSame('15:50', AttendanceSchedule::forDate($friday)->reminderAt($friday)->format('H:i'));
    }

    public function test_weekends_are_not_working_days(): void
    {
        $saturday = now()->startOfWeek()->addDays(5);

        $this->assertFalse(AttendanceSchedule::forDate($saturday)->is_working_day);
        $this->assertNull(AttendanceSchedule::forDate($saturday)->reminderAt($saturday));
    }

    protected function user(string $name = 'Field Officer', string $email = 'officer@example.com'): User
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
}
