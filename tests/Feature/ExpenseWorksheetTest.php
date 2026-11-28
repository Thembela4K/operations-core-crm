<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Expenses are a worksheet of priced lines with a duration, and money released
 * for a requisition becomes an expense.
 */
class ExpenseWorksheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_worksheet_line_multiplies_by_its_duration(): void
    {
        $admin = $this->user('Admin', 'admin.expense@example.com', User::ROLE_BUSINESS_ADMIN);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'category' => 'Labour',
            'expense_type' => 'Labour',
            'payee' => 'Datamatics Staff',
            'expense_date' => now()->toDateString(),
            'status' => 'Recorded',
            'vat_amount' => 0,
            'amount' => 0,
            'items' => [
                [
                    'description' => 'Datamatics labour — network engineer',
                    'item_type' => 'Labour',
                    'quantity' => 2,
                    'unit_cost' => 1000,
                    'duration_months' => 3,
                ],
                [
                    'description' => 'Internet — fibre line',
                    'item_type' => 'Service',
                    'quantity' => 1,
                    'unit_cost' => 500,
                    'duration_months' => 1,
                ],
            ],
        ])->assertRedirect();

        $expense = Expense::query()->firstOrFail();

        // 2 x 1000 x 3 = 6000, plus 1 x 500 x 1 = 500.
        $this->assertSame(6500.00, (float) $expense->amount);
        $this->assertSame(6500.00, (float) $expense->total_amount);
        $this->assertCount(2, $expense->items()->get());

        $labour = $expense->items()->where('item_type', 'Labour')->firstOrFail();
        $this->assertSame(3, $labour->duration_months);
        $this->assertSame(6000.00, (float) $labour->line_total);
    }

    public function test_operational_expenses_such_as_rent_and_internet_can_be_recorded(): void
    {
        $this->assertContains('Rent', Expense::CATEGORIES);
        $this->assertContains('Internet & Telecom', Expense::CATEGORIES);
        $this->assertContains('Utilities', Expense::CATEGORIES);
    }

    public function test_released_requisition_funds_become_an_expense(): void
    {
        $department = $this->department('MIS Department');
        $director = $this->user('Director', 'director.expense@example.com', User::ROLE_DIRECTOR);
        $requester = $this->user('Requester', 'req.expense@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $requisition = Requisition::query()->create([
            'department_id' => $department->id,
            'requisition_number' => 'REQ-EXP-1',
            'addressed_to' => 'Directors',
            'title' => 'Replace switch',
            'category' => 'IT',
            'priority' => 'Medium',
            'status' => Requisition::STATUS_APPROVED,
            'requested_by' => $requester->id,
            'estimated_total' => 1200,
            'bank_total' => 1200,
            'cash_total' => 0,
            'other_total' => 0,
        ]);

        $requisition->items()->create([
            'position' => 1,
            'description' => 'Network switch',
            'payment_type' => 'Bank',
            'quantity' => 1,
            'estimated_unit_cost' => 1200,
            'estimated_total' => 1200,
        ]);

        $this->actingAs($director)
            ->post(route('requisitions.release-funds', $requisition))
            ->assertRedirect();

        $expense = Expense::query()->where('requisition_id', $requisition->id)->firstOrFail();

        $this->assertSame(1200.00, (float) $expense->total_amount);
        $this->assertSame('Hardware', $expense->category);
        $this->assertSame($director->id, $expense->recorded_by);
        $this->assertSame(1, $expense->items()->count());
        $this->assertSame(1200.00, (float) ExpenseItem::query()->first()->line_total);
    }

    private function department(string $name): Department
    {
        return Department::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function user(string $name, string $email, string $role, ?Department $department = null): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'department_id' => $department?->id,
            'is_active' => true,
        ]);
    }
}
