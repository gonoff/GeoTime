<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Log an auditable action.
     */
    public function log(
        string $entityType,
        string $entityId,
        string $action,
        string $changedBy,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'changed_by' => $changedBy,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => $ipAddress ?? request()?->ip(),
        ]);
    }
}
