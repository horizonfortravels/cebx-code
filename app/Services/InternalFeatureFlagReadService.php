<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalFeatureFlagReadService
{
    public function __construct(
        private readonly InternalControlPlane $controlPlane,
    ) {}

    /**
     * @param array{q: string, state: string, source: string} $filters
     */
    public function paginate(?User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $rows = $this->filteredRows($user, $filters);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * @param array{q: string, state: string, source: string} $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filteredRows(?User $user, array $filters): Collection
    {
        return $this->visibleRows($user)
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['q'] !== '') {
                    $haystack = Str::lower(implode(' ', array_filter([
                        (string) $row['name'],
                        (string) $row['key'],
                        (string) $row['description'],
                        (string) $row['source_label'],
                    ])));

                    if (! str_contains($haystack, Str::lower($filters['q']))) {
                        return false;
                    }
                }

                if ($filters['state'] !== '' && $row['state_key'] !== $filters['state']) {
                    return false;
                }

                if ($filters['source'] !== '' && $row['source_key'] !== $filters['source']) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @return array{total: int, enabled: int, config_backed: int, targeted: int}
     */
    public function stats(?User $user): array
    {
        $rows = $this->visibleRows($user);

        return [
            'total' => $rows->count(),
            'enabled' => $rows->where('state_key', 'enabled')->count(),
            'config_backed' => $rows->where('source_key', 'config_backed')->count(),
            'targeted' => $rows->filter(static fn (array $row): bool => (int) $row['target_account_count'] > 0 || (int) $row['target_plan_count'] > 0)->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findVisibleDetail(?User $user, string $routeKey): ?array
    {
        $row = $this->visibleRows($user)
            ->first(static fn (array $candidate): bool => $candidate['route_key'] === $routeKey);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, string>
     */
    public function stateOptions(): array
    {
        return [
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sourceOptions(): array
    {
        return [
            'config_backed' => 'Config-backed key',
            'database_only' => 'Database-only key',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function visibleRows(?User $user): Collection
    {
        if ($this->controlPlane->primaryCanonicalRole($user) === InternalControlPlane::ROLE_CARRIER_MANAGER) {
            return collect();
        }

        return $this->allRows();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allRows(): Collection
    {
        if (! Schema::hasTable('feature_flags')) {
            return collect();
        }

        return FeatureFlag::query()
            ->orderBy('name')
            ->orderBy('key')
            ->get()
            ->map(fn (FeatureFlag $flag): array => $this->mapRow($flag))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(FeatureFlag $flag): array
    {
        $configDefaults = config('features', []);
        $hasConfigDefault = array_key_exists((string) $flag->key, $configDefaults);
        $configDefaultState = $hasConfigDefault ? (bool) data_get($configDefaults, (string) $flag->key, false) : null;
        $targetAccounts = collect($flag->target_accounts ?? [])
            ->filter(static fn ($value): bool => is_string($value) && trim($value) !== '')
            ->values();
        $targetPlans = collect($flag->target_plans ?? [])
            ->filter(static fn ($value): bool => is_string($value) && trim($value) !== '')
            ->values();
        $auditItems = $this->auditItemsFor($flag);
        $latestAudit = $auditItems->first();

        return [
            'route_key' => (string) $flag->id,
            'id' => (string) $flag->id,
            'key' => (string) $flag->key,
            'name' => (string) $flag->name,
            'description' => trim((string) $flag->description) !== '' ? (string) $flag->description : 'No description recorded.',
            'state_key' => $flag->is_enabled ? 'enabled' : 'disabled',
            'state_label' => $flag->is_enabled ? 'Enabled' : 'Disabled',
            'rollout_percentage' => max(0, min(100, (int) $flag->rollout_percentage)),
            'rollout_label' => $this->rolloutLabel((int) $flag->rollout_percentage),
            'target_account_count' => $targetAccounts->count(),
            'target_plan_count' => $targetPlans->count(),
            'targeting_summary' => $this->targetingSummary($targetAccounts->count(), $targetPlans->count()),
            'source_key' => $hasConfigDefault ? 'config_backed' : 'database_only',
            'source_label' => $hasConfigDefault ? 'DB row + config default present' : 'Database-backed only',
            'config_default_label' => $hasConfigDefault
                ? ($configDefaultState ? 'Enabled in config' : 'Disabled in config')
                : 'No config default',
            'config_default_present' => $hasConfigDefault,
            'config_default_state' => $configDefaultState,
            'runtime_note' => $hasConfigDefault
                ? 'This key also has an environment-backed config default. The internal center shows the DB-backed flag record and does not act as a hidden environment override.'
                : 'This key is tracked only in the DB-backed feature-flag catalog.',
            'created_by' => trim((string) $flag->created_by) !== '' ? (string) $flag->created_by : 'Unknown',
            'updated_at' => $this->displayDateTime($flag->updated_at) ?? 'Unknown',
            'latest_audit' => is_array($latestAudit) ? $latestAudit : null,
            'audit_items' => $auditItems,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function auditItemsFor(FeatureFlag $flag): Collection
    {
        if (! Schema::hasTable('audit_logs')) {
            return collect();
        }

        return AuditLog::query()
            ->withoutGlobalScopes()
            ->with('performer:id,name,email')
            ->where('action', 'admin.feature_flag_toggled')
            ->where('entity_type', 'FeatureFlag')
            ->where('entity_id', (string) $flag->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (AuditLog $audit): array {
                $newValues = is_array($audit->new_values) ? $audit->new_values : [];
                $oldValues = is_array($audit->old_values) ? $audit->old_values : [];
                $enabledBefore = (bool) ($oldValues['is_enabled'] ?? false);
                $enabledAfter = (bool) ($newValues['is_enabled'] ?? false);

                return [
                    'headline' => $enabledAfter ? 'Enabled by internal operator' : 'Disabled by internal operator',
                    'detail' => implode(' • ', array_filter([
                        $enabledBefore === $enabledAfter ? null : sprintf(
                            '%s -> %s',
                            $enabledBefore ? 'Enabled' : 'Disabled',
                            $enabledAfter ? 'Enabled' : 'Disabled'
                        ),
                        trim((string) data_get($audit->metadata, 'reason', '')) ?: null,
                    ])),
                    'reason' => trim((string) data_get($audit->metadata, 'reason', '')) ?: 'No reason recorded.',
                    'performed_by' => $audit->performer?->name
                        ? $audit->performer->name . ' • ' . $audit->performer->email
                        : 'System',
                    'created_at' => $this->displayDateTime($audit->created_at) ?? 'Unknown',
                ];
            })
            ->values();
    }

    private function rolloutLabel(int $percentage): string
    {
        $percentage = max(0, min(100, $percentage));

        return match (true) {
            $percentage >= 100 => 'Global rollout',
            $percentage <= 0 => 'Disabled rollout',
            default => sprintf('%d%% deterministic rollout', $percentage),
        };
    }

    private function targetingSummary(int $accountCount, int $planCount): string
    {
        if ($accountCount === 0 && $planCount === 0) {
            return 'No account or plan targeting recorded.';
        }

        return implode(' • ', array_filter([
            $accountCount > 0 ? number_format($accountCount) . ' target account(s)' : null,
            $planCount > 0 ? number_format($planCount) . ' target plan(s)' : null,
        ]));
    }

    private function displayDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
    }
}
