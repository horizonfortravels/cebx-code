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
            $this->validateEdge($edge, $violations);
        }

        $this->validatePersonalAccessTokens($violations);

        if ($violations !== []) {
            throw new RuntimeException($this->formatViolations($violations));
        }
    }

    public function down(): void
    {
        // No-op: this is a validation-only deployment guard.
    }

    /**
     * @param array<int, array<string, mixed>> $violations
     */
    private function validateEdge(array $edge, array &$violations): void
    {
        $childTable = $edge['child_table'];
        $childColumn = $edge['child_column'];
        $parentTable = $edge['parent_table'];
        $parentColumn = $edge['parent_column'];
        $nullable = (bool) $edge['nullable'];
        $edgeIdentifier = "{$childTable}.{$childColumn}";

        if (
            !Schema::hasTable($childTable) ||
            !Schema::hasTable($parentTable) ||
            !Schema::hasColumn($childTable, $childColumn) ||
            !Schema::hasColumn($parentTable, $parentColumn)
        ) {
            return;
        }

        $childQualified = "c.{$childColumn}";
        $parentQualified = "p.{$parentColumn}";

        if (!$nullable) {
            $nullCount = DB::table("{$childTable} as c")
                ->whereNull($childQualified)
                ->count();

            $this->pushViolation($violations, $edgeIdentifier, 'null_required', $nullCount, ['NULL']);
        }

        if ($this->isNumericColumn($childTable, $childColumn)) {
            $invalidQuery = DB::table("{$childTable} as c")
                ->whereNotNull($childQualified)
                ->where($childQualified, '<=', 0);
        } else {
            $invalidQuery = DB::table("{$childTable} as c")
                ->whereNotNull($childQualified)
                ->whereRaw($this->trimmedBlankSql($childQualified));
        }

        $this->pushViolation(
            $violations,
            $edgeIdentifier,
            'invalid_id',
            (clone $invalidQuery)->count(),
            $this->sampleDistinct((clone $invalidQuery), $childQualified)
        );

        $orphanQuery = DB::table("{$childTable} as c")
            ->leftJoin("{$parentTable} as p", $childQualified, '=', $parentQualified)
            ->whereNotNull($childQualified)
            ->whereNull($parentQualified);

        $this->pushViolation(
            $violations,
            $edgeIdentifier,
            'orphan_refs',
            (clone $orphanQuery)->count(),
            $this->sampleDistinct((clone $orphanQuery), $childQualified)
        );
    }

    /**
     * @param array<int, array<string, mixed>> $violations
     */
    private function validatePersonalAccessTokens(array &$violations): void
    {
        if (
            !Schema::hasTable('personal_access_tokens') ||
            !Schema::hasColumn('personal_access_tokens', 'tokenable_type') ||
            !Schema::hasColumn('personal_access_tokens', 'tokenable_id')
        ) {
            return;
        }

        $supportedTypes = ['App\\Models\\User'];

        $nullTypeQuery = DB::table('personal_access_tokens as pat')
            ->whereNull('pat.tokenable_type');

        $this->pushViolation(
            $violations,
            'personal_access_tokens.tokenable_type',
            'null_required',
            (clone $nullTypeQuery)->count(),
            ['NULL']
        );

        $blankTypeQuery = DB::table('personal_access_tokens as pat')
            ->whereNotNull('pat.tokenable_type')
            ->whereRaw($this->trimmedBlankSql('pat.tokenable_type'));

        $this->pushViolation(
            $violations,
            'personal_access_tokens.tokenable_type',
            'invalid_type',
            (clone $blankTypeQuery)->count(),
            $this->sampleDistinct((clone $blankTypeQuery), 'pat.tokenable_type')
        );

        $invalidTypeQuery = DB::table('personal_access_tokens as pat')
            ->whereNotNull('pat.tokenable_type')
            ->whereRaw('TRIM(CAST(pat.tokenable_type AS CHAR)) <> \'\'')
            ->whereNotIn('pat.tokenable_type', $supportedTypes);

        $this->pushViolation(
            $violations,
            'personal_access_tokens.tokenable_type',
            'invalid_type',
            (clone $invalidTypeQuery)->count(),
            $this->sampleDistinct((clone $invalidTypeQuery), 'pat.tokenable_type')
        );

        $nullIdQuery = DB::table('personal_access_tokens as pat')
            ->whereNull('pat.tokenable_id');

        $this->pushViolation(
            $violations,
            'personal_access_tokens.tokenable_id',
            'null_required',
            (clone $nullIdQuery)->count(),
            ['NULL']
        );

        if ($this->isNumericColumn('personal_access_tokens', 'tokenable_id')) {
            $invalidIdQuery = DB::table('personal_access_tokens as pat')
                ->whereNotNull('pat.tokenable_id')
                ->where('pat.tokenable_id', '<=', 0);
        } else {
            $invalidIdQuery = DB::table('personal_access_tokens as pat')
                ->whereNotNull('pat.tokenable_id')
                ->whereRaw($this->trimmedBlankSql('pat.tokenable_id'));
        }

        $this->pushViolation(
            $violations,
            'personal_access_tokens.tokenable_id',
            'invalid_id',
            (clone $invalidIdQuery)->count(),
            $this->sampleDistinct((clone $invalidIdQuery), 'pat.tokenable_id')
        );

        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'id')) {
            return;
        }

        $orphanUserTokenQuery = DB::table('personal_access_tokens as pat')
            ->leftJoin('users as u', 'pat.tokenable_id', '=', 'u.id')
            ->where('pat.tokenable_type', 'App\\Models\\User')
            ->whereNotNull('pat.tokenable_id')
            ->whereNull('u.id');

        $this->pushViolation(
            $violations,
            'personal_access_tokens.tokenable_id',
            'orphan_refs',
            (clone $orphanUserTokenQuery)->count(),
            $this->sampleDistinct((clone $orphanUserTokenQuery), 'pat.tokenable_id')
        );
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
        ];
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

    private function isNumericColumn(string $table, string $column): bool
    {
        $type = strtolower(Schema::getColumnType($table, $column));

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

    private function trimmedBlankSql(string $qualifiedColumn): string
    {
        return "TRIM(CAST({$qualifiedColumn} AS CHAR)) = ''";
    }

    /**
     * @return array<int, string>
     */
    private function sampleDistinct(Builder $query, string $qualifiedColumn): array
    {
        /** @var array<int, scalar|null> $values */
        $values = $query
            ->select($qualifiedColumn)
            ->distinct()
            ->limit(5)
            ->pluck($qualifiedColumn)
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
            'UUID Phase 1 preflight guard failed with '.count($violations).' violation(s):',
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
