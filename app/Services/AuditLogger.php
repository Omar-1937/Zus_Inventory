<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes rows to audit_logs. Shared by every module.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function log(string $action, Model|string $entity, ?int $recordId = null, array $details = [], ?User $user = null): AuditLog
    {
        $entityName = $entity instanceof Model ? class_basename($entity) : $entity;
        $recordId ??= $entity instanceof Model ? (int) $entity->getKey() : null;

        return AuditLog::query()->create([
            'user_id' => $user?->user_id ?? Auth::id(),
            'action' => $action,
            'entity_name' => $entityName,
            'record_id' => $recordId,
            'details' => $details === [] ? null : $details,
        ]);
    }
}
