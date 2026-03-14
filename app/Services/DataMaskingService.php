<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;

/**
 * DataMaskingService — Central service for masking sensitive data.
 *
 * FR-IAM-012: Financial data masking
 * - Card numbers → show last 4 digits
 * - Financial fields (Net/Retail/Profit) → permission-based
 * - IBAN/bank accounts → partial masking
 *
 * This service NEVER stores unmasked data — it masks at the presentation layer.
 */
class DataMaskingService
{
    // ─── Card Number Masking ─────────────────────────────────────

    /**
     * Mask a credit/debit card number, showing only last 4 digits.
     *
     * Handles various card lengths (13-19 digits).
     * Returns masked format: •••• •••• •••• 1234
     *
     * @param string|null $cardNumber Raw card number (with or without spaces/dashes)
     * @return string|null Masked card number or null if input is null
     */
    public static function maskCardNumber(?string $cardNumber): ?string
    {
        if ($cardNumber === null || $cardNumber === '') {
            return $cardNumber;
        }

        // Strip non-digits
        $digits = preg_replace('/\D/', '', $cardNumber);
        $length = strlen($digits);

        if ($length < 4) {
            // Extremely short — mask entirely
            return str_repeat('•', $length);
        }

        $last4 = substr($digits, -4);
        $maskedLength = $length - 4;

        // Format in groups of 4 for readability
        $masked = str_repeat('•', $maskedLength);
        $full = $masked . $last4;

        // Insert spaces every 4 characters
        return trim(chunk_split($full, 4, ' '));
    }

    /**
     * Get only the last 4 digits of a card number.
     */
    public static function lastFourDigits(?string $cardNumber): ?string
    {
        if ($cardNumber === null || $cardNumber === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $cardNumber);
        if (strlen($digits) < 4) {
            return null;
        }

        return substr($digits, -4);
    }

    // ─── IBAN / Bank Account Masking ─────────────────────────────

    /**
     * Mask an IBAN or bank account number.
     * Shows first 4 + last 4 characters: SA12••••••••3456
     */
    public static function maskIban(?string $iban): ?string
    {
        if ($iban === null || $iban === '') {
            return $iban;
        }

        $clean = preg_replace('/\s/', '', $iban);
        $length = strlen($clean);

        if ($length <= 8) {
            return substr($clean, 0, 2) . str_repeat('•', max(0, $length - 4)) . substr($clean, -2);
        }

        return substr($clean, 0, 4) . str_repeat('•', $length - 8) . substr($clean, -4);
    }

    // ─── Email Masking ───────────────────────────────────────────

