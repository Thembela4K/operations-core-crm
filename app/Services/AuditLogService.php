<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function record(string $event, ?Model $record = null, ?string $description = null, array $oldValues = [], array $newValues = []): AuditLog
    {
        $request = request();

        return AuditLog::query()->create([
            'auditable_type' => $record ? $record::class : null,
            'auditable_id' => $record?->getKey(),
            'user_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
