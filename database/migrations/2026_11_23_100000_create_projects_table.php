<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee-owned projects, presented in the same shape as the company
     * weekly Report & Plan: Activity | Status | % | Comment, plus the plan
     * columns Date | By Whom | Time Frame.
     *
     * Foreign key names are explicit on purpose. The original `projects` table
     * was renamed to `tender_proposals` during the tender workflow upgrade, and
     * InnoDB keeps constraint names database-wide — so `projects_created_by_foreign`
     * still exists on tender_proposals and reusing it fails with errno 121.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status')->default('Not Started');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->text('comment')->nullable();

            $table->date('planned_date')->nullable();
            $table->string('by_whom')->nullable();
            $table->string('time_frame')->nullable();

            $table->date('started_on')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('department_id');

            $table->foreign('user_id', 'fk_projects_owner')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('department_id', 'fk_projects_department')
                ->references('id')->on('departments')->nullOnDelete();
            $table->foreign('created_by', 'fk_projects_creator')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
