<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The plan half of a weekly report is a forecast, not a scorecard: each
     * planned item carries the dates it is expected to take, not a percentage.
     */
    public function up(): void
    {
        Schema::table('week_plan_report_tasks', function (Blueprint $table): void {
            $table->date('planned_start')->nullable()->after('progress_percent');
            $table->date('planned_end')->nullable()->after('planned_start');
        });
    }

    public function down(): void
    {
        Schema::table('week_plan_report_tasks', function (Blueprint $table): void {
            $table->dropColumn(['planned_start', 'planned_end']);
        });
    }
};
