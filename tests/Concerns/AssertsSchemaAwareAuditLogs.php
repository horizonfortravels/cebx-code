<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait AssertsSchemaAwareAuditLogs
{
    protected function assertAuditLogRecorded(
        string $action,
        ?string $userId = null,
        ?string $accountId = null,
        ?string $entityType = null,
        ?string $entityId = null,
    ): void {
        $query = DB::table('audit_logs');

        if (Schema::hasColumn('audit_logs', 'action')) {
            $query->where('action', $action);
        } elseif (Schema::hasColumn('audit_logs', 'event')) {
            $query->where('event', $action);
        }

        if ($userId !== null && Schema::hasColumn('audit_logs', 'user_id')) {
            $query->where('user_id', $userId);
        }

        if ($accountId !== null && Schema::hasColumn('audit_logs', 'account_id')) {
            $query->where('account_id', $accountId);
        }

        if ($entityType !== null) {
            if (Schema::hasColumn('audit_logs', 'entity_type')) {
                $query->where('entity_type', $entityType);
            } elseif (Schema::hasColumn('audit_logs', 'auditable_type')) {
                $query->where('auditable_type', $entityType);
            }
        }

        if ($entityId !== null) {
            if (Schema::hasColumn('audit_logs', 'entity_id')) {
                $query->where('entity_id', $entityId);
            } elseif (Schema::hasColumn('audit_logs', 'auditable_id') && ctype_digit($entityId)) {
                $query->where('auditable_id', (int) $entityId);
            }
        }

        $this->assertTrue(
            $query->exists(),
            sprintf('Failed asserting audit log exists for action [%s].', $action)
        );
    }
}
