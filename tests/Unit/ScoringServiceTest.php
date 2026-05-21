<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Quotation;
use App\Services\ScoringService;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    public function test_project_health_score_matches_expected_formula(): void
    {
        $project = new Project([
            'rating' => 4,
            'progress_percent' => 50,
            'risk' => 'Medium',
            'priority' => 'High',
            'status' => 'In Progress',
            'deadline' => now()->addDays(20),
        ]);

        $this->assertSame(47.5, app(ScoringService::class)->projectHealth($project));
    }

    public function test_quotation_score_matches_expected_formula(): void
    {
        $quotation = new Quotation([
            'rating' => 4,
            'win_probability_percent' => 60,
            'quoted_amount' => 1000,
            'expected_cost' => 700,
            'risk' => 'Medium',
            'priority' => 'High',
            'status' => 'Sent',
            'valid_until' => now()->addDays(20),
        ]);

        $this->assertSame(54.0, app(ScoringService::class)->quotationScore($quotation));
    }
}
