<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public holidays drive the working-day calculation for leave.
     *
     * Only fixed-date holidays and Easter-derived holidays are seeded here
     * because they are deterministic. Eswatini also observes variable holidays
     * proclaimed annually (Incwala, Umhlanga); add those through the admin
     * "Public holidays" panel once the dates are gazetted for the year.
     */
    public function up(): void
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $holidays = [
            // 2026 — Easter Sunday falls on 5 April 2026.
            ['2026-01-01', "New Year's Day"],
            ['2026-04-03', 'Good Friday'],
            ['2026-04-06', 'Easter Monday'],
            ['2026-04-19', "King's Birthday"],
            ['2026-04-25', 'National Flag Day'],
            ['2026-05-01', "Workers' Day"],
            ['2026-05-14', 'Ascension Day'],
            ['2026-07-22', "King Father's Birthday"],
            ['2026-09-06', 'Somhlolo Day (Independence)'],
            ['2026-12-25', 'Christmas Day'],
            ['2026-12-26', 'Boxing Day'],

            // 2027 — Easter Sunday falls on 28 March 2027.
            ['2027-01-01', "New Year's Day"],
            ['2027-03-26', 'Good Friday'],
            ['2027-03-29', 'Easter Monday'],
            ['2027-04-19', "King's Birthday"],
            ['2027-04-25', 'National Flag Day'],
            ['2027-05-01', "Workers' Day"],
            ['2027-05-06', 'Ascension Day'],
            ['2027-07-22', "King Father's Birthday"],
            ['2027-09-06', 'Somhlolo Day (Independence)'],
            ['2027-12-25', 'Christmas Day'],
            ['2027-12-26', 'Boxing Day'],
        ];

        $now = now();

        foreach ($holidays as [$date, $name]) {
            DB::table('public_holidays')->insertOrIgnore([
                'holiday_date' => $date,
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
    }
};
