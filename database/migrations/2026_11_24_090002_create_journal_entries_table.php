<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 30)->unique();
            $table->date('entry_date');
            $table->text('memo')->nullable();

            // The document that caused this entry. Unique per source, so a
            // document can never post twice.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('status', 20)->default('posted'); // posted | reversed
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'uq_journal_source');
            $table->index('entry_date');
            $table->index('status');

            $table->foreign('posted_by', 'fk_journal_posted_by')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'fk_journal_created_by')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('reversed_by_id', 'fk_journal_reversed_by')
                ->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
