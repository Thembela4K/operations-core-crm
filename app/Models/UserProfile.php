<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personal information for a staff member: contact details, addresses,
 * emergency contact and banking information.
 */
class UserProfile extends Model
{
    public const GENDERS = ['Female', 'Male', 'Other', 'Prefer not to say'];

    public const MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed', 'Other'];

    public const BANK_ACCOUNT_TYPES = ['Savings', 'Cheque / Current', 'Transmission'];

    protected $fillable = [
        'user_id',
        'phone',
        'alternate_phone',
        'personal_email',
        'national_id',
        'date_of_birth',
        'gender',
        'nationality',
        'marital_status',
        'residential_address',
        'postal_address',
        'city',
        'region',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_branch',
        'bank_branch_code',
        'bank_account_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
