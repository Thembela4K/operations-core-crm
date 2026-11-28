<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assignments become first-class work: they can stand alone (a user assigns
     * work to themselves), they can be meetings with a date and time, and every
     * change is kept in an event trail.
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('assignable_type')->nullable()->change();
            $table->unsignedBigInteger('assignable_id')->nullable()->change();

            $table->string('title')->nullable()->after('assignable_id');
            $table->string('kind', 20)->default('work')->after('title');
            $table->timestamp('starts_at')->nullable()->after('due_date');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->string('location')->nullable()->after('ends_at');
            $table->timestamp('accepted_at')->nullable()->after('assigned_at');
        });

        Schema::create('assignment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id');
            $table->string('action', 40);
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('assignment_id', 'fk_assignment_events_assignment')
                ->references('id')->on('assignments')->cascadeOnDelete();
            $table->foreign('actor_id', 'fk_assignment_events_actor')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_events');

        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn(['title', 'kind', 'starts_at', 'ends_at', 'location', 'accepted_at']);
        });
    }
};
