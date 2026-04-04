<?php

namespace Tests\Feature\Web;

use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalFeatureFlagOperationsWebTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlag $fixtureFlag;
    private string $fixtureAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->fixtureFlag = FeatureFlag::query()
            ->where('key', 'internal_ops_visibility_fixture')
            ->firstOrFail();

        $this->fixtureAccountId = (string) data_get($this->fixtureFlag->target_accounts, '0');
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_feature_flag_index_and_detail_read_only(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.feature-flags.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-feature-flags-table"', false)
                ->assertSeeText('I8D Internal Ops Fixture')
                ->assertSeeText('Carrier DHL')
                ->assertSeeText('50%')
                ->assertSeeText('1 target account(s)')
                ->assertDontSeeText($this->fixtureAccountId)
                ->assertDontSeeText('enterprise');

            $this->assertHasNavigationLink($index, 'internal.feature-flags.index');

            $detail = $this->actingAs($user, 'web')
                ->get(route('internal.feature-flags.show', (string) $this->fixtureFlag->id))
                ->assertOk()
                ->assertSee('data-testid="internal-feature-flag-summary-card"', false)
                ->assertSee('data-testid="internal-feature-flag-runtime-card"', false)
                ->assertSee('data-testid="internal-feature-flag-audit-card"', false)
                ->assertSeeText('I8D Internal Ops Fixture')
                ->assertSeeText('internal_ops_visibility_fixture')
                ->assertSeeText('50% deterministic rollout')
                ->assertSeeText('1 target account(s)')
                ->assertDontSeeText($this->fixtureAccountId)
                ->assertDontSeeText('enterprise');

            if ($email === 'e2e.internal.super_admin@example.test') {
                $detail->assertSee('data-testid="internal-feature-flag-toggle-form"', false);
            } else {
                $detail->assertDontSee('data-testid="internal-feature-flag-toggle-form"', false);
            }
        }
    }

    #[Test]
    public function super_admin_can_toggle_feature_flags_with_reason_and_audit(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $reason = 'Temporarily disabled while validating the internal feature-flag control plane.';

        $this->actingAs($actor, 'web')
            ->post(route('internal.feature-flags.toggle', (string) $this->fixtureFlag->id), [
                'is_enabled' => 0,
                'reason' => $reason,
            ])
            ->assertRedirect(route('internal.feature-flags.show', (string) $this->fixtureFlag->id))
            ->assertSessionHas('success');

        $this->fixtureFlag->refresh();
        $this->assertFalse((bool) $this->fixtureFlag->is_enabled);

        $audit = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('action', 'admin.feature_flag_toggled')
            ->where('user_id', (string) $actor->id)
            ->where('entity_type', 'FeatureFlag')
            ->where('entity_id', (string) $this->fixtureFlag->id)
            ->where('metadata->reason', $reason)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('internal_ops_visibility_fixture', data_get($audit?->metadata, 'flag_key'));
        $this->assertFalse((bool) data_get($audit?->new_values, 'is_enabled', true));
    }

    #[Test]
    public function support_and_ops_readonly_cannot_mutate_flags_and_carrier_manager_is_denied(): void
    {
        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.feature-flags.toggle', (string) $this->fixtureFlag->id), [
                    'is_enabled' => 0,
                    'reason' => 'Not allowed',
                ])
            );
        }

        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.feature-flags.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.feature-flags.show', (string) $this->fixtureFlag->id))
        );
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_feature_flag_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.feature-flags.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.feature-flags.show', (string) $this->fixtureFlag->id))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->post(route('internal.feature-flags.toggle', (string) $this->fixtureFlag->id), [
                'is_enabled' => 0,
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

    private function assertHasNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertSee('href="' . route($routeName) . '"', false);
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
