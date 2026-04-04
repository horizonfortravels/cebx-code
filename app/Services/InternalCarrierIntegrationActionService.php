<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\FeatureFlag;
use App\Models\IntegrationHealthLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalCarrierIntegrationActionService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly CarrierSettingsService $carrierSettingsService,
    ) {}

    public function toggle(string $carrierCode, User $actor, bool $enabled, string $reason): FeatureFlag
    {
        $this->assertCanManageCarriers($actor);

        $carrier = $this->carrierDefinition($carrierCode);
        $normalizedReason = $this->normalizeReason(
            $reason,
            'A clear operator reason is required before changing carrier state.'
        );
        $flagKey = $carrier['flag_key'];
        $currentRuntimeState = FeatureFlag::runtimeEnabled($flagKey);

        if ($currentRuntimeState === $enabled) {
            throw new BusinessException(
                'The requested carrier state is already active.',
                'ERR_CARRIER_STATE_NO_CHANGE',
                422
            );
        }

        return DB::transaction(function () use ($actor, $carrier, $carrierCode, $flagKey, $enabled, $normalizedReason, $currentRuntimeState): FeatureFlag {
            $flag = FeatureFlag::query()->firstOrNew(['key' => $flagKey]);
            $oldValues = [
                'runtime_enabled' => $currentRuntimeState,
                'db_flag_present' => $flag->exists,
                'db_is_enabled' => $flag->exists ? (bool) $flag->is_enabled : null,
            ];

            if (! $flag->exists) {
                $flag->fill([
                    'name' => $carrier['label'],
                    'description' => sprintf('Enable %s carrier workflows from the internal carrier center.', $carrier['label']),
                    'rollout_percentage' => 100,
                    'target_accounts' => [],
                    'target_plans' => [],
                    'created_by' => (string) $actor->id,
                ]);
            }

            $flag->fill([
                'name' => $carrier['label'],
                'description' => sprintf('Enable %s carrier workflows from the internal carrier center.', $carrier['label']),
                'is_enabled' => $enabled,
                'rollout_percentage' => 100,
                'target_accounts' => [],
                'target_plans' => [],
            ])->save();

            $flag->refresh();

            $this->auditService->info(
                null,
                (string) $actor->id,
                $enabled ? 'carrier.integration_enabled' : 'carrier.integration_disabled',
                'carrier',
                'FeatureFlag',
                (string) $flag->id,
                $oldValues,
                [
                    'runtime_enabled' => FeatureFlag::runtimeEnabled($flagKey),
                    'db_flag_present' => true,
                    'db_is_enabled' => (bool) $flag->is_enabled,
                ],
                [
                    'reason' => $normalizedReason,
                    'carrier_code' => $carrierCode,
                    'carrier_label' => $carrier['label'],
                    'flag_key' => $flagKey,
                    'action_scope' => 'internal_carriers_center',
                ]
            );

            return $flag;
        });
    }

    public function testConnection(string $carrierCode, User $actor): IntegrationHealthLog
    {
        return $this->performConnectionTest($carrierCode, $actor, 'internal_carrier_center');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{updated_fields: array<int, string>, connection_log: IntegrationHealthLog}
     */
    public function updateCredentials(string $carrierCode, User $actor, array $payload, string $reason): array
    {
        $this->assertCanManageCarriers($actor);

        $carrier = $this->carrierDefinition($carrierCode);
        $normalizedReason = $this->normalizeReason(
            $reason,
            'A clear operator reason is required before updating carrier credentials.'
        );
        $beforeState = $this->carrierSettingsService->credentialStateForAudit($carrierCode);
        $updatedFields = $this->carrierSettingsService->storeCredentialUpdates(
            $carrierCode,
            $payload,
            (string) $actor->id,
        );
        $afterState = $this->carrierSettingsService->credentialStateForAudit($carrierCode);

        $this->auditService->warning(
            null,
            (string) $actor->id,
            'carrier.credentials_updated',
            'carrier',
            'SystemSetting',
            $carrierCode,
            $beforeState,
            $afterState,
            [
                'reason' => $normalizedReason,
                'carrier_code' => $carrierCode,
                'carrier_label' => $carrier['label'],
                'updated_fields' => $updatedFields,
                'action_scope' => 'internal_carriers_center',
            ]
        );

        return [
            'updated_fields' => $updatedFields,
            'connection_log' => $this->performConnectionTest(
                $carrierCode,
                $actor,
                'carrier_credentials_update'
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{rotated_fields: array<int, string>, connection_log: IntegrationHealthLog}
     */
    public function rotateCredentials(string $carrierCode, User $actor, array $payload, string $reason): array
    {
        $this->assertCanManageCarriers($actor);

        $carrier = $this->carrierDefinition($carrierCode);
        if (! $this->carrierSettingsService->supportsRotation($carrierCode)) {
            throw new BusinessException(
                'This carrier does not expose a safe credential rotation contract in the internal portal.',
                'ERR_CARRIER_ROTATION_UNSUPPORTED',
                422
            );
        }

        $normalizedReason = $this->normalizeReason(
            $reason,
            'A clear operator reason is required before rotating carrier credentials.'
        );
        $beforeState = $this->carrierSettingsService->credentialStateForAudit($carrierCode);
        $rotatedFields = $this->carrierSettingsService->storeCredentialUpdates(
            $carrierCode,
            $payload,
            (string) $actor->id,
            $this->carrierSettingsService->rotationFieldNames($carrierCode),
        );
        $afterState = $this->carrierSettingsService->credentialStateForAudit($carrierCode);

        $this->auditService->warning(
            null,
            (string) $actor->id,
            'carrier.credentials_rotated',
            'carrier',
            'SystemSetting',
            $carrierCode,
            $beforeState,
            $afterState,
            [
                'reason' => $normalizedReason,
                'carrier_code' => $carrierCode,
                'carrier_label' => $carrier['label'],
                'rotated_fields' => $rotatedFields,
                'action_scope' => 'internal_carriers_center',
            ]
        );

        return [
            'rotated_fields' => $rotatedFields,
            'connection_log' => $this->performConnectionTest(
                $carrierCode,
                $actor,
                'carrier_credentials_rotation'
            ),
        ];
    }

    private function assertCanManageCarriers(User $actor): void
    {
        if (! $actor->hasPermission('integrations.manage')) {
            throw BusinessException::permissionDenied();
        }
    }

    private function carrierDefinition(string $carrierCode): array
    {
        return $this->carrierSettingsService->carrierDefinition($carrierCode);
    }

    private function normalizeReason(string $reason, string $message): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');

        if ($normalized === '') {
            throw new BusinessException(
                $message,
                'ERR_CARRIER_REASON_REQUIRED',
                422
            );
        }

        return $normalized;
    }

    private function performConnectionTest(string $carrierCode, User $actor, string $source): IntegrationHealthLog
    {
        $this->assertCanManageCarriers($actor);

        $carrier = $this->carrierDefinition($carrierCode);
        $this->carrierSettingsService->applyRuntimeConfig($carrierCode);

        $startTime = microtime(true);
        $config = $this->carrierSettingsService->runtimeConfig($carrierCode);
        $missingFields = $this->missingFields($config, $carrier['required_fields']);
        $status = $missingFields === []
            ? IntegrationHealthLog::STATUS_HEALTHY
            : IntegrationHealthLog::STATUS_DEGRADED;
        $responseTime = max(1, (int) round((microtime(true) - $startTime) * 1000));
        $errorMessage = $missingFields === []
            ? null
            : 'Missing required config: ' . implode(', ', $missingFields);

        $log = IntegrationHealthLog::query()->create([
            'service' => 'carrier:' . $carrierCode,
            'status' => $status,
            'response_time_ms' => $responseTime,
            'total_requests' => 1,
            'failed_requests' => $missingFields === [] ? 0 : 1,
            'error_rate' => $missingFields === [] ? 0.0 : 100.0,
            'error_message' => $errorMessage,
            'checked_at' => now(),
            'metadata' => [
                'check_source' => $source,
                'check_type' => 'manual_configuration_health_check',
                'carrier_code' => $carrierCode,
                'carrier_label' => $carrier['label'],
                'required_field_count' => count($carrier['required_fields']),
                'missing_field_count' => count($missingFields),
                'carrier_enabled' => FeatureFlag::runtimeEnabled($carrier['flag_key']),
            ],
        ]);

        $this->auditService->info(
            null,
            (string) $actor->id,
            'admin.carrier_tested',
            'admin',
            'IntegrationHealthLog',
            (string) $log->id,
            null,
            [
                'service' => (string) $log->service,
                'status' => (string) $log->status,
                'response_time_ms' => (int) $log->response_time_ms,
                'checked_at' => optional($log->checked_at)?->toIso8601String(),
            ],
            [
                'carrier_code' => $carrierCode,
                'carrier_label' => $carrier['label'],
                'carrier_enabled' => FeatureFlag::runtimeEnabled($carrier['flag_key']),
                'missing_field_count' => count($missingFields),
                'check_source' => $source,
                'internal_scope' => 'internal_carriers_center',
            ]
        );

        return $log;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, string> $requiredFields
     * @return array<int, string>
     */
    private function missingFields(array $config, array $requiredFields): array
    {
        return collect($requiredFields)
            ->filter(static fn (string $field): bool => trim((string) ($config[$field] ?? '')) === '')
            ->map(static fn (string $field): string => Str::headline(str_replace('_', ' ', $field)))
            ->values()
            ->all();
    }

}
