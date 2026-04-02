<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTopup;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalWalletReadCenterWebTest extends TestCase
{
    use RefreshDatabase;

    private Account $accountA;
    private Account $accountC;
    private BillingWallet $walletA;
    private BillingWallet $walletC;
    private Shipment $shipmentA;
    private Shipment $shipmentCaptured;
    private Shipment $shipmentReleased;
    private WalletHold $capturedHold;
    private WalletLedgerEntry $capturedLedgerEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->accountA = $this->accountBySlug('e2e-account-a');
        $this->accountC = $this->accountBySlug('e2e-account-c');
        $this->walletA = $this->walletForAccount($this->accountA);
        $this->walletC = $this->walletForAccount($this->accountC);
        $this->shipmentA = $this->shipmentByReference('SHP-I5A-A-001');

        $this->seedWalletReadFixtures();
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_wallet_index_and_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.billing.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-billing-table"', false)
                ->assertSeeText('E2E Account A')
                ->assertSeeText('E2E Account C')
                ->assertSeeText('USD');

            $this->assertHasNavigationLink($index, 'internal.billing.index');

            $detail = $this->actingAs($user, 'web')
                ->get(route('internal.billing.show', $this->accountA))
                ->assertOk()
                ->assertSee('data-testid="internal-billing-summary-card"', false)
                ->assertSee('data-testid="internal-billing-kyc-card"', false)
                ->assertSee('data-testid="internal-billing-ledger-card"', false)
                ->assertSee('data-testid="internal-billing-topups-card"', false)
                ->assertSee('data-testid="internal-billing-holds-card"', false)
                ->assertSee('data-testid="internal-billing-shipment-events-card"', false)
                ->assertSeeText('E2E Account A')
                ->assertSeeText('USD 980.00')
                ->assertSeeText('USD 25.00')
                ->assertSeeText('USD 955.00')
                ->assertSeeText('USD 200.00')
                ->assertSeeText('SHP-I5A-A-001')
                ->assertDontSeeText('checkout_url')
                ->assertDontSeeText('payment_reference')
                ->assertDontSeeText('gateway_metadata');

            if ($email === 'e2e.internal.ops_readonly@example.test') {
                $detail->assertDontSee('data-testid="internal-billing-account-link"', false);
            } else {
                $detail->assertSee('data-testid="internal-billing-account-link"', false);
            }
        }
    }

    #[Test]
    public function wallet_index_supports_search_and_basic_filters(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.billing.index', ['q' => 'E2E Account C']))
            ->assertOk()
            ->assertSeeText('E2E Account C')
            ->assertDontSeeText('E2E Account A');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.billing.index', ['currency' => 'USD']))
            ->assertOk()
            ->assertSeeText('E2E Account A')
            ->assertSeeText('E2E Account C');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.billing.index', ['status' => 'active']))
            ->assertOk()
            ->assertSeeText('E2E Account A')
            ->assertSeeText('E2E Account C');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.billing.index', ['low_balance' => 'yes']))
            ->assertOk()
            ->assertSeeText('E2E Account C')
            ->assertDontSeeText('E2E Account A');
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_see_billing_navigation_while_carrier_manager_does_not(): void
    {
        $superAdminPage = $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('admin.index'))
            ->assertOk();
        $this->assertHasNavigationLink($superAdminPage, 'internal.billing.index');

        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertHasNavigationLink($page, 'internal.billing.index');
        }

        $carrierManagerPage = $this->actingAs($this->userByEmail('e2e.internal.carrier_manager@example.test'), 'web')
            ->get(route('internal.home'))
            ->assertOk();

        $this->assertMissingNavigationLink($carrierManagerPage, 'internal.billing.index');
    }

    #[Test]
    public function wallet_detail_shows_preflight_hold_capture_release_and_ledger_links_safely(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.billing.show', $this->accountA))
            ->assertOk()
            ->assertSeeText('Reserved')
            ->assertSeeText('Captured')
            ->assertSeeText('Released')
            ->assertSeeText('Reservation capture')
            ->assertSeeText('Reservation release')
            ->assertSeeText('SHP-I6B-A-002')
            ->assertSeeText('SHP-I6B-A-003')
            ->assertSee('data-testid="internal-billing-hold-detail-link"', false)
            ->assertSee('data-testid="internal-billing-ledger-detail-link"', false)
            ->assertSee('data-testid="internal-billing-shipment-event-ledger-link"', false)
            ->assertDontSeeText('idempotency_key')
            ->assertDontSeeText('correlation_id');
    }

    #[Test]
    public function support_and_ops_readonly_can_open_preflight_and_ledger_detail_read_only(): void
    {
        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user, 'web')
                ->get(route('internal.billing.preflights.show', ['account' => $this->accountA, 'hold' => $this->capturedHold]))
                ->assertOk()
                ->assertSee('data-testid="internal-billing-preflight-summary-card"', false)
                ->assertSee('data-testid="internal-billing-preflight-shipment-card"', false)
                ->assertSee('data-testid="internal-billing-preflight-balance-card"', false)
                ->assertSee('data-testid="internal-billing-preflight-ledger-card"', false)
                ->assertSeeText('Captured')
                ->assertSeeText('Reservation capture')
                ->assertSeeText('SHP-I6B-A-002')
                ->assertSeeText('USD 52.00')
                ->assertDontSeeText('idempotency_key')
                ->assertDontSeeText('correlation_id');

            $this->actingAs($user, 'web')
                ->get(route('internal.billing.ledger.show', ['account' => $this->accountA, 'entry' => $this->capturedLedgerEntry]))
                ->assertOk()
                ->assertSee('data-testid="internal-billing-ledger-detail-card"', false)
                ->assertSee('data-testid="internal-billing-ledger-linked-shipment-card"', false)
                ->assertSee('data-testid="internal-billing-ledger-linked-preflight-card"', false)
                ->assertSeeText('Reservation capture')
                ->assertSeeText('SHP-I6B-A-002')
                ->assertSeeText('USD 52.00')
                ->assertDontSeeText('checkout_url')
                ->assertDontSeeText('payment_reference')
                ->assertDontSeeText('gateway_metadata');
        }
    }

    #[Test]
    public function carrier_manager_is_forbidden_from_internal_billing_routes(): void
    {
        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.billing.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.billing.show', $this->accountA))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.billing.preflights.show', ['account' => $this->accountA, 'hold' => $this->capturedHold]))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.billing.ledger.show', ['account' => $this->accountA, 'entry' => $this->capturedLedgerEntry]))
        );
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_billing_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.billing.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.billing.show', $this->accountA))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.billing.preflights.show', ['account' => $this->accountA, 'hold' => $this->capturedHold]))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.billing.ledger.show', ['account' => $this->accountA, 'entry' => $this->capturedLedgerEntry]))
        );
    }

    private function seedWalletReadFixtures(): void
    {
        WalletLedgerEntry::query()->where('wallet_id', (string) $this->walletA->id)->delete();
        WalletHold::query()->where('wallet_id', (string) $this->walletA->id)->delete();
        WalletTopup::query()->where('wallet_id', (string) $this->walletA->id)->delete();

        Shipment::query()
            ->withoutGlobalScopes()
            ->whereIn('reference_number', ['SHP-I6B-A-002', 'SHP-I6B-A-003'])
            ->delete();

        $this->walletA->forceFill([
            'available_balance' => 980.00,
            'reserved_balance' => 25.00,
            'total_credited' => 1200.00,
            'total_debited' => 220.00,
            'status' => 'active',
        ])->save();

        $this->walletC->forceFill([
            'low_balance_threshold' => 1500.00,
            'available_balance' => 1000.00,
            'reserved_balance' => 0.00,
            'status' => 'active',
        ])->save();

        $ownerA = $this->userByEmail('e2e.a.individual@example.test');

        $this->shipmentA->forceFill([
            'status' => Shipment::STATUS_PAYMENT_PENDING,
            'currency' => 'USD',
            'total_charge' => 25.00,
            'reserved_amount' => 25.00,
        ])->save();

        $this->shipmentCaptured = Shipment::factory()->create([
            'account_id' => (string) $this->accountA->id,
            'user_id' => (string) $ownerA->id,
            'created_by' => (string) $ownerA->id,
            'reference_number' => 'SHP-I6B-A-002',
            'status' => Shipment::STATUS_PURCHASED,
            'currency' => 'USD',
            'total_charge' => 52.00,
            'reserved_amount' => 52.00,
        ]);

        $this->shipmentReleased = Shipment::factory()->create([
            'account_id' => (string) $this->accountA->id,
            'user_id' => (string) $ownerA->id,
            'created_by' => (string) $ownerA->id,
            'reference_number' => 'SHP-I6B-A-003',
            'status' => Shipment::STATUS_REQUIRES_ACTION,
            'currency' => 'USD',
            'total_charge' => 18.00,
            'reserved_amount' => 0.00,
        ]);

        $topup = WalletTopup::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'account_id' => (string) $this->accountA->id,
            'amount' => 200.00,
            'currency' => 'USD',
            'status' => WalletTopup::STATUS_SUCCESS,
            'payment_gateway' => 'seeded-fixture',
            'confirmed_at' => now()->subHours(6),
        ]);

        $activeHold = WalletHold::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'account_id' => (string) $this->accountA->id,
            'amount' => 25.00,
            'currency' => 'USD',
            'shipment_id' => (string) $this->shipmentA->id,
            'source' => 'shipment_preflight',
            'status' => WalletHold::STATUS_ACTIVE,
            'idempotency_key' => 'i6b-hold-active-a-001',
            'expires_at' => now()->addHours(12),
        ]);

        $this->capturedHold = WalletHold::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'account_id' => (string) $this->accountA->id,
            'amount' => 52.00,
            'currency' => 'USD',
            'shipment_id' => (string) $this->shipmentCaptured->id,
            'source' => 'shipment_preflight',
            'status' => WalletHold::STATUS_CAPTURED,
            'idempotency_key' => 'i6b-hold-captured-a-001',
            'captured_at' => now()->subHours(2),
            'expires_at' => now()->addHours(4),
        ]);

        $releasedHold = WalletHold::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'account_id' => (string) $this->accountA->id,
            'amount' => 18.00,
            'currency' => 'USD',
            'shipment_id' => (string) $this->shipmentReleased->id,
            'source' => 'shipment_preflight',
            'status' => WalletHold::STATUS_RELEASED,
            'idempotency_key' => 'i6b-hold-released-a-001',
            'released_at' => now()->subHour(),
            'expires_at' => now()->addHours(3),
        ]);

        $this->shipmentA->forceFill([
            'balance_reservation_id' => (string) $activeHold->id,
            'reserved_amount' => 25.00,
        ])->save();

        $this->shipmentCaptured->forceFill([
            'balance_reservation_id' => (string) $this->capturedHold->id,
            'reserved_amount' => 52.00,
        ])->save();

        $this->shipmentReleased->forceFill([
            'balance_reservation_id' => (string) $releasedHold->id,
            'reserved_amount' => 0.00,
        ])->save();

        WalletLedgerEntry::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'sequence' => 1,
            'correlation_id' => 'i6b-ledger-topup-001',
            'transaction_type' => 'topup',
            'direction' => 'credit',
            'amount' => 200.00,
            'running_balance' => 1200.00,
            'reference_type' => 'topup',
            'reference_id' => (string) $topup->id,
            'notes' => 'Seeded top-up visibility fixture',
            'created_at' => now()->subHours(8),
        ]);

        WalletLedgerEntry::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'sequence' => 2,
            'correlation_id' => 'i6b-ledger-adjustment-001',
            'transaction_type' => WalletLedgerEntry::TYPE_ADJUSTMENT,
            'direction' => 'debit',
            'amount' => 20.00,
            'running_balance' => 1180.00,
            'reference_type' => 'adjustment',
            'reference_id' => 'i6b-adjustment-001',
            'notes' => 'Manual credit review adjustment',
            'created_at' => now()->subHours(7),
        ]);

        WalletLedgerEntry::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'sequence' => 3,
            'correlation_id' => 'i6b-ledger-hold-active-001',
            'transaction_type' => 'hold',
            'direction' => 'debit',
            'amount' => 25.00,
            'running_balance' => 1155.00,
            'reference_type' => 'hold',
            'reference_id' => (string) $activeHold->id,
            'notes' => 'Shipment preflight reserved funds for the active reservation.',
            'created_at' => now()->subHours(6),
        ]);

        WalletLedgerEntry::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'sequence' => 4,
            'correlation_id' => 'i6b-ledger-hold-captured-001',
            'transaction_type' => 'hold',
            'direction' => 'debit',
            'amount' => 52.00,
            'running_balance' => 1103.00,
            'reference_type' => 'hold',
            'reference_id' => (string) $this->capturedHold->id,
            'notes' => 'Shipment preflight reserved funds for the captured reservation.',
            'created_at' => now()->subHours(5),
        ]);

        $this->capturedLedgerEntry = WalletLedgerEntry::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'sequence' => 5,
            'correlation_id' => 'i6b-ledger-hold-capture-001',
            'transaction_type' => 'hold_capture',
            'direction' => 'debit',
            'amount' => 52.00,
            'running_balance' => 1051.00,
            'reference_type' => 'shipment',
            'reference_id' => (string) $this->shipmentCaptured->id,
            'notes' => 'Reservation captured when the shipment moved forward.',
            'created_at' => now()->subHours(4),
        ]);

        WalletLedgerEntry::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'sequence' => 6,
            'correlation_id' => 'i6b-ledger-hold-released-001',
            'transaction_type' => 'hold',
            'direction' => 'debit',
            'amount' => 18.00,
            'running_balance' => 1033.00,
            'reference_type' => 'hold',
            'reference_id' => (string) $releasedHold->id,
            'notes' => 'Shipment preflight reserved funds before the shipment returned to requires action.',
            'created_at' => now()->subHours(3),
        ]);

        WalletLedgerEntry::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'sequence' => 7,
            'correlation_id' => 'i6b-ledger-hold-release-001',
            'transaction_type' => 'hold_release',
            'direction' => 'credit',
            'amount' => 18.00,
            'running_balance' => 1051.00,
            'reference_type' => 'hold',
            'reference_id' => (string) $releasedHold->id,
            'notes' => 'Reservation released after the shipment required more action.',
            'created_at' => now()->subHours(2),
        ]);

        WalletLedgerEntry::query()->create([
            'wallet_id' => (string) $this->walletA->id,
            'sequence' => 8,
            'correlation_id' => 'i6b-ledger-shipment-001',
            'transaction_type' => 'debit',
            'direction' => 'debit',
            'amount' => 45.00,
            'running_balance' => 1006.00,
            'reference_type' => 'shipment',
            'reference_id' => (string) $this->shipmentA->id,
            'notes' => 'Shipment debit after label purchase.',
            'created_at' => now()->subHour(),
        ]);
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

    private function walletForAccount(Account $account): BillingWallet
    {
        return BillingWallet::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->where('currency', 'USD')
            ->firstOrFail();
    }

    private function shipmentByReference(string $reference): Shipment
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->where('reference_number', $reference)
            ->firstOrFail();
    }

    private function assertHasNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertSee('href="' . route($routeName) . '"', false);
    }

    private function assertMissingNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertDontSee('href="' . route($routeName) . '"', false);
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('الحالة الحالية: 403')
            ->assertDontSeeText('Internal Server Error');
    }
}
