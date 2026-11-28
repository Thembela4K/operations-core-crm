<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The registry is split in two:
     *
     *  - attachments: files that belong to a record (requisition, ticket, task…)
     *  - company documents: trading licences, registrations, operational
     *    documents that stand on their own and are readable company-wide.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('is_company_document')->default(false)->after('is_generated');
            $table->index(['is_company_document', 'created_at'], 'idx_documents_registry_split');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('idx_documents_registry_split');
            $table->dropColumn('is_company_document');
        });
    }
};
