<?php

namespace App\Services;

/**
 * TaxService — A-1: VAT/Tax Abstraction
 *
 * Centralized tax calculation. Reads rates from config/tax.php.
 * Existing Invoice / PaymentService / DutyCalculationService are NOT modified.
 * They can optionally call TaxService::rateFor() when ready.
 *
 * Usage:
 *   TaxService::rateFor('SA')              → 15.0
 *   TaxService::calculate(100.00, 'SA')    → 15.00
 *   TaxService::addTax(100.00, 'SA')       → 115.00
 *   TaxService::breakdown(100.00, 'SA')    → ['subtotal'=>100, 'tax_rate'=>15, ...]
 */
class TaxService
{
    /**
     * Get the VAT rate for a country. Fallback → default_rate.
     */
    public static function rateFor(?string $country = null): float
    {
        if (!$country) {
            return (float) config('tax.default_rate', 15.0);
        }

        $rate = config('tax.rates.' . strtoupper($country));

        return $rate !== null ? (float) $rate : (float) config('tax.default_rate', 15.0);
    }

    /**
     * Calculate tax amount on a subtotal.
     */
    public static function calculate(float $subtotal, ?string $country = null): float
    {
        return round($subtotal * (self::rateFor($country) / 100), 2);
    }

    /**
     * Add tax to subtotal → total.
     */
    public static function addTax(float $subtotal, ?string $country = null): float
    {
        return round($subtotal + self::calculate($subtotal, $country), 2);
    }

    /**
     * Extract tax from a tax-inclusive total.
     */
    public static function extractTax(float $total, ?string $country = null): float
    {
        $rate = self::rateFor($country);

        return $rate > 0
            ? round($total - ($total / (1 + $rate / 100)), 2)
            : 0.0;
    }

    /**
     * Full breakdown.
     */
    public static function breakdown(float $subtotal, ?string $country = null): array
    {
        $rate = self::rateFor($country);
        $tax = round($subtotal * ($rate / 100), 2);

        return [
            'subtotal'   => round($subtotal, 2),
            'tax_rate'   => $rate,
            'tax_amount' => $tax,
            'total'      => round($subtotal + $tax, 2),
            'currency'   => self::currencyFor($country),
            'label'      => config('tax.tax_label', 'ضريبة القيمة المضافة'),
        ];
    }

    /**
     * Get currency code for a country.
     */
    public static function currencyFor(?string $country = null): string
    {
        if (!$country) {
            return config('tax.default_currency', 'SAR');
        }

        return config('tax.currencies.' . strtoupper($country), config('tax.default_currency', 'SAR'));
    }

    /**
     * Get all configured country rates.
     */
    public static function allRates(): array
    {
        return config('tax.rates', []);
    }
}
