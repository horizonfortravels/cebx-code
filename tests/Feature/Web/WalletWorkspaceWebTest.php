<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\ContentDeclaration;
use App\Models\Parcel;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WalletWorkspaceWebTest extends TestCase
{
    public function test_seeded_b2c_wallet_page_renders_funded_billing_wallet_without_500(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);

        $user = $this->seededUser('e2e.a.individual@example.test');

        $this->actingAs($user, 'web')
            ->get('/b2c/wallet')
            ->assertOk()
            ->assertSee('980.00')
            ->assertSee('25.00')
            ->assertSee('USD');

        $this->actingAs($user, 'web')
            ->get('/wallet')
            ->assertRedirect('/b2c/wallet');
    }

    public function test_seeded_b2b_wallet_page_renders_funded_billing_wallet_without_500(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);

        $user = $this->seededUser('e2e.c.organization_owner@example.test');

        $this->actingAs($user, 'web')
            ->get('/b2b/wallet')
            ->assertOk()
            ->assertSee('1,000.00')
            ->assertSee('USD');

        $this->actingAs($user, 'web')
            ->get('/wallet')
            ->assertRedirect('/b2b/wallet');
    }

    public function test_wallet_workspace_handles_missing_wallet_without_crashing(): void
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Wallet Missing '.Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'ar',
        ]);

        $this->grantTenantPermissions($user, [
            'wallet.balance',
            'wallet.ledger',
            'billing.view',
        ], 'wallet_workspace_missing');

        $this->actingAs($user, 'web')
            ->get('/b2b/wallet')
            ->assertOk()
            ->assertSee('0.00')
            ->assertDontSee('Server Error');
    }

    #[DataProvider('seededPreflightPersonaProvider')]
    public function test_seeded_funded_users_can_pass_wallet_preflight_in_browser_ready_routes(string $email, string $routePrefix): void
    {
        $this->seed(E2EUserMatrixSeeder::class);

        $user = $this->seededUser($email);
        $shipment = $this->createDeclarationCompleteShipment($user);

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post($routePrefix.$shipment->id.'/wallet-preflight')
            ->assertOk()
            ->assertSee('data-testid="carrier-issue-button"', false);

        $shipment->refresh();
        $this->assertSame(Shipment::STATUS_PAYMENT_PENDING, (string) $shipment->status);
        $this->assertNotNull($shipment->balance_reservation_id);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function seededPreflightPersonaProvider(): array
    {
        return [
            'b2c_individual' => ['e2e.a.individual@example.test', '/b2c/shipments/'],
            'b2b_owner' => ['e2e.c.organization_owner@example.test', '/b2b/shipments/'],
        ];
    }

    private function seededUser(string $email): User
    {
        return User::withoutGlobalScopes()->where('email', $email)->firstOrFail();
    }

    private function createDeclarationCompleteShipment(User $user): Shipment
    {
        $attributes = [
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'created_by' => (string) $user->id,
            'status' => Shipment::STATUS_DECLARATION_COMPLETE,
            'type' => 'international',
            'sender_name' => 'Sender',
            'sender_phone' => '+966500000001',
            'sender_address_1' => 'Origin Street',
            'sender_city' => 'Riyadh',
            'sender_postal_code' => '12211',
            'sender_country' => 'SA',
            'recipient_name' => 'Recipient',
            'recipient_phone' => '+12025550123',
            'recipient_address_1' => 'Destination Street',
            'recipient_city' => 'New York',
            'recipient_postal_code' => '10001',
            'recipient_country' => 'US',
            'is_international' => true,
            'has_dangerous_goods' => false,
            'total_weight' => 1.5,
            'chargeable_weight' => 1.5,
            'carrier_code' => 'fedex',
        ];

        if (Schema::hasColumn('shipments', 'currency')) {
            $attributes['currency'] = 'USD';
        }

        if (Schema::hasColumn('shipments', 'total_charge')) {
            $attributes['total_charge'] = 345.15;
        } elseif (Schema::hasColumn('shipments', 'total_cost')) {
            $attributes['total_cost'] = 345.15;
        }

        if (Schema::hasColumn('shipments', 'service_code')) {
            $attributes['service_code'] = 'FEDEX_GROUND';
        }

        if (Schema::hasColumn('shipments', 'service_name')) {
            $attributes['service_name'] = 'FedEx Ground';
        }

        $shipment = Shipment::factory()->create($attributes);

        Parcel::factory()->create([
            'shipment_id' => (string) $shipment->id,
            'weight' => 1.5,
            'length' => 20,
            'width' => 15,
            'height' => 10,
        ]);

        $quote = RateQuote::factory()->create([
            'account_id' => (string) $user->account_id,
            'shipment_id' => (string) $shipment->id,
            'origin_country' => 'SA',
            'origin_city' => 'Riyadh',
            'destination_country' => 'US',
            'destination_city' => 'New York',
            'currency' => 'USD',
            'requested_by' => (string) $user->id,
            'status' => RateQuote::STATUS_SELECTED,
        ]);

        $option = RateOption::query()->create([
            'rate_quote_id' => (string) $quote->id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'service_code' => 'FEDEX_GROUND',
            'service_name' => 'FedEx Ground',
            'net_rate' => 300.00,
            'fuel_surcharge' => 45.15,
            'other_surcharges' => 0.00,
            'total_net_rate' => 345.15,
            'markup_amount' => 0.00,
            'service_fee' => 0.00,
            'retail_rate_before_rounding' => 345.15,
            'retail_rate' => 345.15,
            'profit_margin' => 0.00,
            'currency' => 'USD',
            'estimated_days_min' => 3,
            'estimated_days_max' => 3,
            'estimated_delivery_at' => now()->addDays(3),
            'pricing_breakdown' => ['stage' => 'retail'],
            'rule_evaluation_log' => [
                'pricing_stage' => 'retail',
                'pricing_path' => 'shipment_quote',
            ],
            'is_available' => true,
        ]);

        $quote->update([
            'selected_option_id' => (string) $option->id,
            'status' => RateQuote::STATUS_SELECTED,
        ]);

        $shipment->update([
            'rate_quote_id' => (string) $quote->id,
            'selected_rate_option_id' => (string) $option->id,
            'status' => Shipment::STATUS_DECLARATION_COMPLETE,
        ]);

        ContentDeclaration::query()->create([
            'account_id' => (string) $user->account_id,
            'shipment_id' => (string) $shipment->id,
            'contains_dangerous_goods' => false,
            'dg_flag_declared' => true,
            'status' => ContentDeclaration::STATUS_COMPLETED,
            'waiver_accepted' => true,
            'declared_by' => (string) $user->id,
            'declared_at' => now(),
            'waiver_accepted_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'locale' => 'ar',
        ]);

        return $shipment->fresh(['selectedRateOption', 'rateQuote', 'balanceReservation', 'contentDeclaration', 'parcels']);
    }
}
