<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Company documents have no parent record, so the polymorphic columns must
     * allow nulls. Record attachments keep both values.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('documentable_type')->nullable()->change();
            $table->unsignedBigInteger('documentable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Company documents cannot survive without the nullable columns.
        \Illuminate\Support\Facades\DB::table('documents')->where('is_company_document', true)->delete();

        Schema::table('documents', function (Blueprint $table): void {
            $table->string('documentable_type')->nullable(false)->change();
            $table->unsignedBigInteger('documentable_id')->nullable(false)->change();
        });
    }
};
