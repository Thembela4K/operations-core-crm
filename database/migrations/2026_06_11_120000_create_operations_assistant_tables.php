<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 30);
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'created_at']);
        });

        Schema::create('ai_action_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('intent')->nullable();
            $table->string('action_type')->default('reply');
            $table->string('status')->default('completed');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('route')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['intent', 'status']);
        });

        Schema::create('document_texts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('indexed');
            $table->longText('content')->nullable();
            $table->unsignedInteger('char_count')->default(0);
            $table->timestamp('extracted_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'extracted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_texts');
        Schema::dropIfExists('ai_action_logs');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
