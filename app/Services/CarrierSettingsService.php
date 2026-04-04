<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CarrierSettingsService
{
    /**
     * @var array<string, array{
     *   label: string,
     *   flag_key: string,
     *   config_path: string,
     *   required_fields: array<int, string>,
     *   fields: array<string, array{
     *     label: string,
     *     input_type: string,
     *     mask: string,
     *     rotatable: bool
     *   }>
     * }>
     */
    private const CARRIERS = [
        'aramex' => [
            'label' => 'Aramex',
            'flag_key' => 'carrier_aramex',
            'config_path' => 'services.aramex',
            'required_fields' => ['username', 'password', 'account_number', 'account_pin'],
            'fields' => [
                'username' => ['label' => 'Username', 'input_type' => 'text', 'mask' => 'credential', 'rotatable' => false],
                'password' => ['label' => 'Password', 'input_type' => 'password', 'mask' => 'secret', 'rotatable' => false],
                'account_number' => ['label' => 'Account number', 'input_type' => 'text', 'mask' => 'account', 'rotatable' => false],
                'account_pin' => ['label' => 'Account PIN', 'input_type' => 'password', 'mask' => 'secret', 'rotatable' => false],
            ],
        ],
        'dhl' => [
            'label' => 'DHL Express',
            'flag_key' => 'carrier_dhl',
            'config_path' => 'services.dhl',
            'required_fields' => ['api_key', 'api_secret', 'account_number'],
            'fields' => [
                'api_key' => ['label' => 'API key', 'input_type' => 'password', 'mask' => 'credential', 'rotatable' => true],
                'api_secret' => ['label' => 'API secret', 'input_type' => 'password', 'mask' => 'secret', 'rotatable' => true],
                'account_number' => ['label' => 'Account number', 'input_type' => 'text', 'mask' => 'account', 'rotatable' => false],
            ],
        ],
        'fedex' => [
            'label' => 'FedEx',
            'flag_key' => 'carrier_fedex',
            'config_path' => 'services.fedex',
            'required_fields' => ['client_id', 'client_secret', 'account_number'],
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'input_type' => 'password', 'mask' => 'credential', 'rotatable' => true],
                'client_secret' => ['label' => 'Client secret', 'input_type' => 'password', 'mask' => 'secret', 'rotatable' => true],
                'account_number' => ['label' => 'Account number', 'input_type' => 'text', 'mask' => 'account', 'rotatable' => false],
            ],
        ],
        'smsa' => [
            'label' => 'SMSA',
            'flag_key' => 'carrier_smsa',
            'config_path' => 'services.smsa',
            'required_fields' => ['api_key', 'passkey'],
            'fields' => [
                'api_key' => ['label' => 'API key', 'input_type' => 'password', 'mask' => 'credential', 'rotatable' => true],
                'passkey' => ['label' => 'Passkey', 'input_type' => 'password', 'mask' => 'secret', 'rotatable' => true],
            ],
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return self::CARRIERS;
    }

    /**
     * @return array{
     *   label: string,
     *   flag_key: string,
     *   config_path: string,
     *   required_fields: array<int, string>,
     *   fields: array<string, array{
     *     label: string,
     *     input_type: string,
     *     mask: string,
     *     rotatable: bool
     *   }>
     * }
     */
    public function carrierDefinition(string $carrierCode): array
    {
        $normalized = Str::lower(trim($carrierCode));
        $definition = self::CARRIERS[$normalized] ?? null;

        if (! is_array($definition)) {
            throw new BusinessException(
                'This carrier is not available in the internal carrier center.',
                'ERR_CARRIER_NOT_FOUND',
                404
            );
        }

        return $definition;
    }

    /**
     * @return array<int, string>
     */
    public function editableFieldNames(string $carrierCode): array
    {
        return array_keys($this->carrierDefinition($carrierCode)['fields']);
    }

    /**
     * @return array<int, string>
     */
    public function rotationFieldNames(string $carrierCode): array
    {
        return collect($this->carrierDefinition($carrierCode)['fields'])
            ->filter(static fn (array $field): bool => (bool) ($field['rotatable'] ?? false))
            ->keys()
            ->values()
            ->all();
    }

    public function supportsRotation(string $carrierCode): bool
    {
        return $this->rotationFieldNames($carrierCode) !== [];
    }

    /**
     * @return array<int, array{name: string, label: string, input_type: string, current_value: string}>
     */
    public function editableFieldsForView(string $carrierCode): array
    {
        $definition = $this->carrierDefinition($carrierCode);
        $runtimeConfig = $this->runtimeConfig($carrierCode);

        return collect($definition['fields'])
            ->map(function (array $field, string $name) use ($runtimeConfig): array {
                return [
                    'name' => $name,
                    'label' => $field['label'],
                    'input_type' => $field['input_type'],
                    'current_value' => $this->maskValue(
                        $field['mask'],
                        $runtimeConfig[$name] ?? null
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, label: string, input_type: string, current_value: string}>
     */
    public function rotationFieldsForView(string $carrierCode): array
    {
        $allowed = array_flip($this->rotationFieldNames($carrierCode));

        return collect($this->editableFieldsForView($carrierCode))
            ->filter(static fn (array $field): bool => isset($allowed[$field['name']]))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function runtimeConfig(string $carrierCode): array
    {
        $definition = $this->carrierDefinition($carrierCode);
        $config = $this->safeArray(config($definition['config_path'], []));

        foreach ($this->storedSettings($carrierCode) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $config[$key] = $value;
        }

        return $config;
    }

    public function applyRuntimeConfig(?string $carrierCode = null): void
    {
        if ($carrierCode !== null) {
            $definition = $this->carrierDefinition($carrierCode);
            config([$definition['config_path'] => $this->runtimeConfig($carrierCode)]);

            return;
        }

        foreach (array_keys(self::CARRIERS) as $code) {
            $definition = self::CARRIERS[$code];
            config([$definition['config_path'] => $this->runtimeConfig($code)]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string>|null $allowedFields
     * @return array<int, string>
     */
    public function storeCredentialUpdates(
        string $carrierCode,
        array $payload,
        ?string $updatedBy = null,
        ?array $allowedFields = null
    ): array {
        $definition = $this->carrierDefinition($carrierCode);
        $allowed = $allowedFields !== null
            ? array_values(array_intersect($this->editableFieldNames($carrierCode), $allowedFields))
            : $this->editableFieldNames($carrierCode);

        $updatedFields = [];

        foreach ($allowed as $fieldName) {
            if (! array_key_exists($fieldName, $definition['fields'])) {
                continue;
            }

            $resolved = trim((string) ($payload[$fieldName] ?? ''));
            if ($resolved === '') {
                continue;
            }

            SystemSetting::setValue(
                $this->settingsGroup($carrierCode),
                $fieldName,
                $resolved,
                'encrypted',
                $updatedBy,
            );

            $updatedFields[] = $fieldName;
        }

        if ($updatedFields === []) {
            throw new BusinessException(
                'Enter at least one new credential value before saving carrier settings.',
                'ERR_CARRIER_CREDENTIALS_NO_CHANGE',
                422
            );
        }

        $this->applyRuntimeConfig($carrierCode);

        return $updatedFields;
    }

    /**
     * @return array<string, string|null>
     */
    public function credentialStateForAudit(string $carrierCode): array
    {
        $definition = $this->carrierDefinition($carrierCode);
        $runtimeConfig = $this->runtimeConfig($carrierCode);
        $state = [];

        foreach ($definition['fields'] as $fieldName => $field) {
            $state[$fieldName] = $this->maskValue(
                $field['mask'],
                $runtimeConfig[$fieldName] ?? null
            );
        }

        return $state;
    }

    /**
     * @return array<string, string>
     */
    private function storedSettings(string $carrierCode): array
    {
        if (! $this->canReadSettingsStore()) {
            return [];
        }

        return SystemSetting::query()
            ->where('group', $this->settingsGroup($carrierCode))
            ->get()
            ->mapWithKeys(static function (SystemSetting $setting): array {
                return [(string) $setting->key => trim((string) $setting->getTypedValue())];
            })
            ->all();
    }

    private function settingsGroup(string $carrierCode): string
    {
        return 'carrier_integrations.' . Str::lower(trim($carrierCode));
    }

    private function canReadSettingsStore(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param mixed $value
     */
    private function maskValue(string $mask, mixed $value): string
    {
        $resolved = trim((string) $value);

        if ($resolved === '') {
            return 'Not configured';
        }

        return match ($mask) {
            'account' => $this->maskAccountNumber($resolved),
            'credential' => $this->maskCredential($resolved),
            default => 'Configured',
        };
    }

    private function maskAccountNumber(string $value): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', $value) ?: $value;
        $lastFour = substr($normalized, -4);

        if ($lastFour === false || $lastFour === '') {
            return 'Configured';
        }

        return 'Configured ending ' . $lastFour;
    }

    private function maskCredential(string $value): string
    {
        if (str_contains($value, '@')) {
            return (string) DataMaskingService::maskEmail($value);
        }

        $length = mb_strlen($value);

        if ($length <= 4) {
            return str_repeat('*', max(4, $length));
        }

        return mb_substr($value, 0, 2)
            . str_repeat('*', max(4, $length - 4))
            . mb_substr($value, -2);
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function safeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
