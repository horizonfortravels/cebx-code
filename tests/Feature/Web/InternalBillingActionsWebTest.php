<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BillingWallet;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalBillingActionsWebTest extends TestCase
{
    use RefreshDatabase;

    private Account $accountA;
    private BillingWallet $walletA;
    private Shipment $shipmentA;
    private WalletHold $staleHold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->accountA = $this->accountBySlug('e2e-account-a');
        $this->walletA = BillingWallet::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $this->accountA->id)
            ->where('currency', 'USD')
            ->firstOrFail();
        $this->shipmentA = $this->shipmentByReference('SHP-I5A-A-001');
        $this->staleHold = WalletHold::query()
            ->withoutGlobalScopes()
            ->where('wallet_id', (string) $this->walletA->id)
            ->where('idempotency_key', 'e2e:i6b:hold:active:a')
            ->firstOrFail();
    }

    #[Test]
    public function super_admin_can_release_a_stale_hold_and_audit_is_recorded(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $reason = 'Expired reservation cleared after internal billing review.';

        $this->actingAs($actor, 'web')
            ->get(route('internal.billing.preflights.show', ['account' => $this->accountA, 'hold' => $this->staleHold]))
            ->assertOk()
            ->assertSee('data-testid="internal-billing-hold-actions-card"', false)
            ->assertSee('data-testid="internal-billing-release-hold-form"', false);

        $this->actingAs($actor, 'web')
            ->post(route('internal.billing.preflights.release', ['account' => $this->accountA, 'hold' => $this->staleHold]), [
                'reason' => $reason,
            ])
            ->assertRedirect(route('internal.billing.preflights.show', ['account' => $this->accountA, 'hold' => $this->staleHold]))
            ->assertSessionHas('success');

        $this->staleHold->refresh();
        $this->walletA->refresh();
        $this->shipmentA->refresh();

        $this->assertSame(WalletHold::STATUS_RELEASED, (string) $this->staleHold->status);
        $this->assertNotNull($this->staleHold->released_at);
        $this->assertSame(0.0, (float) $this->walletA->reserved_balance);
        $this->assertNull($this->shipmentA->balance_reservation_id);
        $this->assertNull($this->shipmentA->reserved_amount);

        $releaseLedger = WalletLedgerEntry::query()
            ->withoutGlobalScopes()
            ->where('wallet_id', (string) $this->walletA->id)
            ->where('transaction_type', 'hold_release')
            ->where('reference_type', 'hold')
            ->where('reference_id', (string) $this->staleHold->id)
            ->latest('sequence')
            ->first();

        $this->assertNotNull($releaseLedger);

        $auditEntry = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $this->accountA->id)
            ->where('user_id', (string) $actor->id)
            ->where('action', 'billing.hold_released')
            ->where('entity_id', (string) $this->staleHold->id)
            ->latest()
            ->first();

        $this->assertNotNull($auditEntry);
        $this->assertSame($reason, (string) data_get($auditEntry?->metadata, 'reason'));
        $this->assertSame(true, (bool) data_get($auditEntry?->metadata, 'shipment_link_cleared'));
    }

    #[Test]
    public function support_ops_readonly_and_carrier_manager_cannot_mutate_stale_holds(): void
    {
        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            if ($email !== 'e2e.internal.carrier_manager@example.test') {
                $this->actingAs($user, 'web')
                    ->get(route('internal.billing.preflights.show', ['account' => $this->accountA, 'hold' => $this->staleHold]))
                    ->assertOk()
                    ->assertDontSee('data-testid="internal-billing-hold-actions-card"', false)
                    ->assertDontSee('data-testid="internal-billing-release-hold-form"', false);
            }

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.billing.preflights.release', ['account' => $this->accountA, 'hold' => $this->staleHold]), [
                    'reason' => 'Not allowed',
                ])
            );
        }

        $this->staleHold->refresh();
        $this->assertSame(WalletHold::STATUS_ACTIVE, (string) $this->staleHold->status);
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_billing_action_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->post(route('internal.billing.preflights.release', ['account' => $this->accountA, 'hold' => $this->staleHold]), [
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

    private function accountBySlug(string $slug): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function shipmentByReference(string $reference): Shipment
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->where('reference_number', $reference)
            ->firstOrFail();
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('الحالة الحالية: 403')
            ->assertDontSeeText('Internal Server Error');
    }
}
