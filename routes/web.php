<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\ClientActivityController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JobCardController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficialDocumentController;
use App\Http\Controllers\OperationsAssistantController;
use App\Http\Controllers\PasswordResetOtpController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PurchaseRecordController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\SalesQuotationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenderProposalController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
    Route::get('forgot-password', [PasswordResetOtpController::class, 'requestForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetOtpController::class, 'sendOtp'])->name('password.email');
    Route::get('reset-password', [PasswordResetOtpController::class, 'resetForm'])->name('password.reset.form');
    Route::post('reset-password', [PasswordResetOtpController::class, 'reset'])->name('password.reset');
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
    Route::post('invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept.store');
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Personal modules — every active user
    |--------------------------------------------------------------------------
    | Everyone, including a trainee, logs their own work: tasks, projects,
    | attendance, leave and notifications. Read-only roles may still act on
    | their own personal records.
    */
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::resource('tasks', TaskController::class);
    Route::post('tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comments.store');
    Route::post('tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');

    Route::resource('projects', ProjectController::class);

    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::patch('attendance/{attendanceRecord}/correct', [AttendanceController::class, 'correct'])->name('attendance.correct');

    Route::get('attendance/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::get('attendance/leave/create', [LeaveController::class, 'create'])->name('leave.create');
    Route::post('attendance/leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::post('attendance/leave/holidays', [LeaveController::class, 'storeHoliday'])->name('leave.holidays.store');
    Route::delete('attendance/leave/holidays/{holiday}', [LeaveController::class, 'destroyHoliday'])->name('leave.holidays.destroy');
    Route::get('attendance/leave/{leave}', [LeaveController::class, 'show'])->name('leave.show');
    Route::get('attendance/leave/{leave}/print', [LeaveController::class, 'print'])->name('leave.print');
    Route::get('attendance/leave/{leave}/pdf', [LeaveController::class, 'pdf'])->name('leave.pdf');
    Route::get('attendance/leave/{leave}/attachment', [LeaveController::class, 'attachment'])->name('leave.attachment');
    Route::post('attendance/leave/{leave}/approve', [LeaveController::class, 'approve'])->name('leave.approve');
    Route::post('attendance/leave/{leave}/reject', [LeaveController::class, 'reject'])->name('leave.reject');
    Route::post('attendance/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::post('assistant/message', [OperationsAssistantController::class, 'message'])->name('assistant.message');
    Route::post('assistant/conversation', [OperationsAssistantController::class, 'conversation'])->name('assistant.conversation');
    Route::get('assistant/conversations', [OperationsAssistantController::class, 'conversations'])->name('assistant.conversations');
    Route::get('assistant/history', [OperationsAssistantController::class, 'history'])->name('assistant.history');

    /*
    |--------------------------------------------------------------------------
    | Clients — department staff see the register, reception administers it
    |--------------------------------------------------------------------------
    */
    Route::middleware('module:clients')->group(function (): void {
        Route::resource('clients', ClientController::class);
        Route::resource('client-activities', ClientActivityController::class)->except(['show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Catalog of priced items
    |--------------------------------------------------------------------------
    */
    Route::middleware('module:catalog')->group(function (): void {
        Route::resource('catalog-items', CatalogItemController::class)->except(['show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Operations — quotations, job cards, delivery notes, requisitions,
    | tenders and quotation requests
    |--------------------------------------------------------------------------
    */
    Route::middleware('module:operations')->group(function (): void {
        Route::resource('sales-quotations', SalesQuotationController::class);
        Route::post('sales-quotations/{sales_quotation}/submit', [SalesQuotationController::class, 'submit'])->name('sales-quotations.submit');
        Route::post('sales-quotations/{sales_quotation}/approve', [SalesQuotationController::class, 'approve'])->name('sales-quotations.approve');
        Route::post('sales-quotations/{sales_quotation}/reject', [SalesQuotationController::class, 'reject'])->name('sales-quotations.reject');
        Route::post('sales-quotations/{sales_quotation}/mark-sent', [SalesQuotationController::class, 'markSent'])->name('sales-quotations.mark-sent');
        Route::post('sales-quotations/{sales_quotation}/mark-accepted', [SalesQuotationController::class, 'markAccepted'])->name('sales-quotations.mark-accepted');
        Route::post('sales-quotations/{sales_quotation}/mark-declined', [SalesQuotationController::class, 'markDeclined'])->name('sales-quotations.mark-declined');
        Route::post('sales-quotations/{sales_quotation}/mark-expired', [SalesQuotationController::class, 'markExpired'])->name('sales-quotations.mark-expired');
        Route::post('sales-quotations/{sales_quotation}/convert-to-invoice', [SalesQuotationController::class, 'convertToInvoice'])->name('sales-quotations.convert-to-invoice');
        Route::get('sales-quotations/{sales_quotation}/print', [SalesQuotationController::class, 'print'])->name('sales-quotations.print');
        Route::get('sales-quotations/{sales_quotation}/pdf', [OfficialDocumentController::class, 'salesQuotation'])->name('sales-quotations.pdf');

        Route::resource('job-cards', JobCardController::class);
        Route::post('job-cards/{job_card}/in-progress', [JobCardController::class, 'markInProgress'])->name('job-cards.in-progress');
        Route::post('job-cards/{job_card}/complete', [JobCardController::class, 'markCompleted'])->name('job-cards.complete');
        Route::post('job-cards/{job_card}/ready-for-invoice', [JobCardController::class, 'markReadyForInvoice'])->name('job-cards.ready-for-invoice');
        Route::post('job-cards/{job_card}/create-invoice', [JobCardController::class, 'createInvoice'])->name('job-cards.create-invoice');
        Route::post('job-cards/{job_card}/cancel', [JobCardController::class, 'cancel'])->name('job-cards.cancel');

        Route::resource('delivery-notes', DeliveryNoteController::class);
        Route::post('delivery-notes/{delivery_note}/issue', [DeliveryNoteController::class, 'issue'])->name('delivery-notes.issue');
        Route::post('delivery-notes/{delivery_note}/delivered', [DeliveryNoteController::class, 'markDelivered'])->name('delivery-notes.delivered');
        Route::post('delivery-notes/{delivery_note}/cancel', [DeliveryNoteController::class, 'cancel'])->name('delivery-notes.cancel');
        Route::get('delivery-notes/{delivery_note}/print', [DeliveryNoteController::class, 'print'])->name('delivery-notes.print');

        Route::resource('requisitions', RequisitionController::class);
        Route::get('requisitions/{requisition}/print', [RequisitionController::class, 'print'])->name('requisitions.print');
        Route::get('requisitions/{requisition}/pdf', [OfficialDocumentController::class, 'requisition'])->name('requisitions.pdf');
        Route::post('requisitions/{requisition}/submit', [RequisitionController::class, 'submit'])->name('requisitions.submit');
        Route::post('requisitions/{requisition}/in-review', [RequisitionController::class, 'markInReview'])->name('requisitions.in-review');
        Route::post('requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])->name('requisitions.approve');
        Route::post('requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])->name('requisitions.reject');
        Route::post('requisitions/{requisition}/release-funds', [RequisitionController::class, 'releaseFunds'])->name('requisitions.release-funds');
        Route::post('requisitions/{requisition}/cancel', [RequisitionController::class, 'cancel'])->name('requisitions.cancel');

        Route::resource('tender-proposals', TenderProposalController::class)->except(['create', 'store', 'destroy']);
        Route::post('tender-proposals/{tender_proposal}/progress', [TenderProposalController::class, 'updateProgress'])->name('tender-proposals.progress');
        Route::resource('quotations', QuotationController::class)->except(['create', 'store', 'destroy']);

        Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
        Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');

        Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Finance — invoices, payments, expenses, suppliers, purchases
    |--------------------------------------------------------------------------
    | Closed to department users. Reception administers; direction and analysis
    | may read.
    */
    Route::middleware('module:finance')->group(function (): void {
        Route::resource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('invoices/{invoice}/pdf', [OfficialDocumentController::class, 'invoice'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments/{payment}/pdf', [OfficialDocumentController::class, 'payment'])->name('payments.pdf');

        Route::resource('expenses', ExpenseController::class)->except(['show']);
        Route::resource('suppliers', SupplierController::class);
        Route::resource('purchases', PurchaseRecordController::class);
        Route::get('purchases/{purchase}/print', [PurchaseRecordController::class, 'print'])->name('purchases.print');
    });

    /*
    |--------------------------------------------------------------------------
    | Approvals and reports
    |--------------------------------------------------------------------------
    */
    Route::middleware('module:approvals')->group(function (): void {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    });

    Route::middleware('module:reports')->group(function (): void {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */
    Route::middleware('module:documents')->group(function (): void {
        Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Operations intake — reception and admin only
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:'.User::ROLE_BUSINESS_ADMIN.','.User::ROLE_RECEPTION)->group(function (): void {
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

    /*
    |--------------------------------------------------------------------------
    | Administration
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Staff administration — business admins may see and add users
    |--------------------------------------------------------------------------
    | Deletion and the technical admin area stay with the super admin.
    */
    Route::middleware('module:users')->group(function (): void {
        Route::post('users/{user}/resend-invitation', [UserController::class, 'resendInvitation'])->name('users.resend-invitation');
        Route::resource('users', UserController::class)->except(['destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Team intake
    |--------------------------------------------------------------------------
    |
    | Departmental officers and heads add trainees and interns to their own
    | department without full user-administration access.
    */
    Route::get('team/create', [UserController::class, 'create'])->name('team.create');
    Route::post('team', [UserController::class, 'store'])->name('team.store');

    Route::middleware('module:admin')->group(function (): void {
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::resource('departments', DepartmentController::class);
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
