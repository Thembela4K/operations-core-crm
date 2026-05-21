<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function valueFor(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value === null || $value === '' ? $default : $value;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value ?? ''],
        );
    }
}
