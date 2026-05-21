<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_valid_active_user(): void
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_department_user_only_sees_assigned_projects(): void
    {
        $it = $this->department('IT');
        $gis = $this->department('GIS');
        $user = User::query()->create([
            'department_id' => $it->id,
            'name' => 'IT User',
            'email' => 'it@example.com',
            'password' => 'password',
            'role' => User::ROLE_DEPARTMENT_USER,
            'is_active' => true,
        ]);

        $visible = $this->project('PRJ-100', 'Visible Project');
        $hidden = $this->project('PRJ-200', 'Hidden Project');
        $visible->assignments()->create([
            'department_id' => $it->id,
            'assignee_name' => 'IT User',
            'assignee_email' => 'it@example.com',
            'status' => 'Assigned',
            'assigned_at' => now(),
        ]);
        $hidden->assignments()->create([
            'department_id' => $gis->id,
            'assignee_name' => 'GIS User',
            'assignee_email' => 'gis@example.com',
            'status' => 'Assigned',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk()
            ->assertSee('Visible Project')
            ->assertDontSee('Hidden Project');
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function project(string $code, string $name): Project
    {
        return Project::query()->create([
            'project_code' => $code,
            'name' => $name,
            'owner' => 'Owner',
            'owner_email' => 'owner@example.com',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'rating' => 3,
            'risk' => 'Low',
            'progress_percent' => 50,
            'budget' => 1000,
            'start_date' => now()->subDay()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);
    }
}
