<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: the resource show route for `tender-proposals`/`quotations` was
 * registered before the role-gated /create routes, so "create" was matched as a
 * record id and returned 404. The id parameters are now numeric-constrained.
 */
class OperationsCreateRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_open_the_create_pages(): void
    {
        $admin = User::query()->create([
            'name' => 'Route Admin',
            'email' => 'route.admin@example.com',
            'password' => 'password',
            'role' => User::ROLE_BUSINESS_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/tender-proposals/create')->assertOk();
        $this->actingAs($admin)->get('/quotations/create')->assertOk();
    }

    public function test_reception_can_open_the_create_pages(): void
    {
        $reception = User::query()->create([
            'name' => 'Front Desk',
            'email' => 'frontdesk@example.com',
            'password' => 'password',
            'role' => User::ROLE_RECEPTION,
            'is_active' => true,
        ]);

        $this->actingAs($reception)->get('/tender-proposals/create')->assertOk();
        $this->actingAs($reception)->get('/quotations/create')->assertOk();
    }
}
