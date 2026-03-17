<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\PricingBreakdown;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Carriers\CarrierRateAdapter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RateService
{
    public function __construct(
        protected CarrierRateAdapter $carrierAdapter,
        protected PricingEngineService $pricingEngine,
        protected AuditService $auditService,
        protected DgComplianceService $dgComplianceService,
    ) {}

    public function fetchRates(string $accountId, string $shipmentId, User $performer, ?string $carrierCode = null): RateQuote
    {
        $shipment = Shipment::query()
            ->where('account_id', $accountId)
            ->where('id', $shipmentId)
            ->with('parcels')
            ->firstOrFail();
        $this->assertExternalTenantMatchesShipment($shipment, $performer);

        $account = Account::findOrFail($accountId);

        if (! in_array($shipment->status, [Shipment::STATUS_READY_FOR_RATES, Shipment::STATUS_RATED], true)) {
            throw new BusinessException(
                'لا يمكن جلب الأسعار قبل اكتمال التحقق من البيانات واجتياز بوابة التحقق والقيود.',
                'ERR_INVALID_STATE_FOR_RATES',
                422,
                [
                    'shipment_id' => $shipment->id,
                    'current_status' => $shipment->status,
                    'allowed_statuses' => [
                        Shipment::STATUS_READY_FOR_RATES,
                        Shipment::STATUS_RATED,
                    ],
                    'next_action' => 'أكمل خطوة التحقق من الشحنة أولًا حتى تصبح جاهزة لمرحلة التسعير.',
                ]
            );
        }

        return DB::transaction(function () use ($shipment, $account, $performer, $carrierCode): RateQuote {
            $resolvedCarrierCode = $this->carrierAdapter->resolveCarrierCode($carrierCode);
            $context = $this->buildContext($shipment, $resolvedCarrierCode);

            $quote = RateQuote::create([
                'account_id' => $account->id,
                'shipment_id' => $shipment->id,
                'origin_country' => $shipment->sender_country,
                'origin_city' => $shipment->sender_city,
                'destination_country' => $shipment->recipient_country,
                'destination_city' => $shipment->recipient_city,
                'total_weight' => $shipment->total_weight,
                'chargeable_weight' => $shipment->chargeable_weight,
                'parcels_count' => $shipment->parcels_count,
                'is_cod' => (bool) $shipment->is_cod,
                'cod_amount' => (float) ($shipment->cod_amount ?? 0),
                'is_insured' => (bool) ($shipment->is_insured ?? false),
                'insurance_value' => (float) ($shipment->insurance_amount ?? 0),
                'currency' => $shipment->currency ?? 'SAR',
                'status' => RateQuote::STATUS_PENDING,
                'expires_at' => now()->addMinutes(RateQuote::DEFAULT_TTL_MINUTES),
                'correlation_id' => 'RQ-' . Str::upper(Str::random(12)),
                'requested_by' => $performer->id,
                'request_metadata' => $context,
            ]);

            try {
                $rawRates = $this->carrierAdapter->fetchRates($context);

                if (empty($rawRates)) {
                    $quote->update([
                        'status' => RateQuote::STATUS_FAILED,
                        'error_message' => 'لا توجد خدمات شحن متاحة.',
                    ]);

                    throw new BusinessException(
                        'لا توجد خدمات شحن متاحة لذلك الطلب.',
                        'ERR_NO_RATES_AVAILABLE',
                        422
                    );
                }

                $options = [];
                foreach ($rawRates as $raw) {
                    $optionContext = array_merge($context, [
                        'shipment_id' => (string) $shipment->id,
                        'carrier_code' => $raw['carrier_code'],
                        'service_code' => $raw['service_code'],
                    ]);

                    $pricing = $this->pricingEngine->calculateShipmentOffer(
                        (string) $account->id,
                        $raw,
                        $optionContext,
                        (string) $quote->id
                    );

                    $option = RateOption::create([
                        'rate_quote_id' => $quote->id,
                        'carrier_code' => $raw['carrier_code'],
                        'carrier_name' => $raw['carrier_name'],
                        'service_code' => $raw['service_code'],
                        'service_name' => $raw['service_name'],
                        'net_rate' => $raw['net_rate'],
                        'fuel_surcharge' => $raw['fuel_surcharge'] ?? 0,
                        'other_surcharges' => $raw['other_surcharges'] ?? 0,
                        'total_net_rate' => $raw['total_net_rate'],
                        'markup_amount' => $pricing['markup_amount'],
                        'service_fee' => $pricing['service_fee'],
                        'retail_rate_before_rounding' => $pricing['retail_rate_before_rounding'],
                        'retail_rate' => $pricing['retail_rate'],
                        'profit_margin' => $pricing['profit_margin'],
                        'currency' => (string) ($raw['currency'] ?? $shipment->currency ?? 'SAR'),
                        'estimated_days_min' => $raw['estimated_days_min'] ?? null,
                        'estimated_days_max' => $raw['estimated_days_max'] ?? null,
                        'estimated_delivery_at' => $raw['estimated_delivery_at'] ?? null,
                        'pricing_rule_id' => $pricing['pricing_rule_id'],
                        'pricing_breakdown_id' => $pricing['pricing_breakdown_id'] ?? null,
                        'pricing_breakdown' => $pricing['pricing_breakdown'],
                        'rule_evaluation_log' => $this->mergeRuleEvaluationLog(
                            $pricing['rule_evaluation_log'],
                            $raw
                        ),
                        'is_available' => $raw['is_available'] ?? true,
                        'unavailable_reason' => $raw['unavailable_reason'] ?? null,
                    ]);

                    $options[] = $option;

                    if (! empty($pricing['pricing_breakdown_id'])) {
                        PricingBreakdown::query()
                            ->where('id', $pricing['pricing_breakdown_id'])
                            ->update(['rate_option_id' => (string) $option->id]);
                    }
                }

                $this->assignBadges($options);

                $quote->update([
                    'status' => RateQuote::STATUS_COMPLETED,
                    'options_count' => count($options),
                ]);

                $shipmentUpdates = [
                    'rate_quote_id' => (string) $quote->id,
                    'selected_rate_option_id' => null,
                ];

                if ($shipment->status === Shipment::STATUS_READY_FOR_RATES) {
                    $shipmentUpdates['status'] = Shipment::STATUS_RATED;
                }

                $shipment->update($shipmentUpdates);
            } catch (BusinessException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $quote->update([
                    'status' => RateQuote::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);

                throw new BusinessException(
                    'فشل في جلب الأسعار: ' . $e->getMessage(),
                    'ERR_RATE_FETCH_FAILED',
                    500
                );
            }

            $this->auditService->info(
                $account->id,
                $performer->id,
                'rate.fetched',
                AuditLog::CATEGORY_ACCOUNT,
                'RateQuote',
                $quote->id,
                null,
                [
                    'shipment_id' => $shipment->id,
                    'options' => count($options),
                    'correlation' => $quote->correlation_id,
                ]
            );

            return $quote->load('options');
        });
    }

    public function getShipmentOffers(string $accountId, string $shipmentId, User $performer): array
    {
        $shipment = Shipment::query()
            ->where('account_id', $accountId)
            ->where('id', $shipmentId)
            ->firstOrFail();
        $this->assertExternalTenantMatchesShipment($shipment, $performer);

        $quote = $this->resolveShipmentQuote($accountId, (string) $shipment->id);
        $this->assertExternalTenantMatchesQuote($quote, $performer);
        $canViewFinancial = $performer->hasPermission('rates.view_breakdown');
        $selectedOptionId = trim((string) ($shipment->selected_rate_option_id ?: $quote->selected_option_id ?: ''));

        return [
            'shipment_id' => (string) $shipment->id,
            'shipment_status' => (string) $shipment->status,
            'rate_quote_id' => (string) $quote->id,
            'quote_status' => (string) $quote->status,
            'selected_rate_option_id' => $selectedOptionId !== '' ? $selectedOptionId : null,
            'expires_at' => $quote->expires_at,
            'is_expired' => $quote->isExpired(),
            'expires_in_seconds' => $quote->expires_at ? max(0, now()->diffInSeconds($quote->expires_at, false)) : null,
            'offers' => $quote->options
                ->map(fn (RateOption $option): array => $this->formatQuoteOption(
                    $option,
                    $canViewFinancial,
                    $selectedOptionId !== '' && (string) $option->id === $selectedOptionId
                ))
                ->values()
                ->all(),
        ];
    }

    public function selectOption(string $accountId, string $quoteId, ?string $optionId, string $strategy, User $performer): RateQuote
    {
        $quote = RateQuote::query()
            ->where('account_id', $accountId)
            ->where('id', $quoteId)
            ->with('options')
            ->firstOrFail();
        $this->assertExternalTenantMatchesQuote($quote, $performer);

        if ($quote->isExpired()) {
            throw new BusinessException(
                'انتهت صلاحية عرض الأسعار. يرجى إعادة جلب الأسعار.',
                'ERR_QUOTE_EXPIRED',
                422
            );
        }

        if ($quote->status !== RateQuote::STATUS_COMPLETED) {
            throw new BusinessException(
                'عرض الأسعار غير مكتمل.',
                'ERR_QUOTE_NOT_COMPLETED',
                422
            );
        }

        if ($optionId !== null) {
            $option = $quote->options->firstWhere('id', $optionId);
            if (! $option) {
                throw new BusinessException(
                    'The selected offer does not belong to this quote.',
                    'ERR_OPTION_NOT_IN_QUOTE',
                    422
                );
            }

            if (! $option->is_available) {
                throw new BusinessException(
                    'The selected offer is not currently available.',
                    'ERR_OPTION_NOT_AVAILABLE',
                    422
                );
            }
        } else {
            $available = $quote->options->where('is_available', true);
            $option = match ($strategy) {
                'cheapest' => $available->sortBy('retail_rate')->first(),
                'fastest' => $available->sortBy('estimated_days_min')->first(),
                'best_value' => $available->sortByDesc('is_best_value')->sortBy('retail_rate')->first(),
                default => $available->sortBy('retail_rate')->first(),
            };

            if (! $option) {
                throw new BusinessException(
                    'لا توجد خيارات متاحة للاختيار.',
                    'ERR_NO_OPTIONS',
                    422
                );
            }
        }

        $quote->update([
            'selected_option_id' => $option->id,
            'status' => RateQuote::STATUS_SELECTED,
        ]);

        if ($quote->shipment_id) {
            $shipmentUpdates = [];
            foreach ([
                'rate_quote_id' => (string) $quote->id,
                'selected_rate_option_id' => (string) $option->id,
                'status' => Shipment::STATUS_DECLARATION_REQUIRED,
                'status_reason' => 'Dangerous goods declaration must be completed before payment or issuance.',
                'carrier_code' => $option->carrier_code,
                'carrier_name' => $option->carrier_name,
                'service_code' => $option->service_code,
                'service_name' => $option->service_name,
                'shipping_rate' => $option->total_net_rate,
                'platform_fee' => $option->service_fee,
                'profit_margin' => $option->profit_margin,
                'total_charge' => $option->retail_rate,
                'estimated_delivery_at' => $option->estimated_delivery_at
                    ?? now()->addDays($option->estimated_days_max ?? 5),
            ] as $column => $value) {
                if (Schema::hasColumn('shipments', $column)) {
                    $shipmentUpdates[$column] = $value;
                }
            }

            if ($shipmentUpdates !== []) {
                Shipment::query()
                    ->where('account_id', $accountId)
                    ->where('id', $quote->shipment_id)
                    ->update($shipmentUpdates);
            }

            $this->dgComplianceService->beginShipmentDeclarationGate(
                accountId: $accountId,
                shipmentId: (string) $quote->shipment_id,
                declaredBy: (string) $performer->id,
                locale: (string) ($performer->locale ?? app()->getLocale() ?: 'en'),
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent(),
            );
        }

        $this->auditService->info(
            $accountId,
            $performer->id,
            'rate.selected',
            AuditLog::CATEGORY_ACCOUNT,
            'RateQuote',
            $quote->id,
            null,
            [
                'option_id' => $option->id,
                'carrier' => $option->carrier_code,
                'service' => $option->service_code,
                'retail_rate' => $option->retail_rate,
            ]
        );

        return $quote->fresh(['options', 'selectedOption']);
    }

    public function reprice(string $accountId, string $shipmentId, User $performer, ?string $carrierCode = null): RateQuote
    {
        $oldQuotes = RateQuote::query()
            ->where('account_id', $accountId)
            ->where('shipment_id', $shipmentId)
            ->where('status', RateQuote::STATUS_COMPLETED)
            ->get();

        foreach ($oldQuotes as $quote) {
            $quote->update([
                'status' => RateQuote::STATUS_EXPIRED,
                'is_expired' => true,
            ]);
        }

        return $this->fetchRates($accountId, $shipmentId, $performer, $carrierCode);
    }

    public function getQuote(string $accountId, string $quoteId, User $performer): array
    {
        $quote = RateQuote::query()
            ->where('account_id', $accountId)
            ->where('id', $quoteId)
            ->with(['options', 'shipment'])
            ->firstOrFail();
        $this->assertExternalTenantMatchesQuote($quote, $performer);

        $canViewFinancial = $performer->hasPermission('rates.view_breakdown');
        $selectedOptionId = trim((string) ($quote->shipment?->selected_rate_option_id ?: $quote->selected_option_id ?: ''));

        return [
            'quote' => $quote,
            'options' => $quote->options
                ->map(fn (RateOption $opt): array => $this->formatQuoteOption(
                    $opt,
                    $canViewFinancial,
                    $selectedOptionId !== '' && (string) $opt->id === $selectedOptionId
                ))
                ->values()
                ->all(),
            'is_expired' => $quote->isExpired(),
            'expires_in_seconds' => $quote->expires_at ? max(0, now()->diffInSeconds($quote->expires_at, false)) : null,
        ];
    }

    public function listPricingRules(string $accountId): Collection
    {
        return $this->loadPricingRulesForAccount($accountId);
    }

    public function createPricingRule(string $accountId, array $data, User $performer): PricingRule
    {
        if (
            ! $performer->hasPermission('pricing_rules.manage')
            && ! $performer->hasPermission('rates.manage_rules')
        ) {
            throw BusinessException::permissionDenied();
        }

        $rule = PricingRule::create(array_merge($data, ['account_id' => $accountId]));

        $this->auditService->info(
            $accountId,
            $performer->id,
            'pricing_rule.created',
            AuditLog::CATEGORY_ACCOUNT,
            'PricingRule',
            $rule->id,
            null,
            ['name' => $rule->name, 'priority' => $rule->priority]
        );

        return $rule;
    }

    public function updatePricingRule(string $accountId, string $ruleId, array $data, User $performer): PricingRule
    {
        if (
            ! $performer->hasPermission('pricing_rules.manage')
            && ! $performer->hasPermission('rates.manage_rules')
        ) {
            throw BusinessException::permissionDenied();
        }

        $rule = PricingRule::query()
            ->where('account_id', $accountId)
            ->where('id', $ruleId)
            ->firstOrFail();

        $old = $rule->toArray();
        $rule->update($data);

        $this->auditService->info(
            $accountId,
            $performer->id,
            'pricing_rule.updated',
            AuditLog::CATEGORY_ACCOUNT,
            'PricingRule',
            $rule->id,
            $old,
            $rule->toArray()
        );

        return $rule->fresh();
    }

    public function deletePricingRule(string $accountId, string $ruleId, User $performer): void
    {
        if (
            ! $performer->hasPermission('pricing_rules.manage')
            && ! $performer->hasPermission('rates.manage_rules')
        ) {
            throw BusinessException::permissionDenied();
        }

        $rule = PricingRule::query()
            ->where('account_id', $accountId)
            ->where('id', $ruleId)
            ->firstOrFail();

        $rule->delete();

        $this->auditService->warning(
            $accountId,
            $performer->id,
            'pricing_rule.deleted',
            AuditLog::CATEGORY_ACCOUNT,
            'PricingRule',
            $ruleId,
            ['name' => $rule->name],
            null
        );
    }

    private function resolveShipmentQuote(string $accountId, string $shipmentId): RateQuote
    {
        $shipment = Shipment::query()
            ->where('account_id', $accountId)
            ->where('id', $shipmentId)
            ->firstOrFail();

        $query = RateQuote::query()
            ->where('account_id', $accountId)
            ->where('shipment_id', $shipmentId)
            ->with('options');

        $linkedQuoteId = trim((string) ($shipment->rate_quote_id ?? ''));
        if ($linkedQuoteId !== '') {
            $quote = (clone $query)->where('id', $linkedQuoteId)->first();
            if ($quote instanceof RateQuote) {
                return $quote;
            }
        }

        $quote = (clone $query)
            ->whereIn('status', [RateQuote::STATUS_COMPLETED, RateQuote::STATUS_SELECTED])
            ->latest('created_at')
            ->first();

        if ($quote instanceof RateQuote) {
            return $quote;
        }

        throw new BusinessException(
            'No offers are available for this shipment yet.',
            'ERR_SHIPMENT_OFFERS_NOT_READY',
            422,
            [
                'shipment_id' => $shipmentId,
                'shipment_status' => (string) $shipment->status,
                'next_action' => 'Fetch shipment rates after the shipment reaches the ready_for_rates stage.',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatQuoteOption(RateOption $option, bool $canViewFinancial, bool $isSelected = false): array
    {
        $notes = collect((array) data_get($option->rule_evaluation_log, 'carrier_alerts', []))
            ->map(static fn (array $alert): ?string => filled($alert['message'] ?? null) ? (string) $alert['message'] : null)
            ->filter()
            ->values()
            ->all();

        $restrictions = [];
        if (! $option->is_available && filled($option->unavailable_reason)) {
            $restrictions[] = (string) $option->unavailable_reason;
        }

        $payload = [
            'id' => (string) $option->id,
            'carrier_code' => (string) $option->carrier_code,
            'carrier_name' => (string) $option->carrier_name,
            'service_code' => (string) $option->service_code,
            'service_name' => (string) $option->service_name,
            'retail_rate' => $option->retail_rate,
            'currency' => (string) $option->currency,
            'is_available' => (bool) $option->is_available,
            'unavailable_reason' => $option->unavailable_reason,
            'estimated_delivery' => [
                'days_min' => $option->estimated_days_min,
                'days_max' => $option->estimated_days_max,
                'at' => $option->estimated_delivery_at,
                'label' => $option->deliveryEstimate(),
            ],
            'badges' => $option->badges(),
            'notes' => $notes,
            'restrictions' => $restrictions,
            'is_selected' => $isSelected,
        ];

        if ($canViewFinancial) {
            $payload['net_rate'] = $option->net_rate;
            $payload['fuel_surcharge'] = $option->fuel_surcharge;
            $payload['other_surcharges'] = $option->other_surcharges;
            $payload['total_net_rate'] = $option->total_net_rate;
            $payload['markup_amount'] = $option->markup_amount;
            $payload['profit_margin'] = $option->profit_margin;
            $payload['pricing_breakdown'] = $option->pricing_breakdown;
            $payload['pricing_breakdown_id'] = $option->pricing_breakdown_id;
            $payload['rule_evaluation_log'] = $option->rule_evaluation_log;
        }

        return $payload;
    }

    private function buildContext(Shipment $shipment, ?string $carrierCode): array
    {
        return [
            'carrier_code' => $carrierCode,
            'origin_country' => $shipment->sender_country,
            'origin_city' => $shipment->sender_city,
            'destination_country' => $shipment->recipient_country,
            'destination_city' => $shipment->recipient_city,
            'sender_name' => $shipment->sender_name,
            'sender_phone' => $shipment->sender_phone,
            'sender_address_1' => $shipment->sender_address_1,
            'sender_address_2' => $shipment->sender_address_2,
            'sender_postal_code' => $shipment->sender_postal_code,
            'sender_state' => $shipment->sender_state,
            'recipient_name' => $shipment->recipient_name,
            'recipient_phone' => $shipment->recipient_phone,
            'recipient_address_1' => $shipment->recipient_address_1,
            'recipient_address_2' => $shipment->recipient_address_2,
            'recipient_postal_code' => $shipment->recipient_postal_code,
            'recipient_state' => $shipment->recipient_state,
            'total_weight' => (float) $shipment->total_weight,
            'chargeable_weight' => (float) ($shipment->chargeable_weight ?? $shipment->total_weight),
            'parcels_count' => $shipment->parcels_count,
            'is_cod' => $shipment->is_cod,
            'is_international' => $shipment->is_international,
            'store_id' => $shipment->store_id,
            'shipment_type' => $shipment->is_international ? 'international' : 'domestic',
            'currency' => $shipment->currency ?? 'SAR',
            'system_of_measure_type' => 'METRIC',
            'parcels' => $shipment->parcels
                ->map(fn ($parcel) => [
                    'weight' => (float) $parcel->weight,
                    'length' => $parcel->length !== null ? (float) $parcel->length : null,
                    'width' => $parcel->width !== null ? (float) $parcel->width : null,
                    'height' => $parcel->height !== null ? (float) $parcel->height : null,
                    'packaging_type' => $parcel->packaging_type,
                ])
                ->values()
                ->all(),
        ];
    }

    private function loadPricingRulesForAccount(string $accountId): Collection
    {
        return PricingRule::query()
            ->where(function ($query) use ($accountId): void {
                $query->where('account_id', $accountId)->orWhereNull('account_id');
            })
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();
    }

    /**
     * @param array<string, mixed>|null $current
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    private function mergeRuleEvaluationLog(?array $current, array $raw): ?array
    {
        $alerts = array_values(array_filter(array_map(static fn (array $alert): array => [
            'code' => (string) ($alert['code'] ?? ''),
            'message' => (string) ($alert['message'] ?? ''),
            'alert_type' => (string) ($alert['alert_type'] ?? ''),
        ], (array) ($raw['carrier_alerts'] ?? [])), static fn (array $alert): bool => $alert['code'] !== '' || $alert['message'] !== ''));

        if ($alerts === [] && $current !== null) {
            return $current;
        }

        $log = $current ?? [];
        if ($alerts !== []) {
            $log['carrier_alerts'] = $alerts;
        }

        return $log;
    }

    /**
     * @param array<int, RateOption> $options
     */
    private function assignBadges(array $options): void
    {
        if ($options === []) {
            return;
        }

        $available = collect($options)->where('is_available', true);
        if ($available->isEmpty()) {
            return;
        }

        $cheapest = $available->sortBy('retail_rate')->first();
        $fastest = $available->sortBy('estimated_days_min')->first();
        $bestValue = $available
            ->sortBy(fn ($option) => ($option->retail_rate * 0.6) + ((($option->estimated_days_min ?? 5) * 10) * 0.4))
            ->first();

        if ($cheapest) {
            $cheapest->update(['is_cheapest' => true]);
        }
        if ($fastest) {
            $fastest->update(['is_fastest' => true]);
        }
        if ($bestValue) {
            $bestValue->update(['is_best_value' => true]);
        }

        if ($bestValue && $cheapest && $bestValue->id !== $cheapest->id) {
            $bestValue->update(['is_recommended' => true]);
        } elseif ($cheapest) {
            $cheapest->update(['is_recommended' => true]);
        }
    }

    private function assertExternalTenantMatchesShipment(Shipment $shipment, User $performer): void
    {
        if (strtolower(trim((string) ($performer->user_type ?? ''))) !== 'external') {
            return;
        }

        $userAccountId = trim((string) ($performer->account_id ?? ''));
        $resourceAccountId = trim((string) ($shipment->account_id ?? ''));

        if ($userAccountId !== '' && $resourceAccountId !== '' && $userAccountId !== $resourceAccountId) {
            throw (new ModelNotFoundException())->setModel(Shipment::class, [(string) $shipment->id]);
        }
    }

    private function assertExternalTenantMatchesQuote(RateQuote $quote, User $performer): void
    {
        if (strtolower(trim((string) ($performer->user_type ?? ''))) !== 'external') {
            return;
        }

        $userAccountId = trim((string) ($performer->account_id ?? ''));
        $resourceAccountId = trim((string) ($quote->account_id ?? ''));

        if ($userAccountId !== '' && $resourceAccountId !== '' && $userAccountId !== $resourceAccountId) {
            throw (new ModelNotFoundException())->setModel(RateQuote::class, [(string) $quote->id]);
        }
    }
}
