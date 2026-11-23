<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\LeaveEntitlement;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_working_days_exclude_weekends(): void
    {
        $service = app(LeaveService::class);

        // Friday 6 March 2026 through Monday 9 March 2026 => Fri + Mon only.
        $this->assertSame(2.0, $service->workingDays(
            Carbon::parse('2026-03-06'),
            Carbon::parse('2026-03-09'),
        ));

        // Monday to Friday of the same week => 5.
        $this->assertSame(5.0, $service->workingDays(
            Carbon::parse('2026-03-02'),
            Carbon::parse('2026-03-06'),
        ));
    }

    public function test_working_days_exclude_public_holidays(): void
    {
        PublicHoliday::query()->create([
            'holiday_date' => '2026-03-04',
            'name' => 'Test Holiday',
            'is_active' => true,
        ]);

        $service = app(LeaveService::class);

        $this->assertSame(4.0, $service->workingDays(
            Carbon::parse('2026-03-02'),
            Carbon::parse('2026-03-06'),
        ));
    }

    public function test_next_working_day_skips_weekend_and_holiday(): void
    {
        PublicHoliday::query()->create([
            'holiday_date' => '2026-03-09',
            'name' => 'Test Monday Holiday',
            'is_active' => true,
        ]);

        $service = app(LeaveService::class);

        // Friday -> Saturday/Sunday skipped, Monday is a holiday, so Tuesday.
        $this->assertSame(
            '2026-03-10',
            $service->nextWorkingDay(Carbon::parse('2026-03-06'))->toDateString(),
        );
    }

    public function test_balance_defaults_to_twelve_working_days(): void
    {
        $user = $this->user('Annual User', 'annual@example.com', User::ROLE_DEPARTMENT_USER);

        $balance = app(LeaveService::class)->balanceFor($user, 2026);

        $this->assertSame(12.0, $balance['entitlement']);
        $this->assertSame(12.0, $balance['remaining']);
        $this->assertSame(0.0, $balance['taken']);
    }

    public function test_employee_can_apply_and_balance_only_moves_on_approval(): void
    {
        $department = $this->department('MIS Department');
        $employee = $this->user('Leave User', 'leave@example.com', User::ROLE_DEPARTMENT_USER, $department);
        $director = $this->user('Director', 'director@example.com', User::ROLE_DIRECTOR);

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-04',
            'reason' => 'Family trip',
        ])->assertRedirect();

        $leave = LeaveApplication::query()->firstOrFail();

        $this->assertSame(LeaveApplication::STATUS_PENDING, $leave->status);
        $this->assertSame(3.0, $leave->working_days);
        $this->assertSame('2026-03-05', $leave->back_at_work_on->toDateString());

        $pendingBalance = app(LeaveService::class)->balanceFor($employee, 2026);
        $this->assertSame(0.0, $pendingBalance['taken']);
        $this->assertSame(3.0, $pendingBalance['pending']);
        $this->assertSame(9.0, $pendingBalance['available']);

        $this->actingAs($director)
            ->post(route('leave.approve', $leave), ['decision_note' => 'Enjoy'])
            ->assertRedirect();

        $approvedBalance = app(LeaveService::class)->balanceFor($employee, 2026);
        $this->assertSame(3.0, $approvedBalance['taken']);
        $this->assertSame(9.0, $approvedBalance['remaining']);
        $this->assertSame(LeaveApplication::STATUS_APPROVED, $leave->fresh()->status);
        $this->assertSame($director->id, $leave->fresh()->decided_by);
    }

    public function test_rejection_does_not_deduct_balance(): void
    {
        $employee = $this->user('Leave User', 'leave@example.com', User::ROLE_DEPARTMENT_USER);
        $director = $this->user('Director', 'director@example.com', User::ROLE_DIRECTOR);

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-06',
        ])->assertRedirect();

        $leave = LeaveApplication::query()->firstOrFail();

        $this->actingAs($director)
            ->post(route('leave.reject', $leave), ['decision_note' => 'Peak period'])
            ->assertRedirect();

        $balance = app(LeaveService::class)->balanceFor($employee, 2026);

        $this->assertSame(0.0, $balance['taken']);
        $this->assertSame(12.0, $balance['remaining']);
        $this->assertSame(LeaveApplication::STATUS_REJECTED, $leave->fresh()->status);
    }

    public function test_cannot_apply_for_more_than_remaining_balance(): void
    {
        $employee = $this->user('Leave User', 'leave@example.com', User::ROLE_DEPARTMENT_USER);

        // 2 Mar - 18 Mar 2026 contains 13 working days.
        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-18',
        ])->assertSessionHasErrors('from_date');

        $this->assertDatabaseCount('leave_applications', 0);
    }

    public function test_pending_leave_reduces_available_days(): void
    {
        $employee = $this->user('Leave User', 'leave@example.com', User::ROLE_DEPARTMENT_USER);

        // Uses the whole 12-day allowance.
        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-17',
        ])->assertRedirect();

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-04-20',
            'to_date' => '2026-04-20',
        ])->assertSessionHasErrors('from_date');

        $this->assertDatabaseCount('leave_applications', 1);
    }

    public function test_overlapping_leave_is_rejected(): void
    {
        $employee = $this->user('Leave User', 'leave@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-06',
        ])->assertRedirect();

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_SICK,
            'from_date' => '2026-03-04',
            'to_date' => '2026-03-10',
        ])->assertSessionHasErrors('from_date');

        $this->assertDatabaseCount('leave_applications', 1);
    }

    public function test_non_approver_cannot_approve_leave(): void
    {
        $employee = $this->user('Leave User', 'leave@example.com', User::ROLE_DEPARTMENT_USER);
        $colleague = $this->user('Colleague', 'colleague@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-03',
        ])->assertRedirect();

        $leave = LeaveApplication::query()->firstOrFail();

        $this->actingAs($colleague)
            ->post(route('leave.approve', $leave))
            ->assertForbidden();

        $this->assertSame(LeaveApplication::STATUS_PENDING, $leave->fresh()->status);
    }

    public function test_approver_cannot_approve_their_own_leave(): void
    {
        $director = $this->user('Director', 'director@example.com', User::ROLE_DIRECTOR);

        $this->actingAs($director)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-03',
        ])->assertRedirect();

        $leave = LeaveApplication::query()->firstOrFail();

        $this->actingAs($director)
            ->post(route('leave.approve', $leave), ['decision_note' => 'Self'])
            ->assertSessionHasErrors('status');

        $this->assertSame(LeaveApplication::STATUS_PENDING, $leave->fresh()->status);
    }

    public function test_employee_only_sees_their_own_leave_in_the_register(): void
    {
        $one = $this->user('One User', 'one@example.com', User::ROLE_DEPARTMENT_USER);
        $two = $this->user('Two User', 'two@example.com', User::ROLE_DEPARTMENT_USER);
        $director = $this->user('Director', 'director@example.com', User::ROLE_DIRECTOR);

        $this->actingAs($one)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-03',
        ])->assertRedirect();

        $leave = LeaveApplication::query()->firstOrFail();

        $this->actingAs($two)->get(route('leave.show', $leave))->assertForbidden();

        $this->actingAs($director)->get(route('leave.show', $leave))->assertOk();
        $this->actingAs($one)->get(route('leave.show', $leave))->assertOk();
    }

    public function test_printable_form_and_pdf_render(): void
    {
        $employee = $this->user('Leave User', 'leave@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => LeaveApplication::TYPE_ANNUAL,
            'from_date' => '2026-03-02',
            'to_date' => '2026-03-04',
        ])->assertRedirect();

        $leave = LeaveApplication::query()->firstOrFail();

        $this->actingAs($employee)
            ->get(route('leave.print', $leave))
            ->assertOk()
            ->assertSee('LEAVE APPLICATION')
            ->assertSee('Datamatics Eswatini')
            ->assertSee('For payroll use only', false)
            ->assertSee('DATF025');

        $response = $this->actingAs($employee)->get(route('leave.pdf', $leave));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_entitlement_is_twelve_and_year_specific(): void
    {
        $user = $this->user('Annual User', 'annual@example.com', User::ROLE_DEPARTMENT_USER);

        $entitlement = LeaveEntitlement::forUser($user, 2026);

        $this->assertSame(12.0, $entitlement->totalDays());
        $this->assertSame(2026, $entitlement->year);
        $this->assertDatabaseHas('leave_entitlements', ['user_id' => $user->id, 'year' => 2026]);
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function user(string $name, string $email, string $role, ?Department $department = null): User
    {
        return User::query()->create([
            'department_id' => $department?->id,
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
