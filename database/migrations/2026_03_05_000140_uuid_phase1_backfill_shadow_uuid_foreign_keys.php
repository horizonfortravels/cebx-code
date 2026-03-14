<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $violations = [];

        foreach ($this->fkEdges() as $edge) {
            $this->backfillEdge($edge);
            $this->validateEdge($edge, $violations);
        }

        if ($violations !== []) {
            throw new RuntimeException($this->formatViolations($violations));
        }
    }

    public function down(): void
    {
        // No-op: this migration only backfills and validates shadow UUID FK values.
    }

    private function backfillEdge(array $edge): void
    {
        $childTable = $edge['child_table'];
        $childColumn = $edge['child_column'];
        $parentTable = $edge['parent_table'];
        $parentColumn = $edge['parent_column'];
        $shadowColumn = $this->shadowColumnName($childColumn);

        if (
            !Schema::hasTable($childTable) ||
            !Schema::hasTable($parentTable) ||
            !Schema::hasColumn($childTable, $childColumn) ||
            !Schema::hasColumn($childTable, $shadowColumn) ||
            !Schema::hasColumn($parentTable, $parentColumn)
        ) {
            return;
        }

        $parentUuidColumn = $this->resolveParentUuidColumn($parentTable, $parentColumn);
        if ($parentUuidColumn === null || !Schema::hasColumn($parentTable, $parentUuidColumn)) {
            return;
        }

        DB::table($childTable)
            ->select($childColumn)
            ->whereNotNull($childColumn)
            ->whereNull($shadowColumn)
            ->distinct()
            ->orderBy($childColumn)
            ->chunk(500, function ($rows) use (
                $childTable,
                $childColumn,
                $shadowColumn,
                $parentTable,
                $parentColumn,
                $parentUuidColumn
            ): void {
                $legacyValues = [];
                foreach ($rows as $row) {
                    $legacyValues[] = $row->{$childColumn};
                }

                if ($legacyValues === []) {
                    return;
                }

                $parentRows = DB::table($parentTable)
                    ->select($parentColumn, $parentUuidColumn)
                    ->whereIn($parentColumn, $legacyValues)
                    ->get();

                foreach ($parentRows as $parentRow) {
                    $uuidValue = $parentRow->{$parentUuidColumn};
                    if ($uuidValue === null || trim((string) $uuidValue) === '') {
                        continue;
                    }

                    DB::table($childTable)
                        ->where($childColumn, $parentRow->{$parentColumn})
                        ->whereNull($shadowColumn)
                        ->update([$shadowColumn => (string) $uuidValue]);
                }
            });
    }

    /**
     * @param array<int, array<string, mixed>> $violations
     */
    private function validateEdge(array $edge, array &$violations): void
    {
        $childTable = $edge['child_table'];
        $childColumn = $edge['child_column'];
        $shadowColumn = $this->shadowColumnName($childColumn);
        $nullable = (bool) $edge['nullable'];
        $edgeIdentifier = "{$childTable}.{$shadowColumn}";

        if (
            !Schema::hasTable($childTable) ||
            !Schema::hasColumn($childTable, $childColumn) ||
            !Schema::hasColumn($childTable, $shadowColumn)
        ) {
            return;
        }

        $missingShadowForLegacyQuery = DB::table($childTable)
            ->whereNotNull($childColumn)
            ->whereNull($shadowColumn);

        $this->pushViolation(
            $violations,
            $edgeIdentifier,
            'missing_shadow_for_legacy_fk',
            (clone $missingShadowForLegacyQuery)->count(),
            $this->sampleDistinct((clone $missingShadowForLegacyQuery), $childColumn)
        );

        if (!$nullable) {
            $nullShadowQuery = DB::table($childTable)
                ->whereNull($shadowColumn);

            $this->pushViolation(
                $violations,
                $edgeIdentifier,
                'null_shadow_on_non_nullable_edge',
                (clone $nullShadowQuery)->count(),
                $this->sampleDistinct((clone $nullShadowQuery), $childColumn)
            );
        }
    }

    /**
     * @return array<int, array{child_table: string, child_column: string, parent_table: string, parent_column: string, nullable: bool}>
     */
    private function fkEdges(): array
    {
        return [
            ['child_table' => 'users', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'addresses', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'claims', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'invitations', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'kyc_requests', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'notifications', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'notifications', 'child_column' => 'user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'orders', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'shipments', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'shipments', 'child_column' => 'user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'stores', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'support_tickets', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'support_tickets', 'child_column' => 'user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'ticket_replies', 'child_column' => 'user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'wallet_transactions', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'wallets', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'audit_logs', 'child_column' => 'user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'orders', 'child_column' => 'store_id', 'parent_table' => 'stores', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'wallet_transactions', 'child_column' => 'wallet_id', 'parent_table' => 'wallets', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'ticket_replies', 'child_column' => 'support_ticket_id', 'parent_table' => 'support_tickets', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'shipment_events', 'child_column' => 'shipment_id', 'parent_table' => 'shipments', 'parent_column' => 'id', 'nullable' => false],
            ['child_table' => 'claims', 'child_column' => 'shipment_id', 'parent_table' => 'shipments', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'customs_declarations', 'child_column' => 'shipment_id', 'parent_table' => 'shipments', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'containers', 'child_column' => 'vessel_id', 'parent_table' => 'vessels', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'schedules', 'child_column' => 'vessel_id', 'parent_table' => 'vessels', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'risk_alerts', 'child_column' => 'risk_rule_id', 'parent_table' => 'risk_rules', 'parent_column' => 'id', 'nullable' => true],
            ['child_table' => 'branch_staff', 'child_column' => 'branch_id', 'parent_table' => 'branches', 'parent_column' => 'id', 'nullable' => false],
        ];
    }

    private function shadowColumnName(string $legacyColumn): string
    {
        return "{$legacyColumn}_uuid";
    }

    private function resolveParentUuidColumn(string $table, string $idColumn): ?string
    {
        if (Schema::hasColumn($table, 'id_uuid')) {
            return 'id_uuid';
        }

        if (!$this->isNumericColumn($table, $idColumn)) {
            return $idColumn;
        }

        return null;
    }

    private function isNumericColumn(string $table, string $column): bool
    {
        $type = strtolower((string) Schema::getColumnType($table, $column));

        return in_array($type, [
            'integer',
            'int',
            'tinyint',
            'smallint',
            'mediumint',
            'bigint',
            'biginteger',
            'unsignedbiginteger',
            'unsignedinteger',
            'decimal',
            'double',
            'float',
            'real',
            'numeric',
        ], true);
    }

    /**
     * @param array<int, array<string, mixed>> $violations
     * @param array<int, scalar|null> $samples
     */
    private function pushViolation(
        array &$violations,
        string $edgeIdentifier,
        string $type,
        int $count,
        array $samples
    ): void {
        if ($count <= 0) {
            return;
        }

        $violations[] = [
            'edge' => $edgeIdentifier,
            'type' => $type,
            'count' => $count,
            'samples' => $this->normalizeSamples($samples),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sampleDistinct(Builder $query, string $column): array
    {
        /** @var array<int, scalar|null> $values */
        $values = $query
            ->select($column)
            ->distinct()
            ->limit(5)
            ->pluck($column)
            ->all();

        return $this->normalizeSamples($values);
    }

    /**
     * @param array<int, scalar|null> $samples
     * @return array<int, string>
     */
    private function normalizeSamples(array $samples): array
    {
        $normalized = [];

        foreach ($samples as $sample) {
            if ($sample === null) {
                $normalized[] = 'NULL';
                continue;
            }

            $value = trim((string) $sample);
            $normalized[] = $value === '' ? '<blank>' : (string) $sample;
        }

        return array_values(array_slice(array_unique($normalized), 0, 5));
    }

    /**
     * @param array<int, array{edge: string, type: string, count: int, samples: array<int, string>}> $violations
     */
    private function formatViolations(array $violations): string
    {
        $lines = [
            'UUID Phase 1 shadow FK backfill failed with '.count($violations).' violation(s):',
        ];

        foreach ($violations as $violation) {
            $samples = $violation['samples'] === [] ? 'none' : implode(', ', $violation['samples']);
            $lines[] = sprintf(
                '- edge=%s; type=%s; count=%d; sample_ids=[%s]',
                $violation['edge'],
                $violation['type'],
                $violation['count'],
                $samples
            );
        }

        return implode(PHP_EOL, $lines);
    }
};
