<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Quotation;
use App\Models\TenderProposal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed non-sensitive starter records only.
     */
    public function run(): void
    {
        $departments = collect([
            'IT Department',
            'MIS Department',
            'GIS Department',
            'Admin (Reception)',
            'Directors',
        ])->mapWithKeys(function (string $name): array {
            return [
                $name => Department::query()->updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'name' => $name,
                        'email' => null,
                        'is_active' => true,
                    ],
                ),
            ];
        });

        if (env('ADMIN_EMAIL') && env('ADMIN_PASSWORD')) {
            User::query()->updateOrCreate(
                ['email' => env('ADMIN_EMAIL')],
                [
                    'department_id' => $departments['Admin (Reception)']->id,
                    'name' => env('ADMIN_NAME', 'System Administrator'),
                    'password' => env('ADMIN_PASSWORD'),
                    'role' => User::ROLE_SUPER_ADMIN,
                    'is_active' => true,
                ],
            );
        }

        TenderProposal::query()->firstOrCreate(
            ['tender_reference' => 'TDR-001'],
            [
                'title' => 'Sample Tender Proposal',
                'owner' => 'Tender Owner',
                'owner_email' => null,
                'status' => 'In Progress',
                'priority' => 'High',
                'rating' => 0,
                'risk' => 'Medium',
                'progress_percent' => 0,
                'budget' => 0,
                'received_date' => now()->subDays(45)->toDateString(),
                'closing_date' => now()->addDays(28)->toDateString(),
                'brief' => 'Replace this sample tender proposal with your organization data.',
            ],
        );

        Quotation::query()->firstOrCreate(
            ['quotation_code' => 'QTN-001'],
            [
                'client' => 'Sample Client',
                'opportunity' => 'Sample Opportunity',
                'owner' => 'Quotation Owner',
                'owner_email' => null,
                'status' => 'Sent',
                'priority' => 'High',
                'rating' => 0,
                'risk' => 'Medium',
                'win_probability_percent' => 68,
                'quoted_amount' => 28500,
                'expected_cost' => 19000,
                'issue_date' => now()->subDays(9)->toDateString(),
                'valid_until' => now()->addDays(18)->toDateString(),
                'notes' => 'Replace this sample quotation with your organization data.',
            ],
        );
    }
}
