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
            'enabled' => 'مفعّل',
            'disabled' => 'معطّل',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sourceOptions(): array
    {
        return [
            'config_backed' => 'مفتاح معتمد على الإعدادات',
            'database_only' => 'مفتاح من قاعدة البيانات فقط',
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
            'description' => trim((string) $flag->description) !== '' ? (string) $flag->description : 'لا يوجد وصف مسجل.',
            'state_key' => $flag->is_enabled ? 'enabled' : 'disabled',
            'state_label' => $flag->is_enabled ? 'مفعّل' : 'معطّل',
            'rollout_percentage' => max(0, min(100, (int) $flag->rollout_percentage)),
            'rollout_label' => $this->rolloutLabel((int) $flag->rollout_percentage),
            'target_account_count' => $targetAccounts->count(),
            'target_plan_count' => $targetPlans->count(),
            'targeting_summary' => $this->targetingSummary($targetAccounts->count(), $targetPlans->count()),
            'source_key' => $hasConfigDefault ? 'config_backed' : 'database_only',
            'source_label' => $hasConfigDefault ? 'يوجد سجل قاعدة بيانات مع قيمة افتراضية من الإعدادات' : 'من قاعدة البيانات فقط',
            'config_default_label' => $hasConfigDefault
                ? ($configDefaultState ? 'مفعّل في الإعدادات' : 'معطّل في الإعدادات')
                : 'لا توجد قيمة افتراضية في الإعدادات',
            'config_default_present' => $hasConfigDefault,
            'config_default_state' => $configDefaultState,
            'runtime_note' => $hasConfigDefault
                ? 'لهذا المفتاح أيضًا قيمة افتراضية معتمدة على البيئة. تعرض الواجهة الداخلية سجل العلم في قاعدة البيانات فقط ولا تعمل كتجاوز خفي لإعدادات البيئة.'
                : 'يتم تتبع هذا المفتاح فقط ضمن سجل أعلام الميزات المعتمد على قاعدة البيانات.',
            'created_by' => trim((string) $flag->created_by) !== '' ? (string) $flag->created_by : 'غير معروف',
            'updated_at' => $this->displayDateTime($flag->updated_at) ?? 'غير معروف',
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
                    'headline' => $enabledAfter ? 'تم التفعيل بواسطة المشغل الداخلي' : 'تم التعطيل بواسطة المشغل الداخلي',
                    'detail' => implode(' • ', array_filter([
                        $enabledBefore === $enabledAfter ? null : sprintf(
                            '%s -> %s',
                            $enabledBefore ? 'مفعّل' : 'معطّل',
                            $enabledAfter ? 'مفعّل' : 'معطّل'
                        ),
                        trim((string) data_get($audit->metadata, 'reason', '')) ?: null,
                    ])),
                    'reason' => trim((string) data_get($audit->metadata, 'reason', '')) ?: 'لا يوجد سبب مسجل.',
                    'performed_by' => $audit->performer?->name
                        ? $audit->performer->name . ' • ' . $audit->performer->email
                        : 'النظام',
                    'created_at' => $this->displayDateTime($audit->created_at) ?? 'غير معروف',
                ];
            })
            ->values();
    }

    private function rolloutLabel(int $percentage): string
    {
        $percentage = max(0, min(100, $percentage));

        return match (true) {
            $percentage >= 100 => 'إطلاق عام',
            $percentage <= 0 => 'إطلاق معطّل',
            default => sprintf('إطلاق تدريجي محدد بنسبة %d%%', $percentage),
        };
    }

    private function targetingSummary(int $accountCount, int $planCount): string
    {
        if ($accountCount === 0 && $planCount === 0) {
            return 'لا يوجد استهداف مسجل للحسابات أو الخطط.';
        }

        return implode(' • ', array_filter([
            $accountCount > 0 ? number_format($accountCount) . ' حساب مستهدف' : null,
            $planCount > 0 ? number_format($planCount) . ' خطة مستهدفة' : null,
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
