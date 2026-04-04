<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InternalFeatureFlagActionService
{
    /**
     * @var array<int, string>
     */
    private const UNSAFE_KEYS = [
        'demo_data',
        'maintenance',
        'sandbox_mode',
    ];

    public function __construct(
        private readonly AdminService $adminService,
        private readonly AuditService $auditService,
    ) {}

    public function toggle(string $flagId, User $actor, bool $enabled, string $reason): FeatureFlag
    {
        $this->assertCanManageFlags($actor);

        $flag = FeatureFlag::query()->findOrFail($flagId);
        $normalizedReason = $this->normalizeReason($reason);

        if (in_array((string) $flag->key, self::UNSAFE_KEYS, true)) {
            throw new BusinessException(
                'This feature flag is not exposed for internal web toggling.',
                'ERR_FEATURE_FLAG_UNSAFE',
                422
            );
        }

        if ((bool) $flag->is_enabled === $enabled) {
            throw new BusinessException(
                'The requested feature-flag state is already active.',
                'ERR_FEATURE_FLAG_NO_CHANGE',
                422
            );
        }

        return DB::transaction(function () use ($flag, $actor, $enabled, $normalizedReason): FeatureFlag {
            $oldValues = $this->snapshot($flag);
            $updated = $this->adminService->toggleFeatureFlag((string) $flag->id, $enabled);

            $this->auditService->info(
                null,
                (string) $actor->id,
                'admin.feature_flag_toggled',
                'admin',
                'FeatureFlag',
                (string) $updated->id,
                $oldValues,
                $this->snapshot($updated),
                [
                    'reason' => $normalizedReason,
                    'flag_key' => (string) $updated->key,
                    'internal_scope' => 'internal_feature_flags_center',
                    'config_default_present' => array_key_exists((string) $updated->key, config('features', [])),
                ]
            );

            return $updated;
        });
    }

    private function assertCanManageFlags(User $actor): void
    {
        if (! $actor->hasPermission('feature_flags.manage')) {
            throw BusinessException::permissionDenied();
        }
    }

    private function normalizeReason(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($normalized === '') {
            throw new BusinessException(
                'A clear operator reason is required for internal feature-flag changes.',
                'ERR_FEATURE_FLAG_REASON_REQUIRED',
                422
            );
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(FeatureFlag $flag): array
    {
        return [
            'key' => (string) $flag->key,
            'name' => (string) $flag->name,
            'is_enabled' => (bool) $flag->is_enabled,
            'rollout_percentage' => (int) $flag->rollout_percentage,
            'target_account_count' => count(array_filter((array) ($flag->target_accounts ?? []))),
            'target_plan_count' => count(array_filter((array) ($flag->target_plans ?? []))),
            'created_by' => trim((string) $flag->created_by) !== '' ? (string) $flag->created_by : null,
        ];
    }
}
