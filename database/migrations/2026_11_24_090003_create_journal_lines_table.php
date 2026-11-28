<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');

            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);

            $table->text('description')->nullable();

            // Subledger link: which client or supplier this line belongs to.
            // This is what makes aged debtors/creditors possible.
            $table->string('contact_type')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();

            $table->timestamps();

            $table->index('account_id');
            $table->index(['contact_type', 'contact_id']);

            $table->foreign('journal_entry_id', 'fk_journal_lines_entry')
                ->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('account_id', 'fk_journal_lines_account')
                ->references('id')->on('accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
