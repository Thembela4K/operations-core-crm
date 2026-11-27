<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * The single active default type; Office unless configured otherwise.
     */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->where('is_active', true)->first()
            ?? static::query()->where('is_active', true)->orderBy('id')->first();
    }
}
