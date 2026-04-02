<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\KycVerification;
use App\Models\User;
use App\Models\VerificationRestriction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class InternalKycRestrictionAdminService
{
    public const FEATURE_INTERNATIONAL_SHIPPING = 'international_shipping';
    public const FEATURE_SHIPPING_LIMIT = 'shipping_limit';
    public const FEATURE_DAILY_SHIPMENT_LIMIT = 'daily_shipment_limit';

    /**
     * @var array<int, string>
     */
    private const MUTABLE_STATUSES = [
        KycVerification::STATUS_UNVERIFIED,
        KycVerification::STATUS_PENDING,
        KycVerification::STATUS_REJECTED,
        KycVerification::STATUS_EXPIRED,
    ];

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * @return array<int, string>
     */
    public static function mutableStatuses(): array
    {
        return self::MUTABLE_STATUSES;
    }

    /**
     * @return array<int, string>
     */
    public static function manageableFeatures(): array
    {
        return [
            self::FEATURE_INTERNATIONAL_SHIPPING,
            self::FEATURE_SHIPPING_LIMIT,
            self::FEATURE_DAILY_SHIPMENT_LIMIT,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function controlStatesForStatus(string $status): Collection
    {
        if (!$this->restrictionsTableAvailable()) {
            return collect();
        }

        return collect(self::manageableFeatures())
            ->map(function (string $featureKey) use ($status): array {
                $definition = $this->featureDefinition($featureKey, $status);
                $existing = VerificationRestriction::query()
                    ->where('restriction_key', $definition['restriction_key'])
                    ->first();

                return [
                    'feature_key' => $featureKey,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'type' => $definition['type'],
                    'restriction_key' => $definition['restriction_key'],
                    'is_active' => (bool) ($existing?->is_active ?? false),
                    'quota_value' => $existing?->quota_value !== null ? (int) $existing->quota_value : null,
                    'current_summary' => $this->controlSummary($definition['type'], $existing),
                ];
            })
            ->values();
    }

    public function syncRestriction(
        string $accountId,
        string $status,
        string $featureKey,
        array $payload,
        User $actor,
    ): ?VerificationRestriction {
        $this->assertMutationIsAllowed($actor, $status);

        $account = Account::query()->withoutGlobalScopes()->findOrFail($accountId);
        $definition = $this->featureDefinition($featureKey, $status);
        $mode = (string) ($payload['mode'] ?? '');
        $note = trim((string) ($payload['note'] ?? ''));

        $restriction = VerificationRestriction::query()
            ->where('restriction_key', $definition['restriction_key'])
            ->first();

        $oldValues = $this->snapshot($restriction);

        if ($definition['type'] === VerificationRestriction::TYPE_BLOCK_FEATURE) {
            $restriction = $this->syncBlockRestriction($definition, $restriction, $mode);
        } else {
            $restriction = $this->syncQuotaRestriction($definition, $restriction, $mode, $payload['quota_value'] ?? null);
        }

        if (!$restriction instanceof VerificationRestriction) {
            return null;
        }

        $action = $restriction->is_active ? 'kyc.restriction_updated' : 'kyc.restriction_disabled';
        $severityLogger = $restriction->is_active ? 'warning' : 'info';

        $this->auditService->{$severityLogger}(
            (string) $account->id,
            (string) $actor->id,
            $action,
            AuditLog::CATEGORY_KYC,
            'VerificationRestriction',
            (string) $restriction->id,
            $oldValues,
            $this->snapshot($restriction),
            [
                'scope' => 'verification_status_policy_overlay',
                'feature_key' => $featureKey,
                'applies_to_status' => $status,
                'note' => $note !== '' ? $note : null,
            ]
        );

        return $restriction->fresh();
    }

    /**
     * @return array{label: string, description: string, type: string, restriction_key: string, name: string, feature_key: string, status: string}
     */
    private function featureDefinition(string $featureKey, string $status): array
    {
        return match ($featureKey) {
            self::FEATURE_INTERNATIONAL_SHIPPING => [
                'label' => 'تعليق الشحن الدولي',
                'description' => 'حظر تشغيلي على الشحن الدولي للحسابات المطابقة لحالة التحقق الحالية فقط.',
                'type' => VerificationRestriction::TYPE_BLOCK_FEATURE,
                'restriction_key' => $this->restrictionKey($status, $featureKey),
                'name' => 'تعليق الشحن الدولي',
                'feature_key' => $featureKey,
                'status' => $status,
            ],
            self::FEATURE_SHIPPING_LIMIT => [
                'label' => 'حد الشحن الكلي',
                'description' => 'حد عددي تشغيلي لعدد الشحنات المسموح بها ضمن حالة التحقق الحالية.',
                'type' => VerificationRestriction::TYPE_QUOTA_LIMIT,
                'restriction_key' => $this->restrictionKey($status, $featureKey),
                'name' => 'حد الشحن الكلي',
                'feature_key' => $featureKey,
                'status' => $status,
            ],
            self::FEATURE_DAILY_SHIPMENT_LIMIT => [
                'label' => 'حد الشحن اليومي',
                'description' => 'حد عددي يومي للحسابات المطابقة لحالة التحقق الحالية.',
                'type' => VerificationRestriction::TYPE_QUOTA_LIMIT,
                'restriction_key' => $this->restrictionKey($status, $featureKey),
                'name' => 'حد الشحن اليومي',
                'feature_key' => $featureKey,
                'status' => $status,
            ],
            default => throw new BusinessException(
                'نوع القيد المطلوب غير مدعوم في مركز عمليات KYC الحالي.',
                'ERR_KYC_RESTRICTION_UNSUPPORTED',
                422
            ),
        };
    }

    private function restrictionKey(string $status, string $featureKey): string
    {
        return sprintf('kyc_%s_%s', $status, $featureKey);
    }

    private function restrictionsTableAvailable(): bool
    {
        return Schema::hasTable('verification_restrictions');
    }

    private function assertMutationIsAllowed(User $actor, string $status): void
    {
        if (!$this->restrictionsTableAvailable()) {
            throw new BusinessException(
                'نموذج قيود التحقق غير متاح في البيئة الحالية.',
                'ERR_KYC_RESTRICTIONS_UNAVAILABLE',
                422
            );
        }

        if (!$actor->hasPermission('kyc.manage')) {
            throw BusinessException::permissionDenied();
        }

        if (!in_array($status, self::MUTABLE_STATUSES, true)) {
            throw new BusinessException(
                'لا يسمح هذا المسار بتعديل قيود التحقق إلا للحالات غير الموثقة أو الخطرة.',
                'ERR_KYC_RESTRICTION_STATUS_LOCKED',
                422
            );
        }
    }

    private function syncBlockRestriction(array $definition, ?VerificationRestriction $restriction, string $mode): VerificationRestriction
    {
        if (!in_array($mode, ['enable', 'disable'], true)) {
            throw new BusinessException(
                'إجراء القيد المطلوب غير صالح لهذا النوع من القيود.',
                'ERR_KYC_RESTRICTION_MODE_INVALID',
                422
            );
        }

        $values = [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'applies_to_statuses' => [$definition['status']],
            'restriction_type' => VerificationRestriction::TYPE_BLOCK_FEATURE,
            'feature_key' => $definition['feature_key'],
            'quota_value' => null,
            'is_active' => $mode === 'enable',
        ];

        if ($restriction instanceof VerificationRestriction) {
            $restriction->fill($values)->save();

            return $restriction;
        }

        if ($mode === 'disable') {
            throw new BusinessException(
                'لا يوجد قيد نشط لتعطيله ضمن حالة التحقق الحالية.',
                'ERR_KYC_RESTRICTION_NOT_FOUND',
                404
            );
        }

        return VerificationRestriction::query()->create(array_merge($values, [
            'restriction_key' => $definition['restriction_key'],
        ]));
    }

    private function syncQuotaRestriction(
        array $definition,
        ?VerificationRestriction $restriction,
        string $mode,
        mixed $quotaValue,
    ): VerificationRestriction {
        if (!in_array($mode, ['set', 'clear'], true)) {
            throw new BusinessException(
                'إجراء القيد المطلوب غير صالح لهذا النوع من القيود.',
                'ERR_KYC_RESTRICTION_MODE_INVALID',
                422
            );
        }

        $resolvedQuota = $mode === 'set' ? $this->normalizeQuotaValue($quotaValue) : null;

        $values = [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'applies_to_statuses' => [$definition['status']],
            'restriction_type' => VerificationRestriction::TYPE_QUOTA_LIMIT,
            'feature_key' => $definition['feature_key'],
            'quota_value' => $resolvedQuota,
            'is_active' => $mode === 'set',
        ];

        if ($restriction instanceof VerificationRestriction) {
            $restriction->fill($values)->save();

            return $restriction;
        }

        if ($mode === 'clear') {
            throw new BusinessException(
                'لا يوجد قيد رقمي نشط لإزالته ضمن حالة التحقق الحالية.',
                'ERR_KYC_RESTRICTION_NOT_FOUND',
                404
            );
        }

        return VerificationRestriction::query()->create(array_merge($values, [
            'restriction_key' => $definition['restriction_key'],
        ]));
    }

    private function normalizeQuotaValue(mixed $value): int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($normalized === false) {
            throw new BusinessException(
                'يجب إدخال قيمة عددية صحيحة أكبر من صفر لهذا القيد.',
                'ERR_KYC_RESTRICTION_QUOTA_INVALID',
                422
            );
        }

        return (int) $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshot(?VerificationRestriction $restriction): ?array
    {
        if (!$restriction instanceof VerificationRestriction) {
            return null;
        }

        return [
            'restriction_key' => $restriction->restriction_key,
            'feature_key' => $restriction->feature_key,
            'restriction_type' => $restriction->restriction_type,
            'quota_value' => $restriction->quota_value,
            'is_active' => (bool) $restriction->is_active,
            'applies_to_statuses' => $restriction->applies_to_statuses ?? [],
        ];
    }

    private function controlSummary(string $type, ?VerificationRestriction $restriction): string
    {
        if (!$restriction instanceof VerificationRestriction || !$restriction->is_active) {
            return 'لا يوجد قيد مخصص نشط لهذا المفتاح.';
        }

        if ($type === VerificationRestriction::TYPE_BLOCK_FEATURE) {
            return 'القيد مفعل على حالة التحقق الحالية.';
        }

        return 'القيمة الحالية: ' . number_format((int) ($restriction->quota_value ?? 0));
    }
}
