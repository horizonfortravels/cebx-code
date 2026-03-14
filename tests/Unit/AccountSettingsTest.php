<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Services\AccountSettingsService;
use App\Services\AuditService;
use App\Exceptions\BusinessException;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * FR-IAM-008: Account Settings — Unit Tests (20 tests)
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    protected AccountSettingsService $service;
    protected Account $account;
    protected User $owner;
    protected User $admin;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        AuditService::resetRequestId();
        $this->service = app(AccountSettingsService::class);

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => true,
        ]);

        $adminRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['account.manage', 'account.view'],
            'account_admin'
        );
        $this->admin = $this->createUserWithRole((string) $this->account->id, (string) $adminRole->id, [
            'is_owner' => false,
        ]);

        $this->member = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => false,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // Get Settings
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_all_default_settings()
    {
        $settings = $this->service->getSettings($this->account->id);

        $this->assertEquals('ar', $settings['language']);
        $this->assertEquals('SAR', $settings['currency']);
        $this->assertEquals('Asia/Riyadh', $settings['timezone']);
        $this->assertEquals('SA', $settings['country']);
        $this->assertEquals('Y-m-d', $settings['date_format']);
        $this->assertEquals('kg', $settings['weight_unit']);
        $this->assertEquals('cm', $settings['dimension_unit']);
        $this->assertArrayHasKey('address', $settings);
        $this->assertArrayHasKey('extended', $settings);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_address_as_nested_object()
    {
        $this->account->update([
            'address_line_1' => 'شارع الملك فهد',
            'city'           => 'الرياض',
            'postal_code'    => '12345',
        ]);

        $settings = $this->service->getSettings($this->account->id);

        $this->assertEquals('شارع الملك فهد', $settings['address']['line_1']);
        $this->assertEquals('الرياض', $settings['address']['city']);
        $this->assertEquals('12345', $settings['address']['postal_code']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Update Settings
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_language()
    {
        $result = $this->service->updateSettings(
            $this->account->id,
            ['language' => 'en'],
            $this->owner
        );

        $this->assertEquals('en', $result['language']);
        $this->assertEquals('en', $this->account->fresh()->language);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_currency()
    {
        $result = $this->service->updateSettings(
            $this->account->id,
            ['currency' => 'USD'],
            $this->owner
        );

        $this->assertEquals('USD', $result['currency']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_timezone()
    {
        $result = $this->service->updateSettings(
            $this->account->id,
            ['timezone' => 'Europe/London'],
            $this->owner
        );

        $this->assertEquals('Europe/London', $result['timezone']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_multiple_settings_at_once()
    {
        $result = $this->service->updateSettings(
            $this->account->id,
            [
                'language'    => 'en',
                'currency'    => 'USD',
                'timezone'    => 'UTC',
                'weight_unit' => 'lb',
            ],
            $this->owner
        );

        $this->assertEquals('en', $result['language']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals('UTC', $result['timezone']);
        $this->assertEquals('lb', $result['weight_unit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_contact_info()
    {
        $result = $this->service->updateSettings(
            $this->account->id,
            [
                'contact_phone' => '+966501234567',
                'contact_email' => 'info@company.sa',
            ],
            $this->owner
        );

        $this->assertEquals('+966501234567', $result['contact_phone']);
        $this->assertEquals('info@company.sa', $result['contact_email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_address()
    {
        $result = $this->service->updateSettings(
            $this->account->id,
            [
                'address_line_1' => 'شارع الملك فهد',
                'city'           => 'الرياض',
                'postal_code'    => '12345',
            ],
            $this->owner
        );

        $this->assertEquals('شارع الملك فهد', $result['address']['line_1']);
        $this->assertEquals('الرياض', $result['address']['city']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_account_name()
    {
        $result = $this->service->updateSettings(
            $this->account->id,
            ['name' => 'شركة التقنية الجديدة'],
            $this->owner
        );

        $this->assertEquals('شركة التقنية الجديدة', $this->account->fresh()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_with_permission_can_update_settings()
    {
        $result = $this->service->updateSettings(
            $this->account->id,
            ['language' => 'en'],
            $this->admin
        );

        $this->assertEquals('en', $result['language']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_without_permission_cannot_update()
    {
        $this->expectException(BusinessException::class);
        $this->service->updateSettings(
            $this->account->id,
            ['language' => 'en'],
            $this->member
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function no_change_when_same_values_submitted()
    {
        $before = AuditLog::withoutGlobalScopes()->count();

        $this->service->updateSettings(
            $this->account->id,
            ['language' => 'ar'], // Same as default
            $this->owner
        );

        // No audit log created because nothing changed
        $this->assertEquals($before, AuditLog::withoutGlobalScopes()->count());
    }

    // ═══════════════════════════════════════════════════════════════
    // Extended (JSONB) Settings
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_set_extended_settings()
    {
        $result = $this->service->updateSettings(
            $this->account->id,
            ['extended' => ['notification_email' => true, 'theme' => 'dark']],
            $this->owner
        );

        $this->assertTrue($result['extended']['notification_email']);
        $this->assertEquals('dark', $result['extended']['theme']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function extended_settings_merge_not_replace()
    {
        $this->account->update(['settings' => ['existing_key' => 'value']]);

        $result = $this->service->updateSettings(
            $this->account->id,
            ['extended' => ['new_key' => 'new_value']],
            $this->owner
        );

        $this->assertEquals('value', $result['extended']['existing_key']);
        $this->assertEquals('new_value', $result['extended']['new_key']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Audit Logging
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_creates_audit_log_with_old_new_values()
    {
        $this->service->updateSettings(
            $this->account->id,
            ['language' => 'en', 'currency' => 'USD'],
            $this->owner
        );

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'account.settings_updated')
            ->where('account_id', $this->account->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('ar', $log->old_values['language']);
        $this->assertEquals('en', $log->new_values['language']);
        $this->assertEquals('SAR', $log->old_values['currency']);
        $this->assertEquals('USD', $log->new_values['currency']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function permission_denied_is_audit_logged()
    {
        try {
            $this->service->updateSettings(
                $this->account->id,
                ['language' => 'en'],
                $this->member
            );
        } catch (BusinessException $e) {
            // Expected
        }

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'account.settings_access_denied')
            ->first();

        $this->assertNotNull($log);
    }

    // ═══════════════════════════════════════════════════════════════
    // Reset to Defaults
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_reset_to_defaults()
    {
        // Change settings first
        $this->service->updateSettings(
            $this->account->id,
            ['language' => 'en', 'currency' => 'USD', 'timezone' => 'UTC'],
            $this->owner
        );

        // Reset
        $result = $this->service->resetToDefaults($this->account->id, $this->owner);

        $this->assertEquals('ar', $result['language']);
        $this->assertEquals('SAR', $result['currency']);
        $this->assertEquals('Asia/Riyadh', $result['timezone']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_cannot_reset_settings()
    {
        $this->expectException(BusinessException::class);
        $this->service->resetToDefaults($this->account->id, $this->member);
    }

    // ═══════════════════════════════════════════════════════════════
    // Supported Options
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_supported_options()
    {
        $options = $this->service->getSupportedOptions();

        $this->assertArrayHasKey('languages', $options);
        $this->assertArrayHasKey('currencies', $options);
        $this->assertArrayHasKey('timezones', $options);
        $this->assertArrayHasKey('countries', $options);
        $this->assertArrayHasKey('date_formats', $options);
        $this->assertArrayHasKey('weight_units', $options);
        $this->assertArrayHasKey('dimension_units', $options);

        // Check structure of language options
        $langCodes = array_column($options['languages'], 'code');
        $this->assertContains('ar', $langCodes);
        $this->assertContains('en', $langCodes);

        // Check structure of currency options
        $currCodes = array_column($options['currencies'], 'code');
        $this->assertContains('SAR', $currCodes);
        $this->assertContains('USD', $currCodes);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function account_model_has_settings_constants()
    {
        $this->assertContains('ar', Account::SUPPORTED_LANGUAGES);
        $this->assertContains('SAR', Account::SUPPORTED_CURRENCIES);
        $this->assertContains('Asia/Riyadh', Account::SUPPORTED_TIMEZONES);
        $this->assertContains('SA', Account::SUPPORTED_COUNTRIES);
        $this->assertContains('kg', Account::SUPPORTED_WEIGHT_UNITS);
    }
}
