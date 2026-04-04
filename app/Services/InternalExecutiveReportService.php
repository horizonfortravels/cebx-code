<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Support\CanonicalShipmentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InternalExecutiveReportService
{
    /**
     * @return array<string, mixed>
     */
    public function hubCard(): array
    {
        $snapshot = $this->snapshot();

        return [
            'key' => 'executive',
            'title' => 'Executive metrics',
            'eyebrow' => 'Management snapshot',
            'description' => 'Super-admin-only shipment economics, carrier performance, and liquidity visibility grounded in canonical shipment and wallet data.',
            'summary' => 'Quoted commercial totals stay currency-scoped, realized activity stays limited to safe wallet signals, and no export or mutation controls are exposed from this slice.',
            'route_name' => null,
            'cta_label' => null,
            'metrics' => [
                $this->metric('Total shipments', $snapshot['total_shipments']),
                $this->metric('Delivered (normalized)', $snapshot['delivered_shipments']),
                $this->metric('Open exceptions', $snapshot['open_exception_backlog']),
                $this->metric('Active holds', $snapshot['active_hold_count']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $snapshot = $this->snapshot();

        return [
            'key' => 'executive',
            'title' => 'Executive profitability dashboard',
            'eyebrow' => 'Executive analytics / platform profitability',
            'description' => 'Management-oriented shipment volume, quoted commercial snapshot, carrier performance, and safe wallet activity. Quoted economics come from shipment pricing snapshots; realized carrier settlement remains out of scope here.',
            'metrics' => [
                $this->metric('Total shipments', $snapshot['total_shipments']),
                $this->metric('Delivered (normalized)', $snapshot['delivered_shipments']),
                $this->metric('Open exceptions', $snapshot['open_exception_backlog']),
                $this->metric('Active holds', $snapshot['active_hold_count']),
            ],
            'breakdowns' => [
                [
                    'title' => 'Quoted commercial snapshot',
                    'items' => $snapshot['quoted_items'],
                ],
                [
                    'title' => 'Carrier performance snapshot',
                    'items' => $snapshot['carrier_items'],
                ],
                [
                    'title' => 'Safe wallet activity snapshot',
                    'items' => $snapshot['wallet_items'],
                ],
            ],
            'trend' => [
                'title' => 'Recent shipment volume',
                'summary' => 'Shipments created during the last seven days.',
                'points' => $snapshot['trend_points'],
            ],
            'action_summaries' => [
                [
                    'title' => 'Quoted economics coverage',
                    'detail' => $snapshot['quoted_summary'],
                ],
                [
                    'title' => 'Carrier delivery posture',
                    'detail' => $snapshot['carrier_summary'],
                ],
                [
                    'title' => 'Liquidity snapshot',
                    'detail' => $snapshot['wallet_summary'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $shipments = Shipment::query()
            ->withoutGlobalScopes()
            ->with('carrierShipment')
            ->get();

        $normalizedShipments = $shipments->map(function (Shipment $shipment): array {
            return [
                'shipment' => $shipment,
                'normalized_status' => CanonicalShipmentStatus::fromShipment($shipment),
                'carrier_label' => $this->carrierLabel($shipment),
            ];
        });

        $totalShipments = $normalizedShipments->count();
        $deliveredShipments = $normalizedShipments
            ->where('normalized_status', CanonicalShipmentStatus::DELIVERED)
            ->count();
        $normalizedExceptionCount = $normalizedShipments
            ->where('normalized_status', CanonicalShipmentStatus::EXCEPTION)
            ->count();

        $openExceptionBacklog = Schema::hasTable('shipment_exceptions')
            ? ShipmentException::query()->withoutGlobalScopes()->open()->count()
            : 0;

        $activeHoldCount = Schema::hasTable('wallet_holds')
            ? (int) DB::table('wallet_holds')->where('status', 'active')->count()
            : 0;

        $quotedByCurrency = $normalizedShipments
            ->map(static fn (array $row): Shipment => $row['shipment'])
            ->filter(static function (Shipment $shipment): bool {
                return filled($shipment->currency)
                    && (
                        $shipment->total_charge !== null
                        || $shipment->shipping_rate !== null
                        || $shipment->platform_fee !== null
                        || $shipment->profit_margin !== null
                    );
            })
            ->groupBy(static fn (Shipment $shipment): string => strtoupper((string) $shipment->currency))
            ->map(function (Collection $group, string $currency): array {
                return [
                    'currency' => $currency,
                    'shipment_count' => $group->count(),
                    'quoted_charge' => (float) $group->sum(static fn (Shipment $shipment): float => (float) ($shipment->total_charge ?? 0)),
                    'carrier_cost_proxy' => (float) $group->sum(static fn (Shipment $shipment): float => (float) ($shipment->shipping_rate ?? 0)),
                    'platform_fee' => (float) $group->sum(static fn (Shipment $shipment): float => (float) ($shipment->platform_fee ?? 0)),
                    'quoted_margin' => (float) $group->sum(static fn (Shipment $shipment): float => (float) ($shipment->profit_margin ?? 0)),
                ];
            })
            ->sortKeys()
            ->values();

        $carrierItems = $normalizedShipments
            ->groupBy('carrier_label')
            ->map(function (Collection $group, string $carrierLabel): array {
                $shipmentCount = $group->count();
                $deliveredCount = $group->where('normalized_status', CanonicalShipmentStatus::DELIVERED)->count();
                $exceptionCount = $group->where('normalized_status', CanonicalShipmentStatus::EXCEPTION)->count();
                $deliveryRate = $shipmentCount > 0 ? round(($deliveredCount / $shipmentCount) * 100) : 0;
                $exceptionRate = $shipmentCount > 0 ? round(($exceptionCount / $shipmentCount) * 100) : 0;

                return [
                    'label' => $carrierLabel,
                    'value' => $shipmentCount,
                    'detail' => "{$deliveredCount} delivered · {$exceptionCount} exception · {$deliveryRate}% delivered · {$exceptionRate}% exception",
                ];
            })
            ->sortByDesc('value')
            ->take(5)
            ->values()
            ->all();

        $quotedItems = $quotedByCurrency->flatMap(function (array $row): array {
            $detail = number_format($row['shipment_count']) . ' shipment(s) currently carry quoted pricing snapshot fields in this currency.';

            return [
                [
                    'label' => $row['currency'] . ' quoted charge',
                    'value' => $row['quoted_charge'],
                    'display' => $this->formatMoney($row['currency'], $row['quoted_charge']),
                    'detail' => $detail,
                ],
                [
                    'label' => $row['currency'] . ' carrier cost proxy',
                    'value' => $row['carrier_cost_proxy'],
                    'display' => $this->formatMoney($row['currency'], $row['carrier_cost_proxy']),
                    'detail' => $detail,
                ],
                [
                    'label' => $row['currency'] . ' platform fee',
                    'value' => $row['platform_fee'],
                    'display' => $this->formatMoney($row['currency'], $row['platform_fee']),
                    'detail' => $detail,
                ],
                [
                    'label' => $row['currency'] . ' quoted margin',
                    'value' => $row['quoted_margin'],
                    'display' => $this->formatMoney($row['currency'], $row['quoted_margin']),
                    'detail' => $detail,
                ],
            ];
        })->values()->all();

        if ($quotedItems === []) {
            $quotedItems = [[
                'label' => 'Quoted shipments with pricing snapshot',
                'value' => 0,
                'detail' => 'No shipment rows currently carry the quoted economics fields needed for this executive slice.',
            ]];
        }

        $walletTopupsByCurrency = $this->sumRowsByCurrency(
            Schema::hasTable('wallet_topups')
                ? DB::table('wallet_topups')
                    ->select('currency', DB::raw('COUNT(*) as item_count'), DB::raw('COALESCE(SUM(amount), 0) as total_amount'))
                    ->where('status', 'success')
                    ->groupBy('currency')
                    ->get()
                : collect()
        );

        $activeHoldsByCurrency = $this->sumRowsByCurrency(
            Schema::hasTable('wallet_holds')
                ? DB::table('wallet_holds')
                    ->select('currency', DB::raw('COUNT(*) as item_count'), DB::raw('COALESCE(SUM(amount), 0) as total_amount'))
                    ->where('status', 'active')
                    ->groupBy('currency')
                    ->get()
                : collect()
        );

        $capturedChargesByCurrency = $this->sumRowsByCurrency(
            Schema::hasTable('wallet_ledger_entries') && Schema::hasTable('billing_wallets')
                ? DB::table('wallet_ledger_entries as ledger')
                    ->join('billing_wallets as wallets', 'wallets.id', '=', 'ledger.wallet_id')
                    ->select('wallets.currency', DB::raw('COUNT(*) as item_count'), DB::raw('COALESCE(SUM(ledger.amount), 0) as total_amount'))
                    ->where('ledger.reference_type', 'shipment')
                    ->whereIn('ledger.transaction_type', ['hold_capture', 'debit'])
                    ->groupBy('wallets.currency')
                    ->get()
                : collect()
        );

        $refundsByCurrency = $this->sumRowsByCurrency(
            Schema::hasTable('wallet_refunds') && Schema::hasTable('billing_wallets')
                ? DB::table('wallet_refunds as refunds')
                    ->join('billing_wallets as wallets', 'wallets.id', '=', 'refunds.wallet_id')
                    ->select('wallets.currency', DB::raw('COUNT(*) as item_count'), DB::raw('COALESCE(SUM(refunds.amount), 0) as total_amount'))
                    ->where('refunds.status', 'processed')
                    ->groupBy('wallets.currency')
                    ->get()
                : collect()
        );

        $walletItems = collect(array_unique(array_merge(
            array_keys($walletTopupsByCurrency),
            array_keys($activeHoldsByCurrency),
            array_keys($capturedChargesByCurrency),
            array_keys($refundsByCurrency),
        )))
            ->sort()
            ->flatMap(function (string $currency) use ($walletTopupsByCurrency, $activeHoldsByCurrency, $capturedChargesByCurrency, $refundsByCurrency): array {
                $topups = $walletTopupsByCurrency[$currency] ?? ['count' => 0, 'amount' => 0.0];
                $holds = $activeHoldsByCurrency[$currency] ?? ['count' => 0, 'amount' => 0.0];
                $charges = $capturedChargesByCurrency[$currency] ?? ['count' => 0, 'amount' => 0.0];
                $refunds = $refundsByCurrency[$currency] ?? ['count' => 0, 'amount' => 0.0];

                return array_values(array_filter([
                    $topups['count'] > 0 || $topups['amount'] > 0 ? [
                        'label' => $currency . ' confirmed top-ups',
                        'value' => $topups['amount'],
                        'display' => $this->formatMoney($currency, $topups['amount']),
                        'detail' => number_format($topups['count']) . ' successful top-up(s).',
                    ] : null,
                    $holds['count'] > 0 || $holds['amount'] > 0 ? [
                        'label' => $currency . ' active holds',
                        'value' => $holds['amount'],
                        'display' => $this->formatMoney($currency, $holds['amount']),
                        'detail' => number_format($holds['count']) . ' active reservation(s).',
                    ] : null,
                    $charges['count'] > 0 || $charges['amount'] > 0 ? [
                        'label' => $currency . ' captured shipment charges',
                        'value' => $charges['amount'],
                        'display' => $this->formatMoney($currency, $charges['amount']),
                        'detail' => number_format($charges['count']) . ' shipment-linked debit entry/entries.',
                    ] : null,
                    $refunds['count'] > 0 || $refunds['amount'] > 0 ? [
                        'label' => $currency . ' refunds',
                        'value' => $refunds['amount'],
                        'display' => $this->formatMoney($currency, $refunds['amount']),
                        'detail' => number_format($refunds['count']) . ' processed refund(s).',
                    ] : null,
                ]));
            })
            ->values()
            ->all();

        if ($walletItems === []) {
            $walletItems = [[
                'label' => 'Wallet activity currently tracked',
                'value' => 0,
                'detail' => 'No safe shipment-linked ledger, top-up, hold, or refund activity is currently available for this executive slice.',
            ]];
        }

        return [
            'total_shipments' => $totalShipments,
            'delivered_shipments' => $deliveredShipments,
            'normalized_exception_count' => $normalizedExceptionCount,
            'open_exception_backlog' => $openExceptionBacklog,
            'active_hold_count' => $activeHoldCount,
            'quoted_items' => $quotedItems,
            'carrier_items' => $carrierItems === [] ? [[
                'label' => 'Carrier performance currently tracked',
                'value' => 0,
                'detail' => 'No shipment rows currently carry the carrier attributes needed for this snapshot.',
            ]] : $carrierItems,
            'wallet_items' => $walletItems,
            'trend_points' => $this->dailyTrend($shipments->pluck('created_at')),
            'quoted_summary' => $this->quotedSummary($quotedByCurrency),
            'carrier_summary' => number_format($deliveredShipments) . ' shipment(s) currently normalize to delivered, '
                . number_format($normalizedExceptionCount) . ' normalize to exception, and '
                . number_format($openExceptionBacklog) . ' shipment-exception record(s) remain open.',
            'wallet_summary' => $this->walletSummary(
                $walletTopupsByCurrency,
                $activeHoldsByCurrency,
                $capturedChargesByCurrency,
                $refundsByCurrency
            ),
        ];
    }

    private function carrierLabel(Shipment $shipment): string
    {
        $label = trim((string) ($shipment->carrier_name ?: $shipment->carrier_code));

        return $label !== '' ? $label : 'Unknown carrier';
    }

    /**
     * @param Collection<int, mixed> $timestamps
     * @return array<int, array{label: string, value: int}>
     */
    private function dailyTrend(Collection $timestamps): array
    {
        $start = now()->subDays(6)->startOfDay();
        $counts = $timestamps
            ->filter()
            ->map(static fn ($value): string => $value->copy()->startOfDay()->toDateString())
            ->countBy();

        return collect(range(0, 6))
            ->map(function (int $offset) use ($counts, $start): array {
                $date = (clone $start)->addDays($offset);
                $key = $date->toDateString();

                return [
                    'label' => $date->format('M d'),
                    'value' => (int) ($counts[$key] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @param iterable<int, object> $rows
     * @return array<string, array{count: int, amount: float}>
     */
    private function sumRowsByCurrency(iterable $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $currency = strtoupper((string) ($row->currency ?? ''));

            if ($currency === '') {
                continue;
            }

            $grouped[$currency] = [
                'count' => (int) ($row->item_count ?? 0),
                'amount' => (float) ($row->total_amount ?? 0),
            ];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param Collection<int, array{currency: string, shipment_count: int, quoted_charge: float, carrier_cost_proxy: float, platform_fee: float, quoted_margin: float}> $quotedByCurrency
     */
    private function quotedSummary(Collection $quotedByCurrency): string
    {
        if ($quotedByCurrency->isEmpty()) {
            return 'No shipment rows currently carry the quoted pricing snapshot fields required for a safe profitability summary.';
        }

        $segments = $quotedByCurrency
            ->map(function (array $row): string {
                return $row['currency'] . ' '
                    . number_format($row['shipment_count']) . ' shipment(s), '
                    . 'charge ' . number_format($row['quoted_charge'], 2) . ', '
                    . 'cost proxy ' . number_format($row['carrier_cost_proxy'], 2) . ', '
                    . 'margin ' . number_format($row['quoted_margin'], 2);
            })
            ->all();

        return 'Quoted economics stay currency-scoped: ' . implode(' · ', $segments) . '.';
    }

    /**
     * @param array<string, array{count: int, amount: float}> $topupsByCurrency
     * @param array<string, array{count: int, amount: float}> $holdsByCurrency
     * @param array<string, array{count: int, amount: float}> $chargesByCurrency
     * @param array<string, array{count: int, amount: float}> $refundsByCurrency
     */
    private function walletSummary(
        array $topupsByCurrency,
        array $holdsByCurrency,
        array $chargesByCurrency,
        array $refundsByCurrency,
    ): string {
        $currencies = array_unique(array_merge(
            array_keys($topupsByCurrency),
            array_keys($holdsByCurrency),
            array_keys($chargesByCurrency),
            array_keys($refundsByCurrency),
        ));

        sort($currencies);

        if ($currencies === []) {
            return 'No safe shipment-linked wallet activity is currently available for executive visibility.';
        }

        $segments = collect($currencies)->map(function (string $currency) use ($topupsByCurrency, $holdsByCurrency, $chargesByCurrency, $refundsByCurrency): string {
            $topups = $topupsByCurrency[$currency] ?? ['count' => 0, 'amount' => 0.0];
            $holds = $holdsByCurrency[$currency] ?? ['count' => 0, 'amount' => 0.0];
            $charges = $chargesByCurrency[$currency] ?? ['count' => 0, 'amount' => 0.0];
            $refunds = $refundsByCurrency[$currency] ?? ['count' => 0, 'amount' => 0.0];

            return $currency . ' top-ups ' . number_format($topups['amount'], 2)
                . ', held ' . number_format($holds['amount'], 2)
                . ', captured ' . number_format($charges['amount'], 2)
                . ', refunded ' . number_format($refunds['amount'], 2);
        })->all();

        return 'Safe wallet activity stays currency-scoped: ' . implode(' · ', $segments) . '.';
    }

    /**
     * @return array{label: string, value: int}
     */
    private function metric(string $label, int $value): array
    {
        return [
            'label' => $label,
            'value' => $value,
        ];
    }

    private function formatMoney(string $currency, float $amount): string
    {
        return strtoupper($currency) . ' ' . number_format($amount, 2);
    }
}
