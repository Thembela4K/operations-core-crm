<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Quotation;
use Carbon\CarbonInterface;

class ScoringService
{
    public function projectHealth(Project $project): float
    {
        $ratingScore = (float) $project->rating * 20;
        $progressScore = (float) $project->progress_percent;
        $riskPenalty = ['Low' => 0, 'Medium' => 10, 'High' => 25][$project->risk] ?? 10;
        $priorityBoost = ['Low' => 0, 'Medium' => 2, 'High' => 4, 'Critical' => 6][$project->priority] ?? 0;
        $deadlinePenalty = 0;

        if ($project->status !== 'Completed') {
            $daysLeft = (int) now()->startOfDay()->diffInDays($project->deadline, false);
            if ($daysLeft < 0) {
                $deadlinePenalty = 25;
            } elseif ($daysLeft <= 7) {
                $deadlinePenalty = 15;
            } elseif ($daysLeft <= 14) {
                $deadlinePenalty = 8;
            }
        }

        $score = ($ratingScore * 0.45) + ($progressScore * 0.35) + $priorityBoost - $riskPenalty - $deadlinePenalty;

        return round(max(0, min(100, $score)), 1);
    }

    public function quotationScore(Quotation $quotation): float
    {
        $ratingScore = (float) $quotation->rating * 20;
        $winScore = (float) $quotation->win_probability_percent;
        $quotedAmount = (float) $quotation->quoted_amount;
        $expectedCost = (float) $quotation->expected_cost;
        $marginPercent = $quotedAmount > 0 ? (($quotedAmount - $expectedCost) / $quotedAmount) * 100 : 0;
        $marginScore = max(0, min(100, $marginPercent * 2));
        $riskPenalty = ['Low' => 0, 'Medium' => 10, 'High' => 25][$quotation->risk] ?? 10;
        $priorityBoost = ['Low' => 0, 'Medium' => 2, 'High' => 4, 'Critical' => 6][$quotation->priority] ?? 0;
        $statusAdjustment = [
            'Draft' => 0,
            'Sent' => 3,
            'Under Review' => 5,
            'Accepted' => 15,
            'Rejected' => -30,
            'Expired' => -35,
        ][$quotation->status] ?? 0;
        $expiryPenalty = 0;

        if (! in_array($quotation->status, ['Accepted', 'Rejected', 'Expired'], true)) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($quotation->valid_until, false);
            if ($daysLeft < 0) {
                $expiryPenalty = 30;
            } elseif ($daysLeft <= 7) {
                $expiryPenalty = 15;
            } elseif ($daysLeft <= 14) {
                $expiryPenalty = 8;
            }
        }

        $score = ($ratingScore * 0.3)
            + ($winScore * 0.35)
            + ($marginScore * 0.2)
            + $priorityBoost
            + $statusAdjustment
            - $riskPenalty
            - $expiryPenalty;

        return round(max(0, min(100, $score)), 1);
    }

    public function urgency(?CarbonInterface $dueDate, bool $closed): string
    {
        if ($closed || $dueDate === null) {
            return 'Closed';
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($dueDate, false);

        return match (true) {
            $daysLeft < 0 => 'Overdue',
            $daysLeft <= 3 => 'Due Soon',
            $daysLeft <= 14 => 'Upcoming',
            default => 'On Track',
        };
    }
}
