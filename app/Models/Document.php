<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasFactory;

    public const CATEGORY_ORIGINAL_TENDER = 'original_tender';

    public const CATEGORY_QUOTATION_REQUEST = 'quotation_request';

    public const CATEGORY_QUOTATION_IMAGE = 'quotation_image';

    public const CATEGORY_TECHNICAL_PROPOSAL = 'technical_proposal';

    public const CATEGORY_FINANCIAL_PROPOSAL = 'financial_proposal';

    public const CATEGORY_SUPPORTING_DOCUMENT = 'supporting_document';

    public const CATEGORY_EXPENSE_RECEIPT = 'expense_receipt';

    public const CATEGORY_REQUISITION_ATTACHMENT = 'requisition_attachment';

    public const CATEGORY_GENERATED_PDF = 'generated_pdf';

    public const CATEGORY_RECEIPT = 'receipt';

    public const CATEGORY_OTHER = 'other';

    /*
    | Company documents stand on their own (no parent record): trading
    | licences, registrations and similar operational paperwork.
    */
    public const CATEGORY_TRADING_LICENSE = 'trading_license';

    public const CATEGORY_COMPANY_REGISTRATION = 'company_registration';

    public const CATEGORY_TAX_COMPLIANCE = 'tax_compliance';

    public const CATEGORY_INSURANCE = 'insurance';

    public const CATEGORY_POLICY = 'policy_procedure';

    public const CATEGORY_COMPANY_OTHER = 'company_other';

    public const COMPANY_CATEGORIES = [
        self::CATEGORY_TRADING_LICENSE => 'Trading Licence',
        self::CATEGORY_COMPANY_REGISTRATION => 'Company Registration',
        self::CATEGORY_TAX_COMPLIANCE => 'Tax / Compliance',
        self::CATEGORY_INSURANCE => 'Insurance',
        self::CATEGORY_POLICY => 'Policy / Procedure',
        self::CATEGORY_COMPANY_OTHER => 'Other Company Document',
    ];

    public const CATEGORIES = [
        self::CATEGORY_ORIGINAL_TENDER => 'Original Tender / Request',
        self::CATEGORY_QUOTATION_REQUEST => 'Quotation Request',
        self::CATEGORY_QUOTATION_IMAGE => 'Quotation Image',
        self::CATEGORY_TECHNICAL_PROPOSAL => 'Technical Proposal',
        self::CATEGORY_FINANCIAL_PROPOSAL => 'Financial Proposal',
        self::CATEGORY_SUPPORTING_DOCUMENT => 'Supporting Document',
        self::CATEGORY_EXPENSE_RECEIPT => 'Expense Receipt',
        self::CATEGORY_REQUISITION_ATTACHMENT => 'Requisition Attachment',
        self::CATEGORY_GENERATED_PDF => 'Generated PDF',
        self::CATEGORY_RECEIPT => 'Receipt',
        self::CATEGORY_OTHER => 'Other',
    ];

    public const PREVIEWABLE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/svg+xml',
        'text/plain',
        'text/csv',
    ];

    protected $fillable = [
        'category',
        'title',
        'tags',
        'is_generated',
        'is_company_document',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_generated' => 'boolean',
            'is_company_document' => 'boolean',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function textIndex(): HasOne
    {
        return $this->hasOne(DocumentText::class);
    }

    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_MIME_TYPES, true);
    }

    /**
     * Attachments are scoped to the uploader and their department (the people
     * who review the parent record). Company documents are readable by every
     * signed-in employee.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->seesAllDepartments()) {
            return $query;
        }

        return $query->where(function ($inner) use ($user): void {
            $inner->where('is_company_document', true)
                ->orWhere('uploaded_by', $user->id)
                ->orWhereIn(
                    'uploaded_by',
                    User::query()
                        ->where('department_id', $user->department_id)
                        ->select('id'),
                );
        });
    }
}
