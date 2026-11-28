<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personal information staff maintain about themselves: contact details,
     * addresses, emergency contact and banking information.
     *
     * Employees see and edit their own record; the business admin and directors
     * can see and maintain everyone's.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique();

            $table->string('phone', 40)->nullable();
            $table->string('alternate_phone', 40)->nullable();
            $table->string('personal_email')->nullable();
            $table->string('national_id', 60)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();
            $table->string('nationality', 60)->nullable();
            $table->string('marital_status', 30)->nullable();

            $table->text('residential_address')->nullable();
            $table->text('postal_address')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('region', 80)->nullable();

            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->string('emergency_contact_relationship', 60)->nullable();

            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account_name', 120)->nullable();
            $table->string('bank_account_number', 60)->nullable();
            $table->string('bank_branch', 120)->nullable();
            $table->string('bank_branch_code', 30)->nullable();
            $table->string('bank_account_type', 40)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'fk_user_profiles_user')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
