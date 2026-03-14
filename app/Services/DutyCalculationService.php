<?php

namespace App\Services;

use App\Models\HsCode;
use App\Models\CustomsDeclaration;
use App\Models\ShipmentCharge;
use App\Models\TariffRule;
use Illuminate\Support\Str;

/**
 * CBEX GROUP — Duty Calculation Engine
 *
 * Calculates import/export duties, taxes, and customs fees
 * based on HS codes, origin/destination, and trade agreements.
 */
class DutyCalculationService
{
    // ── Trade agreement zones (simplified) ───────────────────
    protected array $freeTradeZones = [
        'GCC' => ['SA', 'AE', 'BH', 'KW', 'OM', 'QA'],
        'EU'  => ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'SE', 'PL', 'DK', 'FI', 'IE', 'PT', 'GR', 'CZ', 'RO', 'HU', 'BG', 'HR', 'SK', 'SI', 'LT', 'LV', 'EE', 'CY', 'LU', 'MT'],
    ];

    // ── VAT rates by country ─────────────────────────────────
    protected array $vatRates = [
        'SA' => 15.0,  'AE' => 5.0,  'BH' => 10.0, 'KW' => 0.0,
        'OM' => 5.0,   'QA' => 0.0,  'EG' => 14.0, 'JO' => 16.0,
        'US' => 0.0,   'GB' => 20.0, 'DE' => 19.0, 'FR' => 20.0,
        'CN' => 13.0,  'IN' => 18.0, 'TR' => 20.0, 'MY' => 8.0,
    ];

    /**
     * Calculate full duty breakdown for a shipment
     */
    public function calculate(array $params): array
    {
        $items = $params['items'] ?? [];
        $originCountry = strtoupper($params['origin_country'] ?? '');
        $destCountry = strtoupper($params['destination_country'] ?? '');
        $declaredValue = (float)($params['declared_value'] ?? 0);
        $currency = $params['currency'] ?? 'SAR';
        $incoterm = $params['incoterm'] ?? 'DAP';
        $insurance = (float)($params['insurance_value'] ?? 0);
        $freight = (float)($params['freight_cost'] ?? 0);

        // CIF Value = Cost + Insurance + Freight
        $cifValue = $this->calculateCIF($declaredValue, $insurance, $freight, $incoterm);

        // Check free trade zone exemption
        $freeTradeExempt = $this->checkFreeTradeExemption($originCountry, $destCountry);

        // Calculate duties per item
        $itemDuties = [];
        $totalDuty = 0;
        $totalTax = 0;

        foreach ($items as $item) {
            $itemDuty = $this->calculateItemDuty($item, $destCountry, $cifValue, $freeTradeExempt);
            $itemDuties[] = $itemDuty;
            $totalDuty += $itemDuty['duty_amount'];
            $totalTax += $itemDuty['tax_amount'];
        }

        // If no items, calculate on total declared value
        if (empty($items)) {
            $dutyRate = $freeTradeExempt ? 0 : $this->getDefaultDutyRate($destCountry);
            $totalDuty = round($cifValue * ($dutyRate / 100), 2);
            $totalTax = round(($cifValue + $totalDuty) * ($this->getVatRate($destCountry) / 100), 2);
        }

        // Additional fees
        $customsFee = $this->calculateCustomsFee($cifValue, $destCountry);
        $inspectionFee = ($params['inspection_required'] ?? false) ? $this->getInspectionFee($destCountry) : 0;

        $totalCharges = $totalDuty + $totalTax + $customsFee + $inspectionFee;

        return [
            'calculation_id' => Str::uuid()->toString(),
            'origin_country' => $originCountry,
            'destination_country' => $destCountry,
            'currency' => $currency,
            'incoterm' => $incoterm,
            'cif_value' => $cifValue,
            'free_trade_exempt' => $freeTradeExempt,
            'breakdown' => [
                'declared_value' => $declaredValue,
                'insurance' => $insurance,
                'freight' => $freight,
                'cif_value' => $cifValue,
                'duty_amount' => $totalDuty,
                'vat_rate' => $this->getVatRate($destCountry),
                'vat_amount' => $totalTax,
                'customs_fee' => $customsFee,
                'inspection_fee' => $inspectionFee,
                'total_charges' => $totalCharges,
            ],
            'item_duties' => $itemDuties,
            'total_payable' => $totalCharges,
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate CIF value based on incoterm
     */
    protected function calculateCIF(float $value, float $insurance, float $freight, string $incoterm): float
    {
        return match (strtoupper($incoterm)) {
            'EXW' => $value + $insurance + $freight,
            'FOB', 'FCA' => $value + $insurance + $freight,
            'CFR', 'CPT' => $value + $insurance,
            'CIF', 'CIP' => $value,
            'DAP', 'DDP', 'DAT' => $value,
            default => $value + $insurance + $freight,
        };
    }

    /**
     * Check if origin/destination are in same free trade zone
     */
    protected function checkFreeTradeExemption(string $origin, string $dest): bool
    {
        foreach ($this->freeTradeZones as $zone => $countries) {
            if (in_array($origin, $countries) && in_array($dest, $countries)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate duty for a single item based on HS code
     */
    protected function calculateItemDuty(array $item, string $destCountry, float $cifValue, bool $exempt): array
    {
        $hsCode = $item['hs_code'] ?? '';
        $quantity = (int)($item['quantity'] ?? 1);
        $itemValue = (float)($item['value'] ?? 0);
        $weight = (float)($item['weight'] ?? 0);

        // Lookup HS code duty rate
        $dutyRate = 0;
        if ($hsCode && !$exempt) {
            $hsRecord = HsCode::where('code', $hsCode)
                ->where(function ($q) use ($destCountry) {
                    $q->where('country', $destCountry)->orWhereNull('country');
                })
                ->orderByRaw("CASE WHEN country = ? THEN 0 ELSE 1 END", [$destCountry])
                ->first();

            $dutyRate = $hsRecord ? (float)$hsRecord->duty_rate : $this->getDefaultDutyRate($destCountry);
        }

        $dutyAmount = round($itemValue * ($dutyRate / 100), 2);
        $vatRate = $this->getVatRate($destCountry);
        $taxAmount = round(($itemValue + $dutyAmount) * ($vatRate / 100), 2);

        return [
            'description' => $item['description'] ?? '',
            'hs_code' => $hsCode,
            'quantity' => $quantity,
            'value' => $itemValue,
            'weight' => $weight,
            'duty_rate' => $dutyRate,
            'duty_amount' => $dutyAmount,
            'vat_rate' => $vatRate,
            'tax_amount' => $taxAmount,
            'total' => $dutyAmount + $taxAmount,
            'exempt' => $exempt,
        ];
    }

    /**
     * Get VAT rate for a country
     */
    protected function getVatRate(string $country): float
    {
        return $this->vatRates[$country] ?? 15.0;
    }

    /**
     * Get default duty rate for a country
     */
    protected function getDefaultDutyRate(string $country): float
    {
        return match ($country) {
            'SA' => 5.0,  'AE' => 5.0,  'US' => 3.5,
            'GB' => 4.0,  'CN' => 8.0,  'IN' => 10.0,
            default => 5.0,
        };
    }

    /**
     * Calculate customs processing fee
     */
    protected function calculateCustomsFee(float $cifValue, string $country): float
    {
        return match ($country) {
            'SA' => max(50, round($cifValue * 0.002, 2)),
            'AE' => max(100, round($cifValue * 0.001, 2)),
            default => max(25, round($cifValue * 0.0025, 2)),
        };
    }

    /**
     * Get inspection fee for a country
     */
    protected function getInspectionFee(string $country): float
    {
        return match ($country) {
            'SA' => 200, 'AE' => 250, 'US' => 500,
            default => 150,
        };
    }

    /**
     * Store duty calculation as shipment charges
     */
    public function storeAsCharges(string $shipmentId, array $calculation): void
    {
        $breakdown = $calculation['breakdown'];

        $charges = [
            ['type' => 'customs_duty', 'amount' => $breakdown['duty_amount']],
            ['type' => 'vat', 'amount' => $breakdown['vat_amount']],
            ['type' => 'customs_fee', 'amount' => $breakdown['customs_fee']],
        ];

        if ($breakdown['inspection_fee'] > 0) {
            $charges[] = ['type' => 'inspection_fee', 'amount' => $breakdown['inspection_fee']];
        }

        foreach ($charges as $charge) {
            if ($charge['amount'] > 0) {
                ShipmentCharge::create([
                    'id' => Str::uuid(),
                    'shipment_id' => $shipmentId,
                    'charge_type' => $charge['type'],
                    'description' => $this->chargeLabel($charge['type']),
                    'amount' => $charge['amount'],
                    'currency' => $calculation['currency'],
                    'status' => 'pending',
                ]);
            }
        }
    }

    protected function chargeLabel(string $type): string
    {
        return match ($type) {
            'customs_duty' => 'رسوم جمركية',
            'vat' => 'ضريبة القيمة المضافة',
            'customs_fee' => 'رسوم تخليص جمركي',
            'inspection_fee' => 'رسوم فحص',
            default => $type,
        };
    }
}
