<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly Report & Plan: each employee writes up how far last week's tasks
     * got, what they are doing this week (including carry-overs), and who else
     * contributed. Reviewers read the same report.
     */
    public function up(): void
    {
        Schema::create('week_plan_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('department_id')->nullable();
            $table->date('week_start');
            $table->date('week_end');
            $table->text('last_week_progress')->nullable();
            $table->text('this_week_plan')->nullable();
            $table->string('status')->default('Draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'week_start'], 'uq_week_report_owner_week');
            $table->foreign('user_id', 'fk_week_reports_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('department_id', 'fk_week_reports_department')
                ->references('id')->on('departments')->nullOnDelete();
        });

        Schema::create('week_plan_report_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('week_plan_report_id');
            $table->foreignId('task_id')->nullable();
            $table->string('title');
            $table->string('section', 20)->default('last_week'); // last_week | this_week
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->boolean('carried_over')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();

            $table->foreign('week_plan_report_id', 'fk_week_report_tasks_report')
                ->references('id')->on('week_plan_reports')->cascadeOnDelete();
            $table->foreign('task_id', 'fk_week_report_tasks_task')
                ->references('id')->on('crm_tasks')->nullOnDelete();
        });

        Schema::create('week_plan_report_contributors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('week_plan_report_id');
            $table->foreignId('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('organisation')->nullable();
            $table->string('contribution')->nullable();
            $table->timestamps();

            $table->foreign('week_plan_report_id', 'fk_week_report_contrib_report')
                ->references('id')->on('week_plan_reports')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_week_report_contrib_user')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week_plan_report_contributors');
        Schema::dropIfExists('week_plan_report_tasks');
        Schema::dropIfExists('week_plan_reports');
    }
};
