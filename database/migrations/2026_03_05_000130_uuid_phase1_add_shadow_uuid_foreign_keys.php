<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->fkEdges() as $edge) {
            $childTable = $edge['child_table'];
            $childColumn = $edge['child_column'];
            $shadowColumn = $this->shadowColumnName($childColumn);

            if (
                !Schema::hasTable($childTable) ||
                !Schema::hasColumn($childTable, $childColumn) ||
                Schema::hasColumn($childTable, $shadowColumn)
            ) {
                continue;
            }

            $indexName = $this->shadowIndexName($childTable, $shadowColumn);

            Schema::table($childTable, function (Blueprint $table) use ($shadowColumn, $indexName): void {
                $table->uuid($shadowColumn)->nullable();
                $table->index($shadowColumn, $indexName);
            });
        }
    }

    public function down(): void
    {
        $processed = [];

        foreach (array_reverse($this->fkEdges()) as $edge) {
            $childTable = $edge['child_table'];
            $childColumn = $edge['child_column'];
            $shadowColumn = $this->shadowColumnName($childColumn);
            $key = "{$childTable}.{$shadowColumn}";

            if (isset($processed[$key])) {
                continue;
            }
            $processed[$key] = true;

            if (!Schema::hasTable($childTable) || !Schema::hasColumn($childTable, $shadowColumn)) {
                continue;
            }

            $indexName = $this->shadowIndexName($childTable, $shadowColumn);

            try {
                Schema::table($childTable, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            } catch (\Throwable) {
                // Index may not exist on all platforms; ignore in rollback.
            }

            Schema::table($childTable, function (Blueprint $table) use ($shadowColumn): void {
                $table->dropColumn($shadowColumn);
            });
        }
    }

    /**
     * @return array<int, array{child_table: string, child_column: string}>
     */
    private function fkEdges(): array
    {
        return [
            ['child_table' => 'users', 'child_column' => 'account_id'],
            ['child_table' => 'addresses', 'child_column' => 'account_id'],
            ['child_table' => 'claims', 'child_column' => 'account_id'],
            ['child_table' => 'invitations', 'child_column' => 'account_id'],
            ['child_table' => 'kyc_requests', 'child_column' => 'account_id'],
            ['child_table' => 'notifications', 'child_column' => 'account_id'],
            ['child_table' => 'notifications', 'child_column' => 'user_id'],
            ['child_table' => 'orders', 'child_column' => 'account_id'],
            ['child_table' => 'shipments', 'child_column' => 'account_id'],
            ['child_table' => 'shipments', 'child_column' => 'user_id'],
            ['child_table' => 'stores', 'child_column' => 'account_id'],
            ['child_table' => 'support_tickets', 'child_column' => 'account_id'],
            ['child_table' => 'support_tickets', 'child_column' => 'user_id'],
            ['child_table' => 'ticket_replies', 'child_column' => 'user_id'],
            ['child_table' => 'wallet_transactions', 'child_column' => 'account_id'],
            ['child_table' => 'wallets', 'child_column' => 'account_id'],
            ['child_table' => 'audit_logs', 'child_column' => 'user_id'],
            ['child_table' => 'orders', 'child_column' => 'store_id'],
            ['child_table' => 'wallet_transactions', 'child_column' => 'wallet_id'],
            ['child_table' => 'ticket_replies', 'child_column' => 'support_ticket_id'],
            ['child_table' => 'shipment_events', 'child_column' => 'shipment_id'],
            ['child_table' => 'claims', 'child_column' => 'shipment_id'],
            ['child_table' => 'customs_declarations', 'child_column' => 'shipment_id'],
            ['child_table' => 'containers', 'child_column' => 'vessel_id'],
            ['child_table' => 'schedules', 'child_column' => 'vessel_id'],
            ['child_table' => 'risk_alerts', 'child_column' => 'risk_rule_id'],
            ['child_table' => 'branch_staff', 'child_column' => 'branch_id'],
        ];
    }

    private function shadowColumnName(string $legacyColumn): string
    {
        return "{$legacyColumn}_uuid";
    }

    private function shadowIndexName(string $table, string $shadowColumn): string
    {
        return 'idx_'.$table.'_'.$shadowColumn.'_'.substr(md5($table.'_'.$shadowColumn), 0, 8);
    }
};
