<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_ORIGINAL_TENDER => 'Original Tender / Request',
        self::CATEGORY_QUOTATION_REQUEST => 'Quotation Request',
        self::CATEGORY_QUOTATION_IMAGE => 'Quotation Image',
        self::CATEGORY_TECHNICAL_PROPOSAL => 'Technical Proposal',
        self::CATEGORY_FINANCIAL_PROPOSAL => 'Financial Proposal',
        self::CATEGORY_SUPPORTING_DOCUMENT => 'Supporting Document',
        self::CATEGORY_OTHER => 'Other',
    ];

    protected $fillable = [
        'category',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
