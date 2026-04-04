<?php

namespace Tests\Feature\Web;

use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\IntegrationHealthLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CarrierSettingsService;
use App\Services\Carriers\FedexShipmentProvider;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalCarrierOperationsWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.carrier_fedex' => true,
            'services.fedex' => [
                'client_id' => 'fedex-client-id-001',
                'client_secret' => 'fedex-client-secret-001',
                'account_number' => 'FEDX3456',
                'base_url' => 'https://apis-sandbox.fedex.com',
                'oauth_url' => 'https://apis-sandbox.fedex.com/oauth/token',
            ],
            'services.dhl' => [
                'api_key' => 'dhl-internal-key-001',
                'api_secret' => 'dhl-internal-secret-001',
                'account_number' => 'DHL9012',
                'base_url' => 'https://express.api.dhl.com/mydhlapi',
            ],
        ]);

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function super_admin_can_disable_a_carrier_and_the_runtime_gate_changes(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $reason = 'Disabled from the internal carrier center while checking a runtime issue.';

        $this->assertTrue(FeatureFlag::runtimeEnabled('carrier_fedex'));

        $this->actingAs($actor, 'web')
            ->post(route('internal.carriers.toggle', 'fedex'), [
                'is_enabled' => 0,
                'reason' => $reason,
            ])
            ->assertRedirect(route('internal.carriers.show', 'fedex'))
            ->assertSessionHas('success');

        $flag = FeatureFlag::query()->where('key', 'carrier_fedex')->firstOrFail();
        $this->assertFalse((bool) $flag->is_enabled);
        $this->assertFalse(FeatureFlag::runtimeEnabled('carrier_fedex'));
        $this->assertFalse(app(FedexShipmentProvider::class)->isEnabled());

        $audit = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('action', 'carrier.integration_disabled')
            ->where('user_id', (string) $actor->id)
            ->where('entity_type', 'FeatureFlag')
            ->where('entity_id', (string) $flag->id)
            ->where('metadata->reason', $reason)
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('fedex', (string) data_get($audit?->metadata, 'carrier_code'));
        $this->assertFalse((bool) data_get($audit?->new_values, 'runtime_enabled', true));
    }

    #[Test]
    public function carrier_manager_can_enable_a_disabled_carrier_and_the_runtime_gate_changes(): void
    {
        FeatureFlag::query()->updateOrCreate(
            ['key' => 'carrier_fedex'],
            [
                'name' => 'FedEx',
                'description' => 'Enable FedEx carrier workflows from the internal carrier center.',
                'is_enabled' => false,
                'rollout_percentage' => 100,
                'target_accounts' => [],
                'target_plans' => [],
                'created_by' => 'seed',
            ]
        );

        $actor = $this->userByEmail('e2e.internal.carrier_manager@example.test');
        $reason = 'Re-enabled after validating the carrier credential footprint.';

        $this->assertFalse(FeatureFlag::runtimeEnabled('carrier_fedex'));

        $this->actingAs($actor, 'web')
            ->post(route('internal.carriers.toggle', 'fedex'), [
                'is_enabled' => 1,
                'reason' => $reason,
            ])
            ->assertRedirect(route('internal.carriers.show', 'fedex'))
            ->assertSessionHas('success');

        $flag = FeatureFlag::query()->where('key', 'carrier_fedex')->firstOrFail();
        $this->assertTrue((bool) $flag->is_enabled);
        $this->assertTrue(FeatureFlag::runtimeEnabled('carrier_fedex'));
        $this->assertTrue(app(FedexShipmentProvider::class)->isEnabled());

        $audit = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('action', 'carrier.integration_enabled')
            ->where('user_id', (string) $actor->id)
            ->where('entity_type', 'FeatureFlag')
            ->where('entity_id', (string) $flag->id)
            ->where('metadata->reason', $reason)
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('fedex', (string) data_get($audit?->metadata, 'carrier_code'));
        $this->assertTrue((bool) data_get($audit?->new_values, 'runtime_enabled', false));
    }

    #[Test]
    public function carrier_manager_can_run_a_safe_connection_test_and_it_updates_the_detail_surface(): void
    {
        $actor = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->actingAs($actor, 'web')
            ->post(route('internal.carriers.test', 'fedex'))
            ->assertRedirect(route('internal.carriers.show', 'fedex'))
            ->assertSessionHas('success');

        $log = IntegrationHealthLog::query()
            ->where('service', 'carrier:fedex')
            ->latest('checked_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(IntegrationHealthLog::STATUS_HEALTHY, (string) $log?->status);
        $this->assertSame(1, (int) $log?->total_requests);
        $this->assertSame(0, (int) $log?->failed_requests);

        $audit = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('action', 'admin.carrier_tested')
            ->where('user_id', (string) $actor->id)
            ->where('entity_type', 'IntegrationHealthLog')
            ->where('entity_id', (string) $log?->id)
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('fedex', (string) data_get($audit?->metadata, 'carrier_code'));
        $this->assertSame(0, (int) data_get($audit?->metadata, 'missing_field_count', 99));

        $this->actingAs($actor, 'web')
            ->get(route('internal.carriers.show', 'fedex'))
            ->assertOk()
            ->assertSee('data-testid="internal-carrier-actions-card"', false)
            ->assertSee('data-testid="internal-carrier-credentials-card"', false)
            ->assertSeeText('Last health check: Healthy')
            ->assertSeeText('1 total')
            ->assertDontSeeText('fedex-client-id-001')
            ->assertDontSeeText('fedex-client-secret-001');
    }

    #[Test]
    public function super_admin_can_update_carrier_credentials_and_the_values_stay_masked(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $reason = 'Updated the stored FedEx credentials after validating the platform-side integration record.';

        $this->actingAs($actor, 'web')
            ->post(route('internal.carriers.credentials.update', 'fedex'), [
                'client_id' => 'fedex-client-id-rotated-01',
                'client_secret' => 'fedex-client-secret-rotated-01',
                'account_number' => 'FEDX7777',
                'reason' => $reason,
            ])
            ->assertRedirect(route('internal.carriers.show', 'fedex'))
            ->assertSessionHas('success');

        $settings = SystemSetting::query()
            ->where('group', 'carrier_integrations.fedex')
            ->whereIn('key', ['client_id', 'client_secret', 'account_number'])
            ->get()
            ->keyBy('key');

        $this->assertSame('encrypted', (string) $settings['client_id']->type);
        $this->assertSame('encrypted', (string) $settings['client_secret']->type);
        $this->assertSame('encrypted', (string) $settings['account_number']->type);
        $this->assertNotSame('fedex-client-id-rotated-01', (string) $settings['client_id']->value);
        $this->assertNotSame('fedex-client-secret-rotated-01', (string) $settings['client_secret']->value);
        $this->assertNotSame('FEDX7777', (string) $settings['account_number']->value);

        $runtime = app(CarrierSettingsService::class)->runtimeConfig('fedex');
        $this->assertSame('fedex-client-id-rotated-01', (string) ($runtime['client_id'] ?? null));
        $this->assertSame('fedex-client-secret-rotated-01', (string) ($runtime['client_secret'] ?? null));
        $this->assertSame('FEDX7777', (string) ($runtime['account_number'] ?? null));

        $audit = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('action', 'carrier.credentials_updated')
            ->where('user_id', (string) $actor->id)
            ->where('entity_type', 'SystemSetting')
            ->where('entity_id', 'fedex')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('fedex', (string) data_get($audit?->metadata, 'carrier_code'));
        $this->assertSame($reason, (string) data_get($audit?->metadata, 'reason'));
        $this->assertSame(
            ['client_id', 'client_secret', 'account_number'],
            data_get($audit?->metadata, 'updated_fields')
        );
        $this->assertStringStartsWith('fe', (string) data_get($audit?->new_values, 'client_id'));
        $this->assertStringEndsWith('01', (string) data_get($audit?->new_values, 'client_id'));
        $this->assertSame('Configured', (string) data_get($audit?->new_values, 'client_secret'));
        $this->assertSame('Configured ending 7777', (string) data_get($audit?->new_values, 'account_number'));
        $this->assertNotSame('fedex-client-secret-rotated-01', (string) data_get($audit?->new_values, 'client_secret'));

        $healthLog = IntegrationHealthLog::query()
            ->where('service', 'carrier:fedex')
            ->latest('checked_at')
            ->first();

        $this->assertNotNull($healthLog);
        $this->assertSame(IntegrationHealthLog::STATUS_HEALTHY, (string) $healthLog?->status);
        $this->assertSame('carrier_credentials_update', (string) data_get($healthLog?->metadata, 'check_source'));

        $this->actingAs($actor, 'web')
            ->get(route('internal.carriers.show', 'fedex'))
            ->assertOk()
            ->assertSeeText('Configured ending 7777')
            ->assertDontSeeText('fedex-client-id-rotated-01')
            ->assertDontSeeText('fedex-client-secret-rotated-01')
            ->assertDontSeeText('FEDX7777');
    }

    #[Test]
    public function carrier_manager_can_rotate_the_active_api_credentials_and_trigger_a_safe_retest(): void
    {
        $actor = $this->userByEmail('e2e.internal.carrier_manager@example.test');
        $reason = 'Rotated the stored API credential pair after the carrier requested a key refresh.';

        $this->actingAs($actor, 'web')
            ->post(route('internal.carriers.credentials.rotate', 'fedex'), [
                'client_id' => 'fedex-client-id-rotated-02',
                'client_secret' => 'fedex-client-secret-rotated-02',
                'reason' => $reason,
            ])
            ->assertRedirect(route('internal.carriers.show', 'fedex'))
            ->assertSessionHas('success');

        $runtime = app(CarrierSettingsService::class)->runtimeConfig('fedex');
        $this->assertSame('fedex-client-id-rotated-02', (string) ($runtime['client_id'] ?? null));
        $this->assertSame('fedex-client-secret-rotated-02', (string) ($runtime['client_secret'] ?? null));
        $this->assertSame('FEDX3456', (string) ($runtime['account_number'] ?? null));

        $audit = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('action', 'carrier.credentials_rotated')
            ->where('user_id', (string) $actor->id)
            ->where('entity_type', 'SystemSetting')
            ->where('entity_id', 'fedex')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(
            ['client_id', 'client_secret'],
            data_get($audit?->metadata, 'rotated_fields')
        );
        $healthLog = IntegrationHealthLog::query()
            ->where('service', 'carrier:fedex')
            ->latest('checked_at')
            ->first();

        $this->assertNotNull($healthLog);
        $this->assertSame('carrier_credentials_rotation', (string) data_get($healthLog?->metadata, 'check_source'));
    }

    #[Test]
    public function support_and_ops_readonly_cannot_mutate_and_do_not_see_carrier_action_controls(): void
    {
        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user, 'web')
                ->get(route('internal.carriers.show', 'fedex'))
                ->assertOk()
                ->assertDontSee('data-testid="internal-carrier-actions-card"', false)
                ->assertDontSee('data-testid="internal-carrier-toggle-form"', false)
                ->assertDontSee('data-testid="internal-carrier-test-form"', false)
                ->assertDontSee('data-testid="internal-carrier-credentials-update-form"', false)
                ->assertDontSee('data-testid="internal-carrier-credentials-rotate-form"', false);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.carriers.toggle', 'fedex'), [
                    'is_enabled' => 0,
                    'reason' => 'Not allowed',
                ])
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.carriers.test', 'fedex'))
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.carriers.credentials.update', 'fedex'), [
                    'client_id' => 'not-allowed',
                    'client_secret' => 'still-not-allowed',
                    'reason' => 'Not allowed',
                ])
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.carriers.credentials.rotate', 'fedex'), [
                    'client_id' => 'not-allowed',
                    'client_secret' => 'still-not-allowed',
                    'reason' => 'Not allowed',
                ])
            );
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_carrier_action_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->post(route('internal.carriers.toggle', 'fedex'), [
                'is_enabled' => 0,
                'reason' => 'External attempt',
            ])
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->post(route('internal.carriers.test', 'fedex'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->post(route('internal.carriers.credentials.update', 'fedex'), [
                'client_id' => 'external-attempt',
                'client_secret' => 'external-secret',
                'reason' => 'External attempt',
            ])
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->post(route('internal.carriers.credentials.rotate', 'fedex'), [
                'client_id' => 'external-attempt',
                'client_secret' => 'external-secret',
                'reason' => 'External attempt',
            ])
        );
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
