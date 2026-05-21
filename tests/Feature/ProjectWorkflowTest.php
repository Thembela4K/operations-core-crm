<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_project(): void
    {
        $manager = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => 'password',
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->post(route('projects.store'), [
            'project_code' => 'PRJ-500',
            'name' => 'New Implementation',
            'owner' => 'Owner',
            'owner_email' => 'owner@example.com',
            'status' => 'Not Started',
            'priority' => 'High',
            'rating' => 4,
            'risk' => 'Medium',
            'progress_percent' => 0,
            'budget' => 15000,
            'start_date' => now()->toDateString(),
            'deadline' => now()->addMonth()->toDateString(),
            'notes' => 'Kickoff pending.',
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'project_code' => 'PRJ-500',
            'name' => 'New Implementation',
        ]);
    }
}
