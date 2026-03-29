<?php

namespace App\Services;

use Illuminate\Support\Str;

class AddressValidationService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validateSection(array $payload, string $prefix): array
    {
        $driver = (string) config('services.address_validation.driver', 'local_rules');

        if ($driver !== 'local_rules') {
            return [
                'classification' => 'warning_only_unverifiable',
                'source' => 'provider_unavailable',
                'title' => __('portal_shipments.address_validation.provider_title'),
                'message' => __('portal_shipments.address_validation.provider_message'),
                'normalized' => $this->normalizeSection($payload, $prefix),
                'changes' => [],
                'warnings' => [[
                    'field_key' => null,
                    'message' => __('portal_shipments.address_validation.provider_message'),
                ]],
            ];
        }

        $normalized = $this->normalizeSection($payload, $prefix);
        $changes = $this->detectChanges($payload, $normalized, $prefix);
        $warnings = $this->buildWarnings($payload, $normalized, $prefix);

        if ($changes !== []) {
            return [
                'classification' => 'normalized_suggestion',
                'source' => 'local_rules',
                'title' => __('portal_shipments.address_validation.suggestion_title'),
                'message' => __('portal_shipments.address_validation.suggestion_message'),
                'normalized' => $normalized,
                'changes' => $changes,
                'warnings' => $warnings,
            ];
        }

        if ($warnings !== []) {
            return [
                'classification' => 'warning_only_unverifiable',
                'source' => 'local_rules',
                'title' => __('portal_shipments.address_validation.warning_title'),
                'message' => __('portal_shipments.address_validation.warning_message'),
                'normalized' => $normalized,
                'changes' => [],
                'warnings' => $warnings,
            ];
        }

        return [
            'classification' => 'exact_validation_pass',
            'source' => 'local_rules',
            'title' => __('portal_shipments.address_validation.pass_title'),
            'message' => __('portal_shipments.address_validation.pass_message'),
            'normalized' => $normalized,
            'changes' => [],
            'warnings' => [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string|null>
     */
    private function normalizeSection(array $payload, string $prefix): array
    {
        $countryKey = $prefix . '_country';
        $stateKey = $prefix . '_state';
        $postalKey = $prefix . '_postal_code';
        $normalizedCountry = $this->normalizeCountry($payload[$countryKey] ?? null);

        return [
            $prefix . '_address_1' => $this->normalizeText($payload[$prefix . '_address_1'] ?? null),
            $prefix . '_address_2' => $this->normalizeText($payload[$prefix . '_address_2'] ?? null),
            $prefix . '_city' => $this->normalizeText($payload[$prefix . '_city'] ?? null),
            $stateKey => $this->normalizeState($normalizedCountry, $payload[$stateKey] ?? null),
            $postalKey => $this->normalizePostalCode($normalizedCountry, $payload[$postalKey] ?? null),
            $countryKey => $normalizedCountry,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|null> $normalized
     * @return array<int, array<string, string|null>>
     */
    private function detectChanges(array $payload, array $normalized, string $prefix): array
    {
        $changes = [];

        foreach ($normalized as $field => $value) {
            $original = $this->normalizeComparable($payload[$field] ?? null);
            $suggested = $this->normalizeComparable($value);

            if ($original === $suggested) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'field_key' => Str::after($field, $prefix . '_'),
                'original' => $original,
                'suggested' => $suggested,
            ];
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|null> $normalized
     * @return array<int, array<string, string|null>>
     */
    private function buildWarnings(array $payload, array $normalized, string $prefix): array
    {
        $warnings = [];
        $country = (string) ($normalized[$prefix . '_country'] ?? '');
        $state = (string) ($normalized[$prefix . '_state'] ?? '');
        $postalCode = (string) ($normalized[$prefix . '_postal_code'] ?? '');
        $rawCountry = $this->normalizeComparable($payload[$prefix . '_country'] ?? null);

        if ($country === '' || strlen($country) !== 2) {
            $warnings[] = [
                'field_key' => 'country',
                'message' => __('portal_shipments.address_validation.warnings.country_iso'),
            ];
        }

        if ($country === 'US' && $state !== '' && strlen($state) !== 2) {
            $warnings[] = [
                'field_key' => 'state',
                'message' => __('portal_shipments.address_validation.warnings.state_code'),
            ];
        }

        if ($country === 'US' && $postalCode === '') {
            $warnings[] = [
                'field_key' => 'postal_code',
                'message' => __('portal_shipments.address_validation.warnings.us_postal_recommended'),
            ];
        }

        if ($rawCountry !== null && strlen($rawCountry) > 2 && $country === $rawCountry) {
            $warnings[] = [
                'field_key' => 'country',
                'message' => __('portal_shipments.address_validation.warnings.country_iso'),
            ];
        }

        return $warnings;
    }

    private function normalizeCountry(mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return null;
        }

        $aliases = [
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'USA' => 'US',
            'U.S.A.' => 'US',
            'US' => 'US',
            'SAUDI ARABIA' => 'SA',
            'KINGDOM OF SAUDI ARABIA' => 'SA',
            'KSA' => 'SA',
            'SA' => 'SA',
            'UNITED ARAB EMIRATES' => 'AE',
            'UAE' => 'AE',
            'AE' => 'AE',
            'UNITED KINGDOM' => 'GB',
            'GREAT BRITAIN' => 'GB',
            'UK' => 'GB',
            'GB' => 'GB',
        ];

        $key = Str::upper($normalized);

        return $aliases[$key] ?? $key;
    }

    private function normalizeState(?string $country, mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return null;
        }

        if ($country !== 'US') {
            return $normalized;
        }

        $stateMap = [
            'ALABAMA' => 'AL',
            'ALASKA' => 'AK',
            'ARIZONA' => 'AZ',
            'ARKANSAS' => 'AR',
            'CALIFORNIA' => 'CA',
            'COLORADO' => 'CO',
            'CONNECTICUT' => 'CT',
            'DELAWARE' => 'DE',
            'DISTRICT OF COLUMBIA' => 'DC',
            'FLORIDA' => 'FL',
            'GEORGIA' => 'GA',
            'HAWAII' => 'HI',
            'IDAHO' => 'ID',
            'ILLINOIS' => 'IL',
            'INDIANA' => 'IN',
            'IOWA' => 'IA',
            'KANSAS' => 'KS',
            'KENTUCKY' => 'KY',
            'LOUISIANA' => 'LA',
            'MAINE' => 'ME',
            'MARYLAND' => 'MD',
            'MASSACHUSETTS' => 'MA',
            'MICHIGAN' => 'MI',
            'MINNESOTA' => 'MN',
            'MISSISSIPPI' => 'MS',
            'MISSOURI' => 'MO',
            'MONTANA' => 'MT',
            'NEBRASKA' => 'NE',
            'NEVADA' => 'NV',
            'NEW HAMPSHIRE' => 'NH',
            'NEW JERSEY' => 'NJ',
            'NEW MEXICO' => 'NM',
            'NEW YORK' => 'NY',
            'NORTH CAROLINA' => 'NC',
            'NORTH DAKOTA' => 'ND',
            'OHIO' => 'OH',
            'OKLAHOMA' => 'OK',
            'OREGON' => 'OR',
            'PENNSYLVANIA' => 'PA',
            'RHODE ISLAND' => 'RI',
            'SOUTH CAROLINA' => 'SC',
            'SOUTH DAKOTA' => 'SD',
            'TENNESSEE' => 'TN',
            'TEXAS' => 'TX',
            'UTAH' => 'UT',
            'VERMONT' => 'VT',
            'VIRGINIA' => 'VA',
            'WASHINGTON' => 'WA',
            'WEST VIRGINIA' => 'WV',
            'WISCONSIN' => 'WI',
            'WYOMING' => 'WY',
        ];

        $key = Str::upper($normalized);

        if (isset($stateMap[$key])) {
            return $stateMap[$key];
        }

        if (strlen($key) === 2) {
            return $key;
        }

        return $normalized;
    }

    private function normalizePostalCode(?string $country, mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return null;
        }

        if ($country === 'US') {
            $digits = preg_replace('/[^0-9]/', '', $normalized) ?? '';

            if (strlen($digits) === 9) {
                return substr($digits, 0, 5) . '-' . substr($digits, 5);
            }

            if (strlen($digits) === 5) {
                return $digits;
            }
        }

        return Str::upper($normalized);
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $resolved = preg_replace('/\s+/u', ' ', trim((string) $value));
        if ($resolved === null) {
            return null;
        }

        return $resolved === '' ? null : $resolved;
    }

    private function normalizeComparable(mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);

        return $normalized === null ? null : (string) $normalized;
    }
}
