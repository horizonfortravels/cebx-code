<?php

namespace App\Services;

use App\Models\Account;
use App\Models\KycVerification;
use App\Models\Shipment;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Support\Collection;

class InternalKycOperationalEffectService
{
    /**
     * @param array<string, mixed>|null $capabilities
     * @return array{
     *   shipping_state: string,
     *   shipping_label: string,
     *   shipping_detail: string,
     *   international_blocked: bool,
     *   international_label: string,
     *   international_detail: string,
     *   further_action_required: bool,
     *   action_label: string,
     *   action_detail: string,
     *   shipping_limit: int|null,
     *   daily_shipment_limit: int|null,
     *   blocked_shipments_count: int,
     *   queue_summary: string
     * }
     */
    public function summarize(
        Account $account,
        ?string $status = null,
        ?array $capabilities = null,
        int $blockedShipmentsCount = 0,
    ): array {
        $resolvedStatus = trim((string) (
            $status
            ?: $account->kycVerification?->status
            ?: AccountKycStatusMapper::toVerificationStatus((string) ($account->kyc_status ?? ''))
        ));

        $resolvedCapabilities = $capabilities;

        if ($resolvedCapabilities === null || $resolvedCapabilities === []) {
            $resolvedCapabilities = $account->kycVerification?->capabilities()
                ?? $this->fallbackCapabilities($resolvedStatus);
        }

        $shippingLimit = $this->normalizeLimit($resolvedCapabilities['shipping_limit'] ?? null);
        $dailyShipmentLimit = $this->normalizeLimit($resolvedCapabilities['daily_shipment_limit'] ?? null);
        $internationalBlocked = !($resolvedCapabilities['can_ship_international'] ?? false);

        [$shippingState, $shippingLabel, $shippingDetail] = $this->shippingStateSummary(
            $blockedShipmentsCount,
            $shippingLimit,
            $dailyShipmentLimit,
        );

        $actionRequired = $resolvedStatus !== KycVerification::STATUS_APPROVED;

        return [
            'shipping_state' => $shippingState,
            'shipping_label' => $shippingLabel,
            'shipping_detail' => $shippingDetail,
            'international_blocked' => $internationalBlocked,
            'international_label' => $internationalBlocked ? 'معلّق' : 'مسموح',
            'international_detail' => $internationalBlocked
                ? 'الشحن الدولي غير متاح في الحالة الحالية لهذا الحساب.'
                : 'الشحن الدولي متاح وفق حالة KYC الحالية.',
            'further_action_required' => $actionRequired,
            'action_label' => $actionRequired ? 'مطلوب' : 'غير مطلوب',
            'action_detail' => $this->nextActionSummary($resolvedStatus, $blockedShipmentsCount),
            'shipping_limit' => $shippingLimit,
            'daily_shipment_limit' => $dailyShipmentLimit,
            'blocked_shipments_count' => $blockedShipmentsCount,
            'queue_summary' => $this->queueSummary(
                $blockedShipmentsCount,
                $shippingLimit,
                $dailyShipmentLimit,
                $internationalBlocked,
            ),
        ];
    }

    /**
     * @return Collection<int, array{reference: string, status: string, created_at: string|null}>
     */
    public function recentImpactedShipments(Account $account, int $limit = 5): Collection
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->where('status', Shipment::STATUS_KYC_BLOCKED)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Shipment $shipment): array {
                return [
                    'reference' => (string) ($shipment->tracking_number ?: $shipment->reference_number ?: $shipment->id),
                    'status' => (string) $shipment->status,
                    'created_at' => optional($shipment->created_at)->format('Y-m-d H:i'),
                ];
            })
            ->values();
    }

    private function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT);

        if ($normalized === false) {
            return null;
        }

        return max(0, (int) $normalized);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function shippingStateSummary(int $blockedShipmentsCount, ?int $shippingLimit, ?int $dailyShipmentLimit): array
    {
        if ($blockedShipmentsCount > 0) {
            return [
                'blocked',
                'متأثر حاليًا',
                'توجد شحنات محجوبة حاليًا بسبب بوابة KYC التشغيلية الحالية.',
            ];
        }

        if ($shippingLimit !== null || $dailyShipmentLimit !== null) {
            $parts = [];

            if ($shippingLimit !== null) {
                $parts[] = 'حد الشحن الكلي: ' . number_format($shippingLimit);
            }

            if ($dailyShipmentLimit !== null) {
                $parts[] = 'الحد اليومي: ' . number_format($dailyShipmentLimit);
            }

            return [
                'limited',
                'مسموح بحدود',
                implode(' • ', $parts),
            ];
        }

        return [
            'clear',
            'بدون قيود تشغيلية ظاهرة',
            'لا توجد حدود تشغيلية ظاهرة على إنشاء الشحنات من منظور KYC الحالي.',
        ];
    }

    private function nextActionSummary(string $status, int $blockedShipmentsCount): string
    {
        if ($status === KycVerification::STATUS_APPROVED) {
            return $blockedShipmentsCount > 0
                ? 'راجع الشحنات المحجوبة الحالية وتحقق من انتقالها بعد اعتماد KYC.'
                : 'لا يلزم إجراء KYC إضافي قبل متابعة الشحن حاليًا.';
        }

        return match ($status) {
            KycVerification::STATUS_PENDING => 'ينتظر هذا الحساب اكتمال المراجعة الداخلية قبل استعادة كامل السعة التشغيلية.',
            KycVerification::STATUS_REJECTED => 'يلزم تصحيح الملاحظات وإعادة تقديم مستندات KYC قبل إزالة القيود التشغيلية.',
            KycVerification::STATUS_EXPIRED => 'يلزم تجديد ملف KYC قبل استعادة كامل القدرة التشغيلية.',
            default => 'يلزم استكمال متطلبات KYC قبل استعادة كامل القدرة التشغيلية للشحن.',
        };
    }

    private function queueSummary(
        int $blockedShipmentsCount,
        ?int $shippingLimit,
        ?int $dailyShipmentLimit,
        bool $internationalBlocked,
    ): string {
        if ($blockedShipmentsCount > 0) {
            return 'محجوب الآن • شحنات محجوبة: ' . number_format($blockedShipmentsCount);
        }

        if ($dailyShipmentLimit !== null) {
            return 'محدود يوميًا: ' . number_format($dailyShipmentLimit);
        }

        if ($shippingLimit !== null) {
            return 'محدود تشغيليًا: ' . number_format($shippingLimit);
        }

        if ($internationalBlocked) {
            return 'الدولي معلّق حاليًا';
        }

        return 'بدون قيود تشغيلية ظاهرة';
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackCapabilities(string $status): array
    {
        return match ($status) {
            KycVerification::STATUS_APPROVED => [
                'can_ship_international' => true,
                'shipping_limit' => null,
                'daily_shipment_limit' => null,
            ],
            KycVerification::STATUS_PENDING => [
                'can_ship_international' => false,
                'shipping_limit' => 50,
                'daily_shipment_limit' => 10,
            ],
            KycVerification::STATUS_REJECTED => [
                'can_ship_international' => false,
                'shipping_limit' => 10,
                'daily_shipment_limit' => 5,
            ],
            default => [
                'can_ship_international' => false,
                'shipping_limit' => 5,
                'daily_shipment_limit' => 3,
            ],
        };
    }
}
