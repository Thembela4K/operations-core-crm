<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TenderProposalController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('role:'.User::ROLE_SUPER_ADMIN.','.User::ROLE_MANAGER)->group(function (): void {
        Route::get('tender-proposals/create', [TenderProposalController::class, 'create'])->name('tender-proposals.create');
        Route::post('tender-proposals', [TenderProposalController::class, 'store'])->name('tender-proposals.store');
        Route::delete('tender-proposals/{tender_proposal}', [TenderProposalController::class, 'destroy'])->name('tender-proposals.destroy');

        Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');

        Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::post('assignments', [AssignmentController::class, 'store'])->name('assignments.store');

        Route::post('reminders/send-due', [ReminderController::class, 'sendDue'])->name('reminders.send-due');
    });

    Route::resource('tender-proposals', TenderProposalController::class)->except(['create', 'store', 'destroy']);
    Route::resource('quotations', QuotationController::class)->except(['create', 'store', 'destroy']);

    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');

    Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');

    Route::middleware('role:'.User::ROLE_SUPER_ADMIN)->group(function (): void {
        Route::resource('departments', DepartmentController::class);
        Route::resource('users', UserController::class);
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