    /**
     * Mask an email address: a••••@example.com
     */
    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return $email;
        }

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }

        $local = $parts[0];
        $domain = $parts[1];

        $maskedLocal = strlen($local) <= 2
            ? $local[0] . '•'
            : $local[0] . str_repeat('•', strlen($local) - 2) . substr($local, -1);

        return $maskedLocal . '@' . $domain;
    }

    // ─── Phone Masking ───────────────────────────────────────────

    /**
     * Mask a phone number, showing last 4 digits: •••••••1234
     */
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return $phone;
        }

        $digits = preg_replace('/\D/', '', $phone);
        $length = strlen($digits);

        if ($length <= 4) {
            return $phone;
        }

        return str_repeat('•', $length - 4) . substr($digits, -4);
    }

    // ─── Financial Fields ────────────────────────────────────────

    /**
     * List of financial fields that require `financial:profit.view` permission.
     * Used to strip/mask these fields from API responses.
     */
    public static function profitSensitiveFields(): array
    {
        return [
            'net_rate',
            'net_cost',
            'retail_rate',
            'retail_cost',
            'profit',
            'profit_margin',
            'margin_percentage',
            'pricing_breakdown',
            'cost_breakdown',
            'carrier_cost',
            'markup',
            'markup_percentage',
            'fees_breakdown',
        ];
    }

    /**
     * List of general financial fields that require `financial:view` permission.
     */
    public static function generalFinancialFields(): array
    {
        return [
            'total_amount',
            'subtotal',
            'tax_amount',
            'discount_amount',
            'balance',
            'wallet_balance',
            'invoice_total',
            'cod_amount',
        ];
    }

    /**
     * List of card/payment fields that require `financial:cards.view` permission.
     */
    public static function cardSensitiveFields(): array
    {
        return [
            'card_number',
            'card_holder_name',
            'card_expiry',
            'iban',
            'bank_account',
            'bank_name',
            'payment_token',
        ];
    }

    /**
     * Filter an array of data based on user's financial permissions.
     *
     * This is the main method that modules use to mask financial data in their responses.
     *
     * @param array     $data           The data array (e.g., shipment, invoice, report row)
     * @param User|null $user           The requesting user (null = mask everything)
     * @param bool      $replaceWithNull If true, set masked fields to null. If false, remove them.
     * @return array Filtered data
     */
    public static function filterFinancialData(array $data, ?User $user, bool $replaceWithNull = true): array
    {
        $canViewFinancial = $user && $user->hasPermission('financial.view');
        $canViewProfit    = $user && $user->hasPermission('financial.profit.view');
        $canViewCards     = $user && $user->hasPermission('financial.cards.view');

        $result = $data;

        // Mask profit-sensitive fields
        if (!$canViewProfit) {
            foreach (self::profitSensitiveFields() as $field) {
                if (array_key_exists($field, $result)) {
                    $result[$field] = $replaceWithNull ? null : '***';
                }
            }
        }

        // Mask general financial fields
        if (!$canViewFinancial) {
            foreach (self::generalFinancialFields() as $field) {
                if (array_key_exists($field, $result)) {
                    $result[$field] = $replaceWithNull ? null : '***';
                }
            }
            // If can't view financial at all, also mask profit fields
            foreach (self::profitSensitiveFields() as $field) {
                if (array_key_exists($field, $result)) {
                    $result[$field] = $replaceWithNull ? null : '***';
                }
            }
        }

        // Mask card/payment fields
        if (!$canViewCards) {
            foreach (self::cardSensitiveFields() as $field) {
                if (array_key_exists($field, $result)) {
                    $value = $result[$field];
                    if ($field === 'card_number') {
                        $result[$field] = self::maskCardNumber($value);
                    } elseif ($field === 'iban' || $field === 'bank_account') {
                        $result[$field] = self::maskIban($value);
                    } else {
                        $result[$field] = $replaceWithNull ? null : '***';
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Filter a collection of data arrays.
     */
    public static function filterFinancialCollection(array $items, ?User $user): array
    {
        return array_map(fn ($item) => self::filterFinancialData($item, $user), $items);
    }

    /**
     * Check if a user can view profit/pricing data.
     */
    public static function canViewProfitData(?User $user): bool
    {
        return $user && $user->hasPermission('financial.profit.view');
    }

    /**
     * Check if a user can view general financial data.
     */
    public static function canViewFinancialData(?User $user): bool
    {
        return $user && $user->hasPermission('financial.view');
    }

    /**
     * Check if a user can view unmasked card data.
     */
    public static function canViewCardData(?User $user): bool
    {
        return $user && $user->hasPermission('financial.cards.view');
    }

    /**
     * Get a summary of what the user CAN see (useful for UI to know which columns to render).
     */
    public static function visibilityMap(?User $user): array
    {
        return [
            'financial_general'  => self::canViewFinancialData($user),
            'financial_profit'   => self::canViewProfitData($user),
            'financial_cards'    => self::canViewCardData($user),
            'masked_fields'      => self::getMaskedFieldsForUser($user),
            'visible_fields'     => self::getVisibleFieldsForUser($user),
        ];
    }

    /**
     * Get list of fields that will be masked for this user.
     */
    public static function getMaskedFieldsForUser(?User $user): array
    {
        $masked = [];

        if (!self::canViewFinancialData($user)) {
            $masked = array_merge($masked, self::generalFinancialFields(), self::profitSensitiveFields());
        } elseif (!self::canViewProfitData($user)) {
            $masked = array_merge($masked, self::profitSensitiveFields());
        }

        if (!self::canViewCardData($user)) {
            $masked = array_merge($masked, self::cardSensitiveFields());
        }

        return array_unique($masked);
    }

    /**
     * Get list of financial fields visible to this user.
     */
    public static function getVisibleFieldsForUser(?User $user): array
    {
        $all = array_merge(
            self::generalFinancialFields(),
            self::profitSensitiveFields(),
            self::cardSensitiveFields()
        );

        $masked = self::getMaskedFieldsForUser($user);

        return array_values(array_diff($all, $masked));
    }

    // ─── Audit Integration ───────────────────────────────────────

    /**
     * Mask sensitive values in audit log old_values/new_values to prevent data leakage.
     * Card numbers in audit logs should always be masked.
     */
    public static function sanitizeForAuditLog(array $values): array
    {
        $sanitized = $values;

        // Always mask card numbers in audit logs
        foreach (['card_number', 'credit_card', 'debit_card'] as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = self::maskCardNumber($sanitized[$field]);
            }
        }

        // Always mask IBANs in audit logs
        foreach (['iban', 'bank_account'] as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = self::maskIban($sanitized[$field]);
            }
        }

        // Remove raw passwords if accidentally included
        foreach (['password', 'password_hash', 'secret', 'token'] as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }
}
