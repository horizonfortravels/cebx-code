<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalApiKeyOperationsWebTest extends TestCase
{
    use RefreshDatabase;

    private ApiKey $activeKey;
    private ApiKey $revokedKey;
    private Account $accountC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->activeKey = ApiKey::query()
            ->withoutGlobalScopes()
            ->where('name', 'I8C Active Operations Key')
            ->firstOrFail();

        $this->revokedKey = ApiKey::query()
            ->withoutGlobalScopes()
            ->where('name', 'I8C Revoked Legacy Key')
            ->firstOrFail();

        $this->accountC = Account::query()
            ->withoutGlobalScopes()
            ->where('name', 'E2E Account C')
            ->firstOrFail();
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_api_key_index_and_detail_with_masked_values(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.api-keys.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-api-keys-table"', false)
                ->assertSeeText('I8C Active Operations Key')
                ->assertSeeText('I8C Revoked Legacy Key')
                ->assertDontSeeText('sgw_i8c_seed_active_001')
                ->assertDontSeeText('sgw_i8c_seed_revoked_001')
                ->assertDontSeeText('key_hash');

            $this->assertHasNavigationLink($index, 'internal.api-keys.index');

            if ($email === 'e2e.internal.super_admin@example.test') {
                $index->assertSee('data-testid="internal-api-key-create-form"', false);
            } else {
                $index->assertDontSee('data-testid="internal-api-key-create-form"', false);
            }

            $detail = $this->actingAs($user, 'web')
                ->get(route('internal.api-keys.show', (string) $this->activeKey->id))
                ->assertOk()
                ->assertSee('data-testid="internal-api-key-summary-card"', false)
                ->assertSee('data-testid="internal-api-key-scopes-card"', false)
                ->assertSee('data-testid="internal-api-key-security-card"', false)
                ->assertSeeText('I8C Active Operations Key')
                ->assertSeeText('Shipments read')
                ->assertSeeText('Active')
                ->assertDontSeeText('sgw_i8c_seed_active_001')
                ->assertDontSeeText('key_hash');

            if ($email === 'e2e.internal.ops_readonly@example.test') {
                $detail->assertDontSee('data-testid="internal-api-key-account-link"', false);
            } else {
                $detail->assertSee('data-testid="internal-api-key-account-link"', false);
            }

            if ($email === 'e2e.internal.super_admin@example.test') {
                $detail->assertSee('data-testid="internal-api-key-rotate-form"', false)
                    ->assertSee('data-testid="internal-api-key-revoke-form"', false);
            } else {
                $detail->assertDontSee('data-testid="internal-api-key-rotate-form"', false)
                    ->assertDontSee('data-testid="internal-api-key-revoke-form"', false);
            }

            $this->actingAs($user, 'web')
                ->get(route('internal.api-keys.show', (string) $this->revokedKey->id))
                ->assertOk()
                ->assertSeeText('Revoked')
                ->assertDontSeeText('sgw_i8c_seed_revoked_001');
        }
    }

    #[Test]
    public function super_admin_can_create_rotate_and_revoke_api_keys_with_audit_and_one_time_secret(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $createReason = 'Created for an internal shipment reconciliation support workflow.';

        $this->actingAs($actor, 'web')
            ->post(route('internal.api-keys.store'), [
                'account_id' => (string) $this->accountC->id,
                'name' => 'I8C Created Admin Key',
                'scopes' => ['shipments:read'],
                'reason' => $createReason,
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('internal_api_key_plaintext');

        $createdKey = ApiKey::query()
            ->withoutGlobalScopes()
            ->where('name', 'I8C Created Admin Key')
            ->latest('created_at')
            ->firstOrFail();

        $createdPlaintext = (string) session('internal_api_key_plaintext');

        $this->assertTrue(str_starts_with($createdPlaintext, 'sgw_'));
        $this->assertSame(substr($createdPlaintext, 0, 8), (string) $createdKey->key_prefix);
        $this->assertNotSame($createdPlaintext, (string) $createdKey->key_hash);

        $this->assertNotNull(
            AuditLog::query()
                ->withoutGlobalScopes()
                ->where('account_id', (string) $this->accountC->id)
                ->where('user_id', (string) $actor->id)
                ->where('action', 'api_key.created')
                ->where('entity_id', (string) $createdKey->id)
                ->where('metadata->reason', $createReason)
                ->first()
        );

        $rotateReason = 'Rotated after a safe internal integration credentials review.';

        $this->actingAs($actor, 'web')
            ->post(route('internal.api-keys.rotate', (string) $this->activeKey->id), [
                'reason' => $rotateReason,
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('internal_api_key_plaintext');

        $this->activeKey->refresh();
        $this->assertFalse((bool) $this->activeKey->is_active);
        $this->assertNotNull($this->activeKey->revoked_at);

        $rotatedKey = ApiKey::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $this->activeKey->account_id)
            ->where('created_by', (string) $actor->id)
            ->where('name', 'I8C Active Operations Key (rotated)')
            ->latest('created_at')
            ->firstOrFail();

        $this->assertNotNull(
            AuditLog::query()
                ->withoutGlobalScopes()
                ->where('account_id', (string) $this->activeKey->account_id)
                ->where('user_id', (string) $actor->id)
                ->where('action', 'api_key.rotated')
                ->where('entity_id', (string) $rotatedKey->id)
                ->where('metadata->reason', $rotateReason)
                ->where('metadata->rotated_from_id', (string) $this->activeKey->id)
                ->first()
        );

        $revokeReason = 'Revoked after the rotation completed and the old support runbook was retired.';

        $this->actingAs($actor, 'web')
            ->post(route('internal.api-keys.revoke', (string) $rotatedKey->id), [
                'reason' => $revokeReason,
            ])
            ->assertRedirect(route('internal.api-keys.show', (string) $rotatedKey->id))
            ->assertSessionHas('success');

        $rotatedKey->refresh();
        $this->assertFalse((bool) $rotatedKey->is_active);
        $this->assertNotNull($rotatedKey->revoked_at);

        $this->assertNotNull(
            AuditLog::query()
                ->withoutGlobalScopes()
                ->where('account_id', (string) $rotatedKey->account_id)
                ->where('user_id', (string) $actor->id)
                ->where('action', 'api_key.revoked')
                ->where('entity_id', (string) $rotatedKey->id)
                ->where('metadata->reason', $revokeReason)
                ->first()
        );
    }

    #[Test]
    public function support_and_ops_readonly_cannot_mutate_api_keys_and_carrier_manager_is_denied(): void
    {
        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.api-keys.store'), [
                    'account_id' => (string) $this->accountC->id,
                    'name' => 'Should not create',
                    'scopes' => ['shipments:read'],
                    'reason' => 'Not allowed',
                ])
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.api-keys.rotate', (string) $this->activeKey->id), [
                    'reason' => 'Not allowed',
                ])
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.api-keys.revoke', (string) $this->activeKey->id), [
                    'reason' => 'Not allowed',
                ])
            );
        }

        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.api-keys.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.api-keys.show', (string) $this->activeKey->id))
        );
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_api_key_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.api-keys.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.api-keys.show', (string) $this->activeKey->id))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->post(route('internal.api-keys.store'), [
                'account_id' => (string) $this->accountC->id,
                'name' => 'External attempt',
                'scopes' => ['shipments:read'],
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
