<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report 2.2 / 3.2 classification rule: ESPPRA is a *source* of notices, not
     * a procurement classification. Each opportunity therefore records where it
     * was found and what kind of notice it actually is.
     */
    public function up(): void
    {
        Schema::table('tender_proposals', function (Blueprint $table): void {
            $table->string('procurement_type', 60)->nullable()->after('title');
            $table->string('notice_source', 60)->nullable()->after('procurement_type');
        });
    }

    public function down(): void
    {
        Schema::table('tender_proposals', function (Blueprint $table): void {
            $table->dropColumn(['procurement_type', 'notice_source']);
        });
    }
};
