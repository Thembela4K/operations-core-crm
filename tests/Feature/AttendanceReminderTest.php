<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CrmNotification;
use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Services\AttendanceReminderService;
use App\Services\AttendanceService;
use Database\Seeders\AttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Attendance reminders and the one-time dashboard prompt (report 5.3 / 5.4).
 */
class AttendanceReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AttendanceSeeder::class);
    }

    public function test_the_dashboard_prompts_once_per_day(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Remember to record your arrival');

        // The second visit that day must not prompt again.
        $this->actingAs($user->fresh())->get('/')
            ->assertOk()
            ->assertDontSee('Remember to record your arrival');
    }

    public function test_approved_leave_suppresses_the_prompt(): void
    {
        $user = $this->user();

        LeaveApplication::query()->create([
            'user_id' => $user->id,
            'department_id' => $user->department_id,
            'leave_type' => 'Annual',
            'from_date' => now()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'working_days' => 3,
            'reason' => 'Family trip',
            'status' => LeaveApplication::STATUS_APPROVED,
            'leave_year' => now()->year,
            'applied_on' => now()->toDateString(),
        ]);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertDontSee('Remember to record your arrival');
    }

    public function test_a_checked_in_employee_is_not_prompted_or_reminded(): void
    {
        $user = $this->user();
        app(AttendanceService::class)->clockIn($user);

        $reminders = app(AttendanceReminderService::class);

        $this->assertTrue($reminders->hasArrivalToday($user));
        $this->assertCount(0, $reminders->morningReminderRecipients());
    }

    public function test_clock_out_reminders_only_apply_after_the_reminder_time(): void
    {
        $user = $this->user();

        // Wednesday of this week, with an arrival but no departure.
        $wednesday = now()->startOfWeek()->addDays(2)->setTime(8, 30);

        AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'department_id' => $user->department_id,
            'work_date' => $wednesday->toDateString(),
            'in_time' => $wednesday->copy()->setTime(8, 30),
            'status' => AttendanceRecord::STATUS_CLOCKED_IN,
        ]);

        $reminders = app(AttendanceReminderService::class);

        // Wednesday: reminder at 16:50.
        $beforeWindow = $wednesday->copy()->setTime(16, 0);
        $this->assertCount(0, $reminders->clockOutReminderRecipients($beforeWindow));

        $afterWindow = $wednesday->copy()->setTime(16, 55);
        $this->assertCount(1, $reminders->clockOutReminderRecipients($afterWindow));
    }

    public function test_clocked_out_employees_are_not_reminded(): void
    {
        $user = $this->user();
        $service = app(AttendanceService::class);

        $record = $service->clockIn($user);
        $service->clockOut($record, null, $user);

        $afterWindow = now()->startOfWeek()->addDays(2)->setTime(16, 55);

        $this->assertCount(0, app(AttendanceReminderService::class)->clockOutReminderRecipients($afterWindow));
    }

    public function test_the_command_sends_once_and_is_idempotent(): void
    {
        $user = $this->user();

        $this->artisan('attendance:remind --force')->assertSuccessful();

        $this->assertSame(1, CrmNotification::query()->where('user_id', $user->id)->where('type', 'attendance_clock_in')->count());

        // Running again the same day must not duplicate the nudge.
        $this->artisan('attendance:remind --force')->assertSuccessful();

        $this->assertSame(1, CrmNotification::query()->where('user_id', $user->id)->where('type', 'attendance_clock_in')->count());
    }

    protected function user(): User
    {
        $department = Department::query()->firstOrCreate(
            ['slug' => Str::slug('MIS Department')],
            ['name' => 'MIS Department', 'is_active' => true],
        );

        return User::query()->create([
            'name' => 'Attendance Person',
            'email' => 'attendance.person@example.com',
            'password' => 'password',
            'role' => User::ROLE_DEPARTMENT_USER,
            'level' => User::LEVEL_OFFICER,
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }
}
