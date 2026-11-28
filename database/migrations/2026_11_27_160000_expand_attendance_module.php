<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance module (report section 5).
     *
     * Types describe where someone worked, events are an append-only record of
     * what actually happened, corrections are separate audited requests, and
     * schedules define the expected working day used for reminders.
     */
    public function up(): void
    {
        Schema::create('attendance_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('weekday'); // 1 = Monday ... 7 = Sunday
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_working_day')->default(true);
            $table->timestamps();

            $table->unique('weekday', 'uq_attendance_schedule_weekday');
        });

        Schema::create('attendance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_record_id');
            $table->foreignId('user_id')->nullable();
            $table->string('event_type');        // clock_in, clock_out, type_changed, correction
            $table->string('attendance_type')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('actor_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('attendance_record_id', 'fk_attendance_events_record')
                ->references('id')->on('attendance_records')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_attendance_events_user')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('actor_id', 'fk_attendance_events_actor')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_record_id');
            $table->foreignId('requested_by')->nullable();
            $table->timestamp('original_in_at')->nullable();
            $table->timestamp('original_out_at')->nullable();
            $table->timestamp('proposed_in_at')->nullable();
            $table->timestamp('proposed_out_at')->nullable();
            $table->string('reason');
            $table->string('status')->default('Pending');
            $table->foreignId('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();

            $table->foreign('attendance_record_id', 'fk_attendance_corrections_record')
                ->references('id')->on('attendance_records')->cascadeOnDelete();
            $table->foreign('requested_by', 'fk_attendance_corrections_requester')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('decided_by', 'fk_attendance_corrections_decider')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreignId('attendance_type_id')->nullable()->after('department_id');
            $table->foreign('attendance_type_id', 'fk_attendance_records_type')
                ->references('id')->on('attendance_types')->nullOnDelete();
        });

        // First-login attendance prompt state, per employee per local workday.
        Schema::table('users', function (Blueprint $table): void {
            $table->date('attendance_prompted_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('attendance_prompted_on');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropForeign('fk_attendance_records_type');
            $table->dropColumn('attendance_type_id');
        });

        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_schedules');
        Schema::dropIfExists('attendance_types');
    }
};
