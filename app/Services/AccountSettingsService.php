<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\User;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * AccountSettingsService — FR-IAM-008: Account Settings Management
 *
 * Manages: language, currency, timezone, country, contact info, address,
 * date/weight/dimension formats, and extended (JSONB) settings.
 *
 * Every change is audit-logged with old → new values.
 */
class AccountSettingsService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get all account settings (combined: dedicated + extended).
     */
    public function getSettings(string $accountId): array
    {
        $account = Account::findOrFail($accountId);
        return $account->allSettings();
    }

    /**
     * Update account settings.
     * Only Owner or account:manage permission.
     *
     * @param  string $accountId
     * @param  array  $data      Validated settings data
     * @param  User   $performer Who is making the change
     * @return array  Updated settings
     */
    public function updateSettings(string $accountId, array $data, User $performer): array
    {
        $this->assertCanManageSettings($performer);

        $account = Account::findOrFail($accountId);

        return DB::transaction(function () use ($account, $data, $performer) {
            $oldSettings = $account->allSettings();

            // ── Update dedicated columns ──────────────────────────
            $directFields = [
                'name', 'language', 'currency', 'timezone', 'country',
                'contact_phone', 'contact_email',
                'address_line_1', 'address_line_2', 'city', 'postal_code',
                'date_format', 'weight_unit', 'dimension_unit',
            ];

            $changes = [];
            foreach ($directFields as $field) {
                if (array_key_exists($field, $data) && $account->{$field} !== $data[$field]) {
                    $changes[$field] = [
                        'old' => $account->{$field},
                        'new' => $data[$field],
                    ];
                    $account->{$field} = $data[$field];
                }
            }

            // ── Update extended (JSONB) settings ──────────────────
            if (isset($data['extended']) && is_array($data['extended'])) {
                $currentExtended = $account->settings ?? [];
                $extendedChanges = [];

                foreach ($data['extended'] as $key => $value) {
                    $oldVal = $currentExtended[$key] ?? null;
                    if ($oldVal !== $value) {
                        $extendedChanges[$key] = ['old' => $oldVal, 'new' => $value];
                    }
                }

                if (!empty($extendedChanges)) {
                    $account->settings = array_merge($currentExtended, $data['extended']);
                    $changes['extended'] = $extendedChanges;
                }
            }

            if (empty($changes)) {
                return $account->allSettings(); // Nothing changed
            }

            $account->save();

            // ── Audit log ─────────────────────────────────────────
            $oldValues = [];
            $newValues = [];
            foreach ($changes as $field => $change) {
                if ($field === 'extended') {
                    foreach ($change as $k => $v) {
                        $oldValues["ext.{$k}"] = $v['old'];
                        $newValues["ext.{$k}"] = $v['new'];
                    }
                } else {
                    $oldValues[$field] = $change['old'];
                    $newValues[$field] = $change['new'];
                }
            }

            $this->auditService->info(
                $account->id, $performer->id,
                'account.settings_updated', AuditLog::CATEGORY_SETTINGS,
                'Account', $account->id,
                $oldValues, $newValues,
                ['fields_changed' => array_keys($changes)]
            );

            return $account->fresh()->allSettings();
        });
    }

    /**
     * Update a single setting by key.
     */
    public function updateSingleSetting(
        string $accountId,
        string $key,
        mixed  $value,
        User   $performer
    ): array {
        return $this->updateSettings($accountId, [$key => $value], $performer);
    }

    /**
     * Reset settings to defaults.
     */
    public function resetToDefaults(string $accountId, User $performer): array
    {
        $this->assertCanManageSettings($performer);

        $defaults = [
            'language'       => 'ar',
            'currency'       => 'SAR',
            'timezone'       => 'Asia/Riyadh',
            'country'        => 'SA',
            'date_format'    => 'Y-m-d',
            'weight_unit'    => 'kg',
            'dimension_unit' => 'cm',
        ];

        return $this->updateSettings($accountId, $defaults, $performer);
    }

    /**
     * Get supported options for frontend dropdowns.
     */
    public function getSupportedOptions(): array
    {
        return [
            'languages'       => $this->languageOptions(),
            'currencies'      => $this->currencyOptions(),
            'timezones'       => $this->timezoneOptions(),
            'countries'       => $this->countryOptions(),
            'date_formats'    => Account::SUPPORTED_DATE_FORMATS,
            'weight_units'    => Account::SUPPORTED_WEIGHT_UNITS,
            'dimension_units' => Account::SUPPORTED_DIMENSION_UNITS,
        ];
    }

    // ─── Options with labels ─────────────────────────────────────

    private function languageOptions(): array
    {
        return [
            ['code' => 'ar', 'name' => 'العربية', 'name_en' => 'Arabic'],
            ['code' => 'en', 'name' => 'English', 'name_en' => 'English'],
            ['code' => 'fr', 'name' => 'Français', 'name_en' => 'French'],
            ['code' => 'tr', 'name' => 'Türkçe', 'name_en' => 'Turkish'],
            ['code' => 'ur', 'name' => 'اردو', 'name_en' => 'Urdu'],
        ];
    }

    private function currencyOptions(): array
    {
        return [
            ['code' => 'SAR', 'name' => 'ريال سعودي', 'symbol' => '﷼'],
            ['code' => 'AED', 'name' => 'درهم إماراتي', 'symbol' => 'د.إ'],
            ['code' => 'USD', 'name' => 'دولار أمريكي', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'يورو', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'جنيه إسترليني', 'symbol' => '£'],
            ['code' => 'EGP', 'name' => 'جنيه مصري', 'symbol' => 'ج.م'],
            ['code' => 'KWD', 'name' => 'دينار كويتي', 'symbol' => 'د.ك'],
            ['code' => 'BHD', 'name' => 'دينار بحريني', 'symbol' => 'د.ب'],
            ['code' => 'OMR', 'name' => 'ريال عماني', 'symbol' => 'ر.ع'],
            ['code' => 'QAR', 'name' => 'ريال قطري', 'symbol' => 'ر.ق'],
            ['code' => 'JOD', 'name' => 'دينار أردني', 'symbol' => 'د.أ'],
            ['code' => 'TRY', 'name' => 'ليرة تركية', 'symbol' => '₺'],
        ];
    }

    private function timezoneOptions(): array
    {
        return array_map(fn ($tz) => [
            'code'   => $tz,
            'offset' => now($tz)->format('P'),
            'name'   => str_replace(['/', '_'], [' — ', ' '], $tz),
        ], Account::SUPPORTED_TIMEZONES);
    }

    private function countryOptions(): array
    {
        $names = [
            'SA' => 'المملكة العربية السعودية',
            'AE' => 'الإمارات العربية المتحدة',
            'KW' => 'الكويت',
            'BH' => 'البحرين',
            'QA' => 'قطر',
            'OM' => 'سلطنة عمان',
            'JO' => 'الأردن',
            'EG' => 'مصر',
            'TR' => 'تركيا',
            'US' => 'الولايات المتحدة',
            'GB' => 'المملكة المتحدة',
        ];

        return array_map(fn ($code) => [
            'code' => $code,
            'name' => $names[$code] ?? $code,
        ], Account::SUPPORTED_COUNTRIES);
    }

    // ─── Authorization ───────────────────────────────────────────

    private function assertCanManageSettings(User $user): void
    {
        if (!$user->hasPermission('account.manage')) {
            $this->auditService->warning(
                $user->account_id, $user->id,
                'account.settings_access_denied', AuditLog::CATEGORY_SETTINGS,
                null, null, null, null,
                ['attempted_action' => 'manage_settings']
            );
            throw BusinessException::permissionDenied();
        }
    }
}
