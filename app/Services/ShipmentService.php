<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\BillingWallet;
use App\Models\KycVerification;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletHold;
use App\Exceptions\BusinessException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ShipmentService — FR-SH-001→019 (19 requirements)
 *
 * FR-SH-001: Direct shipping
 * FR-SH-002: Order→Shipment
 * FR-SH-003: Multi-parcel
 * FR-SH-004: Address book
 * FR-SH-005: Validation
 * FR-SH-006: State machine
 * FR-SH-007: Cancel/Void
 * FR-SH-008: Reprint label
 * FR-SH-009: Search & filter
 * FR-SH-010: Bulk creation
 * FR-SH-011: Financial visibility (RBAC)
 * FR-SH-012: Print permissions
 * FR-SH-013: KYC check before purchase
 * FR-SH-014: Balance reservation
 * FR-SH-015: Ledger entries for charges/refunds
 * FR-SH-016: Return shipments
 * FR-SH-017: Dangerous goods flag
 * FR-SH-018: DG declaration status display
 * FR-SH-019: COD support
 */
class ShipmentService
{
    public function __construct(
        protected AuditService $auditService,
        protected BillingWalletService $billingWalletService,
        protected DgComplianceService $dgComplianceService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-001: Direct Shipping (create from scratch)
    // ═══════════════════════════════════════════════════════════════

    public function createDirect(string $accountId, array $data, User $performer): Shipment
    {
        $this->assertCanCreateShipmentDraft($performer);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($accountId, $data, $performer) {
                    $shipment = Shipment::create(
                        $this->filterShipmentAttributes(
                            $this->buildShipmentDraftAttributes($accountId, $data, $performer)
                        )
                    );

                    // Create parcels (FR-SH-003)
                    $this->createParcels($shipment, $data['parcels'] ?? [['weight' => $data['weight'] ?? 0.5]]);

                    // Record initial status
                    $this->recordStatusChange($shipment, null, Shipment::STATUS_DRAFT, 'system', $performer->id, 'Shipment created');

                    $this->auditService->info(
                        $accountId, $performer->id,
                        'shipment.created', AuditLog::CATEGORY_ACCOUNT,
                        'Shipment', $shipment->id,
                        null,
                        ['source' => 'direct', 'reference' => $shipment->reference_number]
                    );

                    return $shipment->fresh(['parcels']);
                });
            } catch (QueryException $exception) {
                if (! Shipment::isReferenceConflict($exception) || $attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('استنفدت إعادة المحاولة لتوليد مرجع مسودة الشحنة.');
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-002: Order → Shipment
    // ═══════════════════════════════════════════════════════════════

    public function createFromOrder(string $accountId, string $orderId, array $overrides, User $performer): Shipment
    {
        $this->assertCanCreateShipmentDraft($performer);

        $order = Order::where('account_id', $accountId)
            ->where('id', $orderId)
            ->with('items')
            ->firstOrFail();

        if (!$order->isShippable()) {
            throw new BusinessException('الطلب غير جاهز للشحن.', 'ERR_ORDER_NOT_SHIPPABLE', 422);
        }

        if ($order->hasShipment()) {
            throw new BusinessException('الطلب مرتبط بشحنة بالفعل.', 'ERR_ORDER_HAS_SHIPMENT', 422);
        }

        // Get default sender address
        $senderAddr = Address::where('account_id', $accountId)->defaultSender()->first();

        $data = array_merge([
            'store_id'            => $order->store_id,
            'sender_name'         => $senderAddr->contact_name ?? $overrides['sender_name'] ?? '',
            'sender_phone'        => $senderAddr->phone ?? $overrides['sender_phone'] ?? '',
            'sender_address_1'    => $senderAddr->address_line_1 ?? $overrides['sender_address_1'] ?? '',
            'sender_city'         => $senderAddr->city ?? $overrides['sender_city'] ?? '',
            'sender_country'      => $senderAddr->country ?? $overrides['sender_country'] ?? 'SA',
            'sender_address_id'   => $senderAddr->id ?? null,
            'recipient_name'      => $order->shipping_name ?? $order->customer_name,
            'recipient_phone'     => $order->shipping_phone ?? $order->customer_phone,
            'recipient_email'     => $order->customer_email,
            'recipient_address_1' => $order->shipping_address_line_1,
            'recipient_address_2' => $order->shipping_address_line_2,
            'recipient_city'      => $order->shipping_city,
            'recipient_state'     => $order->shipping_state,
            'recipient_postal_code' => $order->shipping_postal_code,
            'recipient_country'   => $order->shipping_country,
            'weight'              => $order->total_weight ?? 0.5,
        ], $overrides);

        return DB::transaction(function () use ($accountId, $data, $performer, $order) {
            $shipment = $this->createDirect($accountId, $data, $performer);

            // Link order ↔ shipment
            $shipment->update(['order_id' => $order->id, 'source' => Shipment::SOURCE_ORDER]);
            $order->update(['shipment_id' => $shipment->id, 'status' => Order::STATUS_PROCESSING]);

            $this->auditService->info(
                $accountId, $performer->id,
                'shipment.created_from_order', AuditLog::CATEGORY_ACCOUNT,
                'Shipment', $shipment->id,
                null,
                ['order_id' => $order->id, 'reference' => $shipment->reference_number]
            );

            return $shipment->fresh(['parcels', 'order']);
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-005: Validation
    // ═══════════════════════════════════════════════════════════════

    public function validateShipment(string $accountId, string $shipmentId, User $performer): Shipment
    {
        $this->assertCanUpdateShipmentDraft($performer);

        $shipment = $this->findShipment($accountId, $shipmentId);

        if (!in_array($shipment->status, [
            Shipment::STATUS_DRAFT,
            Shipment::STATUS_VALIDATED,
            Shipment::STATUS_KYC_BLOCKED,
        ], true)) {
            throw new BusinessException(
                'لا يمكن تجهيز هذه الشحنة للتسعير من حالتها الحالية.',
                'ERR_INVALID_STATE',
                422,
                [
                    'shipment_id' => $shipment->id,
                    'current_status' => $shipment->status,
                    'allowed_statuses' => [
                        Shipment::STATUS_DRAFT,
                        Shipment::STATUS_VALIDATED,
                        Shipment::STATUS_KYC_BLOCKED,
                    ],
                ]
            );
        }

        $errors = $this->runValidation($shipment);

        if (!empty($errors)) {
            throw new BusinessException(
                'تعذر متابعة الشحنة قبل تصحيح بيانات الطلب.',
                'ERR_VALIDATION_FAILED',
                422,
                [
                    'shipment_id' => $shipment->id,
                    'current_status' => Shipment::STATUS_DRAFT,
                    'next_action' => 'صحح بيانات المرسل أو المستلم أو الطرود، ثم أعد محاولة التحقق.',
                    'validation_errors' => $errors,
                ]
            );
        }

        if ($shipment->status !== Shipment::STATUS_VALIDATED) {
            $this->transitionStatus($shipment, Shipment::STATUS_VALIDATED, 'system', $performer->id, 'Validation passed');
            $shipment = $shipment->fresh(['parcels', 'account.kycVerification']);
        }

        $kycGate = $this->evaluateKycRestrictionGate($shipment);
        if (!$kycGate['allowed']) {
            if ($shipment->status !== Shipment::STATUS_KYC_BLOCKED) {
                $this->transitionStatus(
                    $shipment,
                    Shipment::STATUS_KYC_BLOCKED,
                    'system',
                    $performer->id,
                    $kycGate['message']
                );
            } else {
                $this->updateShipmentCompat($shipment, ['status_reason' => $kycGate['message']]);
            }

            $this->updateShipmentCompat($shipment, ['kyc_verified' => false]);

            throw new BusinessException(
                $kycGate['message'],
                $kycGate['error_code'],
                422,
                [
                    'shipment_id' => $shipment->id,
                    'current_status' => Shipment::STATUS_KYC_BLOCKED,
                    'kyc_status' => $kycGate['kyc_status'],
                    'reason_code' => $kycGate['reason_code'],
                    'next_action' => $kycGate['next_action'],
                    'capabilities' => $kycGate['capabilities'],
                ]
            );
        }

        if ($shipment->status !== Shipment::STATUS_READY_FOR_RATES) {
            $this->transitionStatus($shipment, Shipment::STATUS_READY_FOR_RATES, 'system', $performer->id, 'Shipment cleared for rate fetching');
        }

        $this->updateShipmentCompat($shipment, [
            'kyc_verified' => $kycGate['kyc_status'] === 'verified',
            'status_reason' => null,
        ]);

        return $shipment->fresh();
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createWalletPreflightReservation(
        string $accountId,
        string $shipmentId,
        User $performer,
        array $options = []
    ): array {
        if (! $performer->hasPermission('billing.manage')) {
            throw BusinessException::permissionDenied();
        }

        return DB::transaction(function () use ($accountId, $shipmentId, $performer, $options): array {
            /** @var Shipment $shipment */
            $shipment = Shipment::query()
                ->where('account_id', $accountId)
                ->where('id', $shipmentId)
                ->with(['selectedRateOption', 'balanceReservation'])
                ->lockForUpdate()
                ->firstOrFail();

            $selectedOption = $shipment->selectedRateOption;
            if ($selectedOption === null) {
                throw new BusinessException(
                    'يجب اختيار عرض شحنة قبل طلب الحجز المسبق للمحفظة.',
                    'ERR_SELECTED_OFFER_REQUIRED',
                    422,
                    [
                        'shipment_id' => (string) $shipment->id,
                        'current_status' => (string) $shipment->status,
                        'next_action' => 'اختر عرضًا واحدًا للشحنة قبل المتابعة إلى الحجز المسبق للمحفظة.',
                    ]
                );
            }

            $this->dgComplianceService->validateForIssuance((string) $shipment->id, $accountId);

            if (! in_array((string) $shipment->status, [
                Shipment::STATUS_DECLARATION_COMPLETE,
                Shipment::STATUS_PAYMENT_PENDING,
            ], true)) {
                throw new BusinessException(
                    'هذه الشحنة ليست في الحالة الصحيحة للحجز المسبق للمحفظة.',
                    'ERR_INVALID_STATE',
                    422,
                    [
                        'shipment_id' => (string) $shipment->id,
                        'current_status' => (string) $shipment->status,
                        'allowed_statuses' => [
                            Shipment::STATUS_DECLARATION_COMPLETE,
                            Shipment::STATUS_PAYMENT_PENDING,
                        ],
                        'next_action' => 'أكمل الإقرار واختيار العرض قبل طلب الحجز المسبق للمحفظة.',
                    ]
                );
            }

            $amount = $this->resolvePreflightAmount($shipment);
            $currency = $this->resolvePreflightCurrency($shipment);

            /** @var BillingWallet|null $wallet */
            $wallet = BillingWallet::query()
                ->where('account_id', $accountId)
                ->where('currency', $currency)
                ->first();

            if (! $wallet instanceof BillingWallet) {
                throw new BusinessException(
                    'لا توجد محفظة متاحة لعملة الشحنة المحددة.',
                    'ERR_WALLET_NOT_AVAILABLE',
                    422,
                    [
                        'shipment_id' => (string) $shipment->id,
                        'currency' => $currency,
                        'next_action' => 'أنشئ محفظة بعملة الشحنة أو موّلها قبل المتابعة.',
                    ]
                );
            }

            if (! $wallet->isActive()) {
                throw BusinessException::walletFrozen();
            }

            $existingHold = $this->resolveExistingActiveReservation($shipment, (string) $wallet->id);
            if ($existingHold instanceof WalletHold) {
                if (round((float) $existingHold->amount, 2) !== round($amount, 2)) {
                    throw new BusinessException(
                        'لم يعد الحجز الحالي يطابق إجمالي العرض المحدد.',
                        'ERR_PREFLIGHT_AMOUNT_MISMATCH',
                        409,
                        [
                            'shipment_id' => (string) $shipment->id,
                            'existing_hold_id' => (string) $existingHold->id,
                            'existing_amount' => (float) $existingHold->amount,
                            'expected_amount' => $amount,
                            'currency' => $currency,
                            'next_action' => 'حرر الحجز القديم قبل إنشاء حجز مسبق جديد للمحفظة.',
                        ]
                    );
                }

                $this->syncShipmentReservation($shipment, $existingHold, $performer, false);

                return $this->formatWalletPreflightResult(
                    $shipment->fresh(['selectedRateOption', 'rateQuote', 'balanceReservation']),
                    $existingHold->fresh(),
                    $wallet->fresh(),
                    false
                );
            }

            $correlationId = trim((string) ($options['correlation_id'] ?? AuditService::getRequestId()));
            $idempotencyKey = trim((string) ($options['idempotency_key'] ?? ''));
            if ($idempotencyKey === '') {
                $idempotencyKey = 'shipment-preflight-' . $shipment->id . '-' . $correlationId;
            }

            try {
                $hold = $this->billingWalletService->createHold(
                    (string) $wallet->id,
                    (string) $shipment->id,
                    $amount,
                    $idempotencyKey,
                    [
                        'account_id' => $accountId,
                        'currency' => $currency,
                        'source' => 'shipment_preflight',
                        'correlation_id' => $correlationId,
                        'actor_id' => (string) $performer->id,
                    ]
                );
            } catch (\RuntimeException $exception) {
                if (str_contains($exception->getMessage(), 'ERR_INSUFFICIENT_BALANCE')) {
                    throw new BusinessException(
                        'رصيد المحفظة غير كافٍ لعرض الشحنة المحدد.',
                        'ERR_INSUFFICIENT_BALANCE',
                        422,
                        [
                            'shipment_id' => (string) $shipment->id,
                            'wallet_id' => (string) $wallet->id,
                            'required_amount' => $amount,
                            'currency' => $currency,
                            'effective_balance' => $wallet->fresh()->getEffectiveBalance(),
                            'next_action' => 'اشحن المحفظة أو اختر عرضًا أقل تكلفة قبل المتابعة.',
                        ]
                    );
                }

                if (str_contains($exception->getMessage(), 'ERR_HOLD_ALREADY_EXISTS')) {
                    throw new BusinessException(
                        'يوجد حجز محفظة نشط بالفعل لهذه الشحنة.',
                        'ERR_HOLD_ALREADY_EXISTS',
                        409,
                        [
                            'shipment_id' => (string) $shipment->id,
                            'wallet_id' => (string) $wallet->id,
                        ]
                    );
                }

                if (str_contains($exception->getMessage(), 'ERR_WALLET_FROZEN')) {
                    throw BusinessException::walletFrozen();
                }

                throw $exception;
            }

            $this->syncShipmentReservation($shipment, $hold, $performer, true);

            return $this->formatWalletPreflightResult(
                $shipment->fresh(['selectedRateOption', 'rateQuote', 'balanceReservation']),
                $hold->fresh(),
                $wallet->fresh(),
                true
            );
        });
    }

    private function runValidation(Shipment $shipment): array
    {
        $errors = [
            'sender' => [],
            'recipient' => [],
            'routing' => [],
            'parcels' => [],
        ];

        // Sender validation
        if (empty($shipment->sender_name))      $errors['sender'][] = 'اسم المرسل مطلوب.';
        if (empty($shipment->sender_phone))     $errors['sender'][] = 'هاتف المرسل مطلوب.';
        if (empty($this->shipmentAttribute($shipment, 'sender_address_1', 'sender_address'))) $errors['sender'][] = 'عنوان المرسل مطلوب.';
        if (empty($shipment->sender_city))      $errors['sender'][] = 'مدينة المرسل مطلوبة.';
        if (empty($this->shipmentAttribute($shipment, 'sender_country')))   $errors['sender'][] = 'دولة المرسل مطلوبة.';

        // Recipient validation
        if (empty($shipment->recipient_name))      $errors['recipient'][] = 'اسم المستلم مطلوب.';
        if (empty($shipment->recipient_phone))     $errors['recipient'][] = 'هاتف المستلم مطلوب.';
        if (empty($this->shipmentAttribute($shipment, 'recipient_address_1', 'recipient_address'))) $errors['recipient'][] = 'عنوان المستلم مطلوب.';
        if (empty($shipment->recipient_city))      $errors['recipient'][] = 'مدينة المستلم مطلوبة.';
        if (empty($shipment->recipient_country))   $errors['recipient'][] = 'دولة المستلم مطلوبة.';

        foreach ([
            'sender' => [
                'phone' => (string) $shipment->sender_phone,
                'country' => (string) $this->shipmentAttribute($shipment, 'sender_country'),
                'postal_code' => (string) ($this->shipmentAttribute($shipment, 'sender_postal_code') ?? ''),
            ],
            'recipient' => [
                'phone' => (string) $shipment->recipient_phone,
                'country' => (string) $shipment->recipient_country,
                'postal_code' => (string) ($shipment->recipient_postal_code ?? ''),
            ],
        ] as $party => $details) {
            if ($details['phone'] !== '' && !$this->isPhoneFormatReasonable($details['phone'])) {
                $errors[$party][] = 'رقم الهاتف يجب أن يكون بصيغة رقمية واضحة مع رمز الدولة عند الحاجة.';
            }

            if ($details['country'] !== '' && !$this->isCountryCodeReasonable($details['country'])) {
                $errors[$party][] = 'رمز الدولة يجب أن يتكون من حرفين لاتينيين.';
            }

            if ($details['postal_code'] !== '' && !$this->isPostalCodeReasonable($details['postal_code'])) {
                $errors[$party][] = 'الرمز البريدي يحتوي على صيغة غير صالحة.';
            }
        }

        if ($this->isInternationalShipment($shipment)) {
            if (blank($this->shipmentAttribute($shipment, 'sender_postal_code'))) {
                $errors['routing'][] = 'الشحن الدولي يتطلب رمزًا بريديًا للمرسل.';
            }
            if (blank($this->shipmentAttribute($shipment, 'recipient_postal_code'))) {
                $errors['routing'][] = 'الشحن الدولي يتطلب رمزًا بريديًا للمستلم.';
            }
        }

        // Parcel validation
        if ($shipment->parcels->isEmpty()) {
            $errors['parcels'][] = 'يجب إضافة طرد واحد على الأقل.';
        } else {
            foreach ($shipment->parcels as $parcel) {
                if ($parcel->weight <= 0) {
                    $errors['parcels'][] = "الطرد #{$parcel->sequence}: الوزن يجب أن يكون أكبر من 0.";
                }

                foreach (['length', 'width', 'height'] as $dimension) {
                    $value = $parcel->{$dimension};
                    if ($value !== null && (float) $value <= 0) {
                        $errors['parcels'][] = "الطرد #{$parcel->sequence}: قيمة {$dimension} يجب أن تكون أكبر من 0.";
                    }
                }

                if ($parcel->length !== null || $parcel->width !== null || $parcel->height !== null) {
                    if ($parcel->length === null || $parcel->width === null || $parcel->height === null) {
                        $errors['parcels'][] = "الطرد #{$parcel->sequence}: عند إدخال الأبعاد يجب تعبئة الطول والعرض والارتفاع معًا.";
                    }
                }
            }
        }

        // COD validation (FR-SH-019)
        if ($shipment->is_cod && $shipment->cod_amount <= 0) {
            $errors['routing'][] = 'مبلغ الدفع عند الاستلام يجب أن يكون أكبر من 0.';
        }

        return array_filter($errors);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-006: Status Management (State Machine)
    // ═══════════════════════════════════════════════════════════════

    public function updateStatus(string $accountId, string $shipmentId, string $newStatus, User $performer, ?string $reason = null, string $source = 'user'): Shipment
    {
        $shipment = $this->findShipment($accountId, $shipmentId);

        if (!$shipment->canTransitionTo($newStatus)) {
            throw new BusinessException(
                "لا يمكن الانتقال من {$shipment->status} إلى {$newStatus}.",
                'ERR_INVALID_STATUS_TRANSITION', 422
            );
        }

        $this->transitionStatus($shipment, $newStatus, $source, $performer->id, $reason);

        // Side effects based on new status
        if ($newStatus === Shipment::STATUS_DELIVERED) {
            $shipment->update(['actual_delivery_at' => now()]);
            // Update linked order
            if ($shipment->order_id) {
                Order::where('id', $shipment->order_id)->update(['status' => Order::STATUS_DELIVERED]);
            }
        }

        if ($newStatus === Shipment::STATUS_PICKED_UP) {
            $shipment->update(['picked_up_at' => now()]);
            if ($shipment->order_id) {
                Order::where('id', $shipment->order_id)->update(['status' => Order::STATUS_SHIPPED]);
            }
        }

        return $shipment->fresh();
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-007: Cancel / Void
    // ═══════════════════════════════════════════════════════════════

    public function cancelShipment(string $accountId, string $shipmentId, User $performer, ?string $reason = null): Shipment
    {
        $this->assertCanManageShipments($performer);
        $shipment = $this->findShipment($accountId, $shipmentId);

        if (!$shipment->isCancellable()) {
            throw new BusinessException(
                'لا يمكن إلغاء هذه الشحنة في حالتها الحالية.',
                'ERR_SHIPMENT_NOT_CANCELLABLE', 422
            );
        }

        return DB::transaction(function () use ($shipment, $performer, $reason) {
            $oldStatus = $shipment->status;
            $needsRefund = in_array($oldStatus, [Shipment::STATUS_PURCHASED, Shipment::STATUS_READY_FOR_PICKUP]);
            $reservationId = trim((string) ($shipment->balance_reservation_id ?? ''));

            // FR-SH-015: Refund to wallet if already charged
            if ($needsRefund && $shipment->total_charge > 0) {
                $shipment->update(['refund_ledger_entry_id' => 'pending_refund']);
            }

            // Release balance reservation (FR-SH-014)
            if ($reservationId !== '') {
                $hold = WalletHold::query()->find($reservationId);
                if ($hold instanceof WalletHold && $hold->isActive()) {
                    $this->billingWalletService->releaseHold((string) $hold->id);
                }

                $shipment->update(['balance_reservation_id' => null, 'reserved_amount' => null]);
            }

            $this->transitionStatus($shipment, Shipment::STATUS_CANCELLED, 'user', $performer->id, $reason);
            $shipment->update([
                'cancelled_by'        => $performer->id,
                'cancellation_reason' => $reason ?? 'User requested cancellation',
            ]);

            // Unlink order if linked
            if ($shipment->order_id) {
                Order::where('id', $shipment->order_id)->update([
                    'shipment_id' => null,
                    'status'      => Order::STATUS_READY,
                ]);
            }

            $this->auditService->warning(
                $shipment->account_id, $performer->id,
                'shipment.cancelled', AuditLog::CATEGORY_ACCOUNT,
                'Shipment', $shipment->id,
                ['status' => $oldStatus],
                ['status' => 'cancelled', 'reason' => $reason, 'needs_refund' => $needsRefund]
            );

            return $shipment->fresh();
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-008: Reprint / Download Label
    // ═══════════════════════════════════════════════════════════════

    public function getLabelInfo(string $accountId, string $shipmentId, User $performer): array
    {
        $this->assertCanPrintLabel($performer);
        $shipment = $this->findShipment($accountId, $shipmentId);

        if (!$shipment->hasLabel()) {
            throw new BusinessException('لا يوجد ملصق لهذه الشحنة.', 'ERR_NO_LABEL', 422);
        }

        // Increment print count (FR-SH-012)
        $shipment->increment('label_print_count');

        $this->auditService->info(
            $accountId, $performer->id,
            'shipment.label_printed', AuditLog::CATEGORY_ACCOUNT,
            'Shipment', $shipment->id,
            null,
            ['print_count' => $shipment->label_print_count + 1]
        );

        return [
            'label_url'    => $shipment->label_url,
            'label_format' => $shipment->label_format,
            'print_count'  => $shipment->label_print_count + 1,
            'tracking_number' => $shipment->tracking_number,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-009: Search & Filter
    // ═══════════════════════════════════════════════════════════════

    public function listShipments(string $accountId, array $filters, User $performer): array
    {
        $query = Shipment::where('account_id', $accountId)
            ->with('parcels', 'store:id,name,platform');

        // Filters
        if (!empty($filters['store_id']))   $query->where('store_id', $filters['store_id']);
        if (!empty($filters['status']))     $query->where('status', $filters['status']);
        if (!empty($filters['carrier']))    $query->where('carrier_code', $filters['carrier']);
        if (!empty($filters['source']))     $query->where('source', $filters['source']);
        if (!empty($filters['is_cod']))     $query->where('is_cod', true);
        if (!empty($filters['is_international'])) $query->where('is_international', true);

        if (!empty($filters['from'])) $query->where('created_at', '>=', $filters['from']);
        if (!empty($filters['to']))   $query->where('created_at', '<=', $filters['to']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('reference_number', 'ilike', "%{$s}%")
                  ->orWhere('tracking_number', 'ilike', "%{$s}%")
                  ->orWhere('recipient_name', 'ilike', "%{$s}%")
                  ->orWhere('recipient_phone', 'ilike', "%{$s}%");
            });
        }

        $limit  = min($filters['limit'] ?? 50, 100);
        $offset = $filters['offset'] ?? 0;

        $total = (clone $query)->count();
        $shipments = $query->orderByDesc('created_at')->limit($limit)->offset($offset)->get();

        // FR-SH-011: Mask financial fields if user lacks permission
        if (!$performer->hasPermission('shipments.view_financial')) {
            $shipments->each(function ($sh) {
                $sh->makeHidden(['shipping_rate', 'total_charge', 'platform_fee', 'profit_margin', 'insurance_amount']);
            });
        }

        return ['shipments' => $shipments, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-010: Bulk Shipment Creation
    // ═══════════════════════════════════════════════════════════════

    public function bulkCreateFromOrders(string $accountId, array $orderIds, array $defaults, User $performer): array
    {
        $this->assertCanManageShipments($performer);

        $results = ['success' => 0, 'failed' => 0, 'errors' => [], 'shipments' => []];

        foreach ($orderIds as $orderId) {
            try {
                $shipment = $this->createFromOrder($accountId, $orderId, $defaults, $performer);
                $results['success']++;
                $results['shipments'][] = ['order_id' => $orderId, 'shipment_id' => $shipment->id, 'reference' => $shipment->reference_number];
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = ['order_id' => $orderId, 'error' => $e->getMessage()];
            }
        }

        $this->auditService->info(
            $accountId, $performer->id,
            'shipment.bulk_created', AuditLog::CATEGORY_ACCOUNT,
            'Account', $accountId,
            null,
            ['total' => count($orderIds), 'success' => $results['success'], 'failed' => $results['failed']]
        );

        return $results;
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-013: KYC Check
    // ═══════════════════════════════════════════════════════════════

    public function checkKycForPurchase(Shipment $shipment): bool
    {
        return $this->evaluateKycRestrictionGate($shipment)['allowed'];
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-016: Return Shipments
    // ═══════════════════════════════════════════════════════════════

    public function createReturnShipment(string $accountId, string $originalShipmentId, array $overrides, User $performer): Shipment
    {
        $original = $this->findShipment($accountId, $originalShipmentId);

        if (!in_array($original->status, [Shipment::STATUS_DELIVERED, Shipment::STATUS_EXCEPTION])) {
            throw new BusinessException('لا يمكن إنشاء شحنة مرتجع إلا بعد التسليم أو حدوث استثناء.', 'ERR_RETURN_NOT_ALLOWED', 422);
        }

        // Swap sender ↔ recipient
        $data = array_merge([
            'sender_name'         => $original->recipient_name,
            'sender_phone'        => $original->recipient_phone,
            'sender_address_1'    => $original->recipient_address_1,
            'sender_city'         => $original->recipient_city,
            'sender_country'      => $original->recipient_country,
            'recipient_name'      => $original->sender_name,
            'recipient_phone'     => $original->sender_phone,
            'recipient_address_1' => $original->sender_address_1,
            'recipient_city'      => $original->sender_city,
            'recipient_country'   => $original->sender_country,
            'is_return'           => true,
            'store_id'            => $original->store_id,
        ], $overrides);

        $returnShipment = $this->createDirect($accountId, $data, $performer);
        $returnShipment->update([
            'source'   => Shipment::SOURCE_RETURN,
            'metadata' => array_merge($returnShipment->metadata ?? [], ['original_shipment_id' => $originalShipmentId]),
        ]);

        return $returnShipment->fresh(['parcels']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-004: Address Book
    // ═══════════════════════════════════════════════════════════════

    public function listAddresses(string $accountId, ?string $type = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Address::where('account_id', $accountId);

        if ($type && Address::supportsTypedAddressBook()) {
            $query->whereIn('type', [$type, 'both']);
        }

        return $query
            ->orderByDesc(Address::defaultSenderColumn())
            ->orderBy('label')
            ->get();
    }

    public function findAddress(string $accountId, string $addressId, ?string $type = null): Address
    {
        $query = Address::where('account_id', $accountId)
            ->where('id', $addressId);

        if ($type && Address::supportsTypedAddressBook()) {
            $query->whereIn('type', [$type, 'both']);
        }

        return $query->firstOrFail();
    }

    public function saveAddress(string $accountId, array $data, User $performer): Address
    {
        $payload = $this->normalizeAddressPayload($data);

        if (! empty($payload[Address::defaultSenderColumn()])) {
            $this->clearDefaultSenderFlag($accountId);
        }

        return Address::create(array_merge($payload, ['account_id' => $accountId]));
    }

    public function updateAddress(string $accountId, string $addressId, array $data, User $performer): Address
    {
        $address = $this->findAddress($accountId, $addressId);
        $payload = $this->normalizeAddressPayload($data);

        if (! empty($payload[Address::defaultSenderColumn()])) {
            $this->clearDefaultSenderFlag($accountId, (string) $address->id);
        }

        $address->update($payload);

        return $address->fresh();
    }

    public function deleteAddress(string $accountId, string $addressId): void
    {
        $this->findAddress($accountId, $addressId)->delete();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeAddressPayload(array $data): array
    {
        $payload = [];

        if (array_key_exists('type', $data) && Address::supportsTypedAddressBook()) {
            $payload['type'] = $data['type'] ?: 'both';
        }

        if (array_key_exists('label', $data)) {
            $payload['label'] = $data['label'];
        }

        if (array_key_exists('contact_name', $data) || array_key_exists('name', $data)) {
            $payload['contact_name'] = $data['contact_name'] ?? $data['name'];
        }

        if (array_key_exists('company_name', $data) && Address::hasSchemaColumn('company_name')) {
            $payload['company_name'] = $data['company_name'];
        }

        if (array_key_exists('phone', $data)) {
            $payload['phone'] = $data['phone'];
        }

        if (array_key_exists('email', $data) && Address::hasSchemaColumn('email')) {
            $payload['email'] = $data['email'];
        }

        if (array_key_exists('address_line_1', $data) || array_key_exists('street', $data)) {
            $payload['address_line_1'] = $data['address_line_1'] ?? $data['street'];
        }

        if (array_key_exists('address_line_2', $data) || array_key_exists('district', $data)) {
            $payload['address_line_2'] = $data['address_line_2'] ?? $data['district'];
        }

        if (array_key_exists('city', $data)) {
            $payload['city'] = $data['city'];
        }

        if (array_key_exists('state', $data) && Address::hasSchemaColumn('state')) {
            $payload['state'] = $data['state'];
        }

        if (array_key_exists('postal_code', $data)) {
            $payload['postal_code'] = $data['postal_code'];
        }

        if (array_key_exists('country', $data)) {
            $payload['country'] = $data['country'];
        }

        $defaultFlag = null;
        if (array_key_exists('is_default_sender', $data)) {
            $defaultFlag = (bool) $data['is_default_sender'];
        } elseif (array_key_exists('is_default', $data)) {
            $defaultFlag = (bool) $data['is_default'];
        }

        if ($defaultFlag !== null) {
            $payload[Address::defaultSenderColumn()] = $defaultFlag;
        }

        return $payload;
    }

    private function clearDefaultSenderFlag(string $accountId, ?string $exceptId = null): void
    {
        $query = Address::where('account_id', $accountId);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $updates = ['is_default' => false];
        if (Address::hasSchemaColumn('is_default_sender')) {
            $updates['is_default_sender'] = false;
        }

        $query->where(Address::defaultSenderColumn(), true)->update($updates);
    }

    // ═══════════════════════════════════════════════════════════════
    // Statistics
    // ═══════════════════════════════════════════════════════════════

    public function getShipmentStats(string $accountId, ?string $storeId = null): array
    {
        $query = Shipment::where('account_id', $accountId);
        if ($storeId) $query->where('store_id', $storeId);

        $byStatus = (clone $query)->selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status')->toArray();
        $total = array_sum($byStatus);

        return [
            'total'             => $total,
            'draft'             => $byStatus[Shipment::STATUS_DRAFT] ?? 0,
            'purchased'         => $byStatus[Shipment::STATUS_PURCHASED] ?? 0,
            'in_transit'        => $byStatus[Shipment::STATUS_IN_TRANSIT] ?? 0,
            'delivered'         => $byStatus[Shipment::STATUS_DELIVERED] ?? 0,
            'cancelled'         => $byStatus[Shipment::STATUS_CANCELLED] ?? 0,
            'returned'          => $byStatus[Shipment::STATUS_RETURNED] ?? 0,
            'exception'         => $byStatus[Shipment::STATUS_EXCEPTION] ?? 0,
            'by_status'         => $byStatus,
        ];
    }

    public function getShipment(string $accountId, string $shipmentId): Shipment
    {
        return Shipment::where('account_id', $accountId)
            ->where('id', $shipmentId)
            ->with('parcels', 'statusHistory', 'store:id,name,platform', 'order:id,external_order_number,status')
            ->firstOrFail();
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-003: Parcel Management
    // ═══════════════════════════════════════════════════════════════

    public function addParcel(string $accountId, string $shipmentId, array $data, User $performer): Parcel
    {
        $this->assertCanUpdateShipmentDraft($performer);

        $shipment = $this->findShipment($accountId, $shipmentId);
        if (!$shipment->isDraft() && $shipment->status !== Shipment::STATUS_VALIDATED) {
            throw new BusinessException('لا يمكن إضافة طرود بعد تسعير الشحنة.', 'ERR_CANNOT_MODIFY_PARCELS', 422);
        }

        $nextSeq = ($shipment->parcels->max('sequence') ?? 0) + 1;

        $parcel = Parcel::create([
            'shipment_id'    => $shipment->id,
            'sequence'       => $nextSeq,
            'weight'         => $data['weight'],
            'length'         => $data['length'] ?? null,
            'width'          => $data['width'] ?? null,
            'height'         => $data['height'] ?? null,
            'packaging_type' => $data['packaging_type'] ?? 'custom',
            'description'    => $data['description'] ?? null,
            'reference'      => $data['reference'] ?? null,
        ]);

        $parcel->update(['volumetric_weight' => $parcel->calculateVolumetricWeight()]);
        $shipment->recalculateWeights();

        return $parcel;
    }

    public function removeParcel(string $accountId, string $shipmentId, string $parcelId, User $performer): void
    {
        $this->assertCanUpdateShipmentDraft($performer);

        $shipment = $this->findShipment($accountId, $shipmentId);
        if ($shipment->parcels->count() <= 1) {
            throw new BusinessException('لا يمكن حذف آخر طرد.', 'ERR_LAST_PARCEL', 422);
        }

        Parcel::where('id', $parcelId)->where('shipment_id', $shipmentId)->firstOrFail()->delete();
        $shipment->recalculateWeights();
    }

    // ═══════════════════════════════════════════════════════════════
    // Internal Helpers
    // ═══════════════════════════════════════════════════════════════

    private function createParcels(Shipment $shipment, array $parcelsData): void
    {
        if (! Schema::hasTable('parcels')) {
            return;
        }

        foreach ($parcelsData as $i => $pd) {
            $parcel = Parcel::create([
                'shipment_id'    => $shipment->id,
                'sequence'       => $i + 1,
                'weight'         => $pd['weight'] ?? 0.5,
                'length'         => $pd['length'] ?? null,
                'width'          => $pd['width'] ?? null,
                'height'         => $pd['height'] ?? null,
                'packaging_type' => $pd['packaging_type'] ?? 'custom',
                'description'    => $pd['description'] ?? null,
                'reference'      => $pd['reference'] ?? null,
            ]);
            $parcel->update(['volumetric_weight' => $parcel->calculateVolumetricWeight()]);
        }
        $shipment->recalculateWeights();
    }

    private function transitionStatus(Shipment $shipment, string $newStatus, string $source, ?string $userId, ?string $reason): void
    {
        $oldStatus = $shipment->status;
        $this->updateShipmentCompat($shipment, ['status' => $newStatus, 'status_reason' => $reason]);
        $this->recordStatusChange($shipment, $oldStatus, $newStatus, $source, $userId, $reason);
    }

    private function recordStatusChange(Shipment $shipment, ?string $from, string $to, string $source, ?string $userId, ?string $reason): void
    {
        if (! Schema::hasTable('shipment_status_history')) {
            return;
        }

        ShipmentStatusHistory::create([
            'shipment_id' => $shipment->id,
            'from_status' => $from,
            'to_status'   => $to,
            'source'      => $source,
            'reason'      => $reason,
            'changed_by'  => $userId,
            'created_at'  => now(),
        ]);
    }

    private function findShipment(string $accountId, string $shipmentId): Shipment
    {
        $query = Shipment::where('account_id', $accountId)
            ->where('id', $shipmentId);

        if (Schema::hasTable('parcels')) {
            $query->with('parcels');
        }

        return $query->firstOrFail();
    }

    private function assertCanCreateShipmentDraft(User $user): void
    {
        if (!$user->hasPermission('shipments.create') && !$user->hasPermission('shipments.manage')) {
            throw BusinessException::permissionDenied();
        }
    }

    private function assertCanUpdateShipmentDraft(User $user): void
    {
        if (!$user->hasPermission('shipments.update_draft') && !$user->hasPermission('shipments.manage')) {
            throw BusinessException::permissionDenied();
        }
    }

    private function assertCanManageShipments(User $user): void
    {
        if (!$user->hasPermission('shipments.manage')) {
            throw BusinessException::permissionDenied();
        }
    }

    private function assertCanPrintLabel(User $user): void
    {
        if (!$user->hasPermission('shipments.manage') && !$user->hasPermission('shipments.print_label')) {
            throw BusinessException::permissionDenied();
        }
    }

    /**
     * @return array{
     *   allowed: bool,
     *   kyc_status: string,
     *   error_code: string,
     *   reason_code: string,
     *   message: string,
     *   next_action: string,
     *   capabilities: array<string, mixed>
     * }
     */
    private function evaluateKycRestrictionGate(Shipment $shipment): array
    {
        /** @var Account $account */
        $account = $shipment->account ?? Account::with('kycVerification')->findOrFail($shipment->account_id);
        $verification = $account->kycVerification;

        $rawStatus = $verification?->status ?? (string) ($account->kyc_status ?? KycVerification::STATUS_UNVERIFIED);
        $canonicalStatus = match ($rawStatus) {
            'verified', KycVerification::STATUS_APPROVED => 'verified',
            'pending', 'pending_review', KycVerification::STATUS_PENDING => 'pending_review',
            'rejected', KycVerification::STATUS_REJECTED => 'rejected',
            default => 'unverified',
        };

        $capabilities = $verification?->capabilities() ?? $this->fallbackKycCapabilities($canonicalStatus);

        if (($account->status ?? 'active') !== 'active') {
            return [
                'allowed' => false,
                'kyc_status' => $canonicalStatus,
                'error_code' => 'ERR_ACCOUNT_RESTRICTED',
                'reason_code' => 'account_inactive',
                'message' => 'الحساب غير نشط حاليًا ولا يمكنه متابعة الشحن.',
                'next_action' => 'تواصل مع الدعم أو مع مدير الحساب لتفعيل الحساب أولًا.',
                'capabilities' => $capabilities,
            ];
        }

        if ($this->isInternationalShipment($shipment) && !($capabilities['can_ship_international'] ?? false)) {
            return [
                'allowed' => false,
                'kyc_status' => $canonicalStatus,
                'error_code' => match ($canonicalStatus) {
                    'pending_review' => 'ERR_KYC_PENDING_REVIEW',
                    'rejected' => 'ERR_KYC_REJECTED',
                    default => 'ERR_KYC_REQUIRED',
                },
                'reason_code' => 'international_restricted',
                'message' => match ($canonicalStatus) {
                    'pending_review' => 'الحساب بانتظار مراجعة التحقق. الشحن الدولي سيتاح بعد اعتماد الطلب.',
                    'rejected' => 'تم رفض طلب التحقق الحالي. الشحن الدولي متوقف حتى إعادة تقديم المستندات المطلوبة.',
                    default => 'يلزم إكمال التحقق من الهوية قبل الشحن الدولي.',
                },
                'next_action' => match ($canonicalStatus) {
                    'pending_review' => 'انتظر اكتمال مراجعة التحقق أو تابع مع الدعم إذا طال وقت المراجعة.',
                    'rejected' => 'أعد رفع مستندات التحقق أو صحح الطلب الحالي ثم أعد المحاولة.',
                    default => 'أكمل التحقق من الهوية وارفع المستندات المطلوبة ثم أعد المحاولة.',
                },
                'capabilities' => $capabilities,
            ];
        }

        $dailyLimit = $capabilities['daily_shipment_limit'] ?? null;
        if ($dailyLimit !== null) {
            $todayCount = Shipment::query()
                ->where('account_id', $shipment->account_id)
                ->whereDate('created_at', now()->toDateString())
                ->where('id', '!=', $shipment->id)
                ->whereNotIn('status', [Shipment::STATUS_CANCELLED, Shipment::STATUS_FAILED])
                ->count();

            if ($todayCount >= (int) $dailyLimit) {
                return [
                    'allowed' => false,
                    'kyc_status' => $canonicalStatus,
                    'error_code' => 'ERR_KYC_USAGE_LIMIT',
                    'reason_code' => 'daily_shipment_limit',
                    'message' => 'تم الوصول إلى الحد اليومي المسموح به لهذا الحساب قبل فتح التسعير.',
                    'next_action' => 'انتظر حتى اليوم التالي أو أكمل التحقق لرفع حدود الاستخدام الحالية.',
                    'capabilities' => $capabilities,
                ];
            }
        }

        return [
            'allowed' => true,
            'kyc_status' => $canonicalStatus,
            'error_code' => 'OK',
            'reason_code' => 'ready_for_rates',
            'message' => 'الشحنة جاهزة للانتقال إلى مرحلة التسعير.',
            'next_action' => 'يمكنك الآن متابعة جلب الأسعار واختيار العرض المناسب.',
            'capabilities' => $capabilities,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackKycCapabilities(string $canonicalStatus): array
    {
        return match ($canonicalStatus) {
            'verified' => [
                'can_ship_domestic' => true,
                'can_ship_international' => true,
                'daily_shipment_limit' => null,
                'message' => 'الحساب موثق بالكامل.',
            ],
            'pending_review' => [
                'can_ship_domestic' => true,
                'can_ship_international' => false,
                'daily_shipment_limit' => 10,
                'message' => 'الحساب بانتظار المراجعة.',
            ],
            'rejected' => [
                'can_ship_domestic' => true,
                'can_ship_international' => false,
                'daily_shipment_limit' => 5,
                'message' => 'تم رفض التحقق الحالي.',
            ],
            default => [
                'can_ship_domestic' => true,
                'can_ship_international' => false,
                'daily_shipment_limit' => 3,
                'message' => 'يلزم إكمال التحقق.',
            ],
        };
    }

    private function isPhoneFormatReasonable(string $phone): bool
    {
        return preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/\s+/', '', $phone)) === 1;
    }

    private function isCountryCodeReasonable(string $country): bool
    {
        return preg_match('/^[A-Z]{2}$/', strtoupper($country)) === 1;
    }

    private function isPostalCodeReasonable(string $postalCode): bool
    {
        return preg_match('/^[A-Za-z0-9 -]{3,12}$/', trim($postalCode)) === 1;
    }
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildShipmentDraftAttributes(string $accountId, array $data, User $performer): array
    {
        $parcels = is_array($data['parcels'] ?? null) && $data['parcels'] !== [] ? $data['parcels'] : [['weight' => $data['weight'] ?? 0.5]];
        $pieces = max(1, count($parcels));
        $totalWeight = round((float) collect($parcels)->sum(fn (array $parcel) => (float) ($parcel['weight'] ?? 0)), 3);
        $chargeableWeight = $totalWeight > 0 ? $totalWeight : (float) ($data['weight'] ?? 0.5);
        $senderCountry = strtoupper((string) ($data['sender_country'] ?? ''));
        $recipientCountry = strtoupper((string) ($data['recipient_country'] ?? ''));
        $isInternational = $senderCountry !== '' && $recipientCountry !== '' && $senderCountry !== $recipientCountry;
        $firstParcel = $parcels[0] ?? [];
        $payload = [
            'account_id' => $accountId,
            'store_id' => $data['store_id'] ?? null,
            'reference_number' => Shipment::generateReference(),
            'source' => Shipment::SOURCE_DIRECT,
            'status' => Shipment::STATUS_DRAFT,
            'type' => $isInternational ? 'international' : 'domestic',
            'sender_address_id' => $data['sender_address_id'] ?? null,
            'sender_name' => $data['sender_name'],
            'sender_company' => $data['sender_company'] ?? null,
            'sender_phone' => $data['sender_phone'],
            'sender_email' => $data['sender_email'] ?? null,
            'sender_address' => $data['sender_address_1'],
            'sender_address_1' => $data['sender_address_1'],
            'sender_address_2' => $data['sender_address_2'] ?? null,
            'sender_city' => $data['sender_city'],
            'sender_state' => $data['sender_state'] ?? null,
            'sender_postal_code' => $data['sender_postal_code'] ?? null,
            'sender_country' => $senderCountry,
            'recipient_address_id' => $data['recipient_address_id'] ?? null,
            'recipient_name' => $data['recipient_name'],
            'recipient_company' => $data['recipient_company'] ?? null,
            'recipient_phone' => $data['recipient_phone'],
            'recipient_email' => $data['recipient_email'] ?? null,
            'recipient_address' => $data['recipient_address_1'],
            'recipient_address_1' => $data['recipient_address_1'],
            'recipient_address_2' => $data['recipient_address_2'] ?? null,
            'recipient_city' => $data['recipient_city'],
            'recipient_state' => $data['recipient_state'] ?? null,
            'recipient_postal_code' => $data['recipient_postal_code'] ?? null,
            'recipient_country' => $recipientCountry,
            'is_international' => $isInternational,
            'is_cod' => !empty($data['cod_amount']) && (float) $data['cod_amount'] > 0,
            'cod_amount' => $data['cod_amount'] ?? 0,
            'is_insured' => !empty($data['insurance_amount']) && (float) $data['insurance_amount'] > 0,
            'insurance_amount' => $data['insurance_amount'] ?? 0,
            'insurance_flag' => !empty($data['insurance_amount']) && (float) $data['insurance_amount'] > 0,
            'is_return' => $data['is_return'] ?? false,
            'has_dangerous_goods' => $data['has_dangerous_goods'] ?? false,
            'delivery_instructions' => $data['delivery_instructions'] ?? null,
            'parcels_count' => $pieces,
            'pieces' => $pieces,
            'total_weight' => $totalWeight,
            'weight' => $chargeableWeight,
            'chargeable_weight' => $chargeableWeight,
            'content_description' => $firstParcel['description'] ?? ($data['content_description'] ?? 'مسودة الشحنة'),
            'user_id' => $performer->id,
            'created_by' => $performer->id,
            'metadata' => array_filter([
                'phase' => 'phase_b',
                'workflow_state' => Shipment::STATUS_DRAFT,
                'workflow_type' => $isInternational ? 'international' : 'domestic',
                'sender_postal_code' => $data['sender_postal_code'] ?? null,
                'sender_country' => $senderCountry ?: null,
                'recipient_postal_code' => $data['recipient_postal_code'] ?? null,
                'recipient_country' => $recipientCountry ?: null,
                'parcels' => $parcels,
                'input' => $data['metadata'] ?? null,
            ], fn ($value) => $value !== null && $value !== []),
        ];

        // Keep Phase B workflow classification separate from the existing shipment_type enum.
        if (Schema::hasColumn('shipments', 'shipment_type') && !isset($payload['shipment_type']) && array_key_exists('shipment_type', $data)) {
            $payload['shipment_type'] = $data['shipment_type'];
        }

        return $this->filterShipmentAttributes($payload);
    }
    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function filterShipmentAttributes(array $attributes): array
    {
        $filtered = [];
        foreach ($attributes as $column => $value) {
            if (Schema::hasColumn('shipments', $column)) {
                $filtered[$column] = $value;
            }
        }
        return $filtered;
    }

    private function resolvePreflightAmount(Shipment $shipment): float
    {
        $selectedOption = $shipment->selectedRateOption;

        $amount = $shipment->total_charge !== null
            ? (float) $shipment->total_charge
            : (float) ($selectedOption?->retail_rate ?? 0);

        if ($amount <= 0) {
            throw new BusinessException(
                'عرض الشحنة المحدد لا يملك إجماليًا صالحًا للفوترة للحجز المسبق للمحفظة.',
                'ERR_INVALID_SELECTED_OFFER_TOTAL',
                422,
                [
                    'shipment_id' => (string) $shipment->id,
                    'selected_rate_option_id' => (string) ($shipment->selected_rate_option_id ?? ''),
                ]
            );
        }

        return round($amount, 2);
    }

    private function resolvePreflightCurrency(Shipment $shipment): string
    {
        $currency = trim((string) (
            $shipment->selectedRateOption?->currency
            ?? $shipment->rateQuote?->currency
            ?? $shipment->currency
            ?? 'SAR'
        ));

        return strtoupper($currency === '' ? 'SAR' : $currency);
    }

    private function resolveExistingActiveReservation(Shipment $shipment, string $walletId): ?WalletHold
    {
        $linkedReservationId = trim((string) ($shipment->balance_reservation_id ?? ''));
        if ($linkedReservationId !== '') {
            $linkedHold = WalletHold::query()->where('wallet_id', $walletId)->find($linkedReservationId);
            if ($linkedHold instanceof WalletHold && $linkedHold->isActive()) {
                return $linkedHold;
            }
        }

        return WalletHold::query()
            ->where('wallet_id', $walletId)
            ->where('shipment_id', (string) $shipment->id)
            ->active()
            ->latest('created_at')
            ->first();
    }

    private function syncShipmentReservation(Shipment $shipment, WalletHold $hold, User $performer, bool $isNewReservation): void
    {
        $payload = [
            'balance_reservation_id' => (string) $hold->id,
            'reserved_amount' => (float) $hold->amount,
            'status_reason' => null,
        ];

        if ((float) ($shipment->total_charge ?? 0) <= 0) {
            $payload['total_charge'] = (float) $hold->amount;
        }

        if (Schema::hasColumn('shipments', 'total_cost') && (float) ($shipment->total_cost ?? 0) <= 0) {
            $payload['total_cost'] = (float) $hold->amount;
        }

        $payload = $this->filterShipmentAttributes($payload);

        if ($payload !== []) {
            Shipment::withoutGlobalScopes()
                ->where('id', (string) $shipment->id)
                ->update($payload);

            $shipment->forceFill($payload);
        }

        if ($shipment->status !== Shipment::STATUS_PAYMENT_PENDING) {
            $this->transitionStatus(
                $shipment,
                Shipment::STATUS_PAYMENT_PENDING,
                'system',
                $performer->id,
                'حجز المحفظة المسبق نشط'
            );
        }

        $this->auditService->info(
            (string) $shipment->account_id,
            (string) $performer->id,
            $isNewReservation ? 'shipment.wallet_preflight_reserved' : 'shipment.wallet_preflight_reused',
            AuditLog::CATEGORY_FINANCIAL,
            'Shipment',
            (string) $shipment->id,
            null,
            [
                'reservation_id' => (string) $hold->id,
                'wallet_id' => (string) $hold->wallet_id,
                'reserved_amount' => (float) $hold->amount,
                'currency' => (string) ($hold->currency ?? ''),
                'source' => (string) ($hold->source ?? 'shipment_preflight'),
                'correlation_id' => (string) ($hold->correlation_id ?? AuditService::getRequestId()),
                'selected_rate_option_id' => (string) ($shipment->selected_rate_option_id ?? ''),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWalletPreflightResult(
        Shipment $shipment,
        WalletHold $hold,
        BillingWallet $wallet,
        bool $created
    ): array {
        return [
            'shipment_id' => (string) $shipment->id,
            'shipment_status' => (string) $shipment->status,
            'rate_quote_id' => (string) ($shipment->rate_quote_id ?? ''),
            'selected_rate_option_id' => (string) ($shipment->selected_rate_option_id ?? ''),
            'wallet_id' => (string) $wallet->id,
            'currency' => (string) ($hold->currency ?? $wallet->currency),
            'reserved_amount' => (float) $hold->amount,
            'reservation_id' => (string) $hold->id,
            'reservation_status' => (string) $hold->status,
            'source' => (string) ($hold->source ?? 'shipment_preflight'),
            'correlation_id' => (string) ($hold->correlation_id ?? ''),
            'available_balance' => (float) $wallet->available_balance,
            'reserved_balance' => (float) $wallet->reserved_balance,
            'effective_balance' => $wallet->getEffectiveBalance(),
            'created' => $created,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function updateShipmentCompat(Shipment $shipment, array $attributes): void
    {
        $payload = $this->filterShipmentAttributes($attributes);
        if ($payload === []) {
            return;
        }
        $shipment->update($payload);
    }
    private function shipmentAttribute(Shipment $shipment, string $primary, ?string $fallback = null): mixed
    {
        $value = $shipment->{$primary} ?? null;
        if ($value !== null && $value !== '') {
            return $value;
        }
        if ($fallback === null) {
            return null;
        }
        $fallbackValue = $shipment->{$fallback} ?? null;
        return $fallbackValue !== '' ? $fallbackValue : null;
    }
    private function isInternationalShipment(Shipment $shipment): bool
    {
        if ($shipment->is_international !== null) {
            return (bool) $shipment->is_international;
        }
        $senderCountry = strtoupper((string) ($this->shipmentAttribute($shipment, 'sender_country') ?? ''));
        $recipientCountry = strtoupper((string) ($this->shipmentAttribute($shipment, 'recipient_country') ?? ''));
        return $senderCountry !== '' && $recipientCountry !== '' && $senderCountry !== $recipientCountry;
    }
}
