<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IT Service Desk (report section 4).
     *
     * Tickets belong to the employee who raised them; the IT Officer triages,
     * and a ticket may raise one or more requisitions.
     */
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('requester_id');
            $table->foreignId('department_id')->nullable();
            $table->foreignId('ticket_category_id')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->foreignId('created_by')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('Normal');
            $table->string('status')->default('Submitted');
            $table->text('resolution_note')->nullable();

            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('requester_id', 'fk_tickets_requester')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('department_id', 'fk_tickets_department')
                ->references('id')->on('departments')->nullOnDelete();
            $table->foreign('ticket_category_id', 'fk_tickets_category')
                ->references('id')->on('ticket_categories')->nullOnDelete();
            $table->foreign('assigned_to', 'fk_tickets_assignee')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'fk_tickets_creator')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['status', 'priority'], 'idx_tickets_queue');
        });

        Schema::create('ticket_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id');
            $table->foreignId('user_id')->nullable();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->foreign('ticket_id', 'fk_ticket_comments_ticket')
                ->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_ticket_comments_author')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ticket_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id', 'fk_ticket_status_ticket')
                ->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('actor_id', 'fk_ticket_status_actor')
                ->references('id')->on('users')->nullOnDelete();
        });

        // A requisition may come from a ticket; standalone requisitions stay valid.
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->foreignId('source_ticket_id')->nullable()->after('id');
            $table->foreign('source_ticket_id', 'fk_requisitions_source_ticket')
                ->references('id')->on('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->dropForeign('fk_requisitions_source_ticket');
            $table->dropColumn('source_ticket_id');
        });

        Schema::dropIfExists('ticket_status_history');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_categories');
    }
};
