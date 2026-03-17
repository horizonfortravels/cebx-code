<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentDraftFlowWebTest extends TestCase
{
    public function test_b2c_create_page_shows_staged_guidance(): void
    {
        $user = $this->createPortalUser('individual', $this->shipmentRequestPermissions());

        $this->actingAs($user, 'web')
            ->get('/b2c/shipments/create')
            ->assertOk()
            ->assertSee('name="sender_name"', false)
            ->assertSee('name="recipient_name"', false)
            ->assertSee('name="sender_state"', false)
            ->assertSee('name="recipient_state"', false)
            ->assertSee('name="parcels[0][weight]"', false);
    }

    public function test_invalid_b2c_submission_shows_validation_guidance(): void
    {
        $user = $this->createPortalUser('individual', $this->shipmentRequestPermissions());

        $response = $this->actingAs($user, 'web')
            ->from('/b2c/shipments/create')
            ->post('/b2c/shipments', $this->shipmentPayload([
                'recipient_name' => '',
            ]));

        $response->assertRedirect();
        $this->assertStringContainsString('/b2c/shipments/create', (string) $response->headers->get('Location'));
        $response->assertSessionHasErrors(['recipient_name']);

        $this->followingRedirects()
            ->actingAs($user, 'web')
            ->post('/b2c/shipments', $this->shipmentPayload([
                'recipient_name' => '',
            ]))
            ->assertOk()
            ->assertSee('name="recipient_name"', false);
    }

    public function test_b2b_submission_shows_kyc_guidance_when_blocked(): void
    {
        $user = $this->createPortalUser('organization', $this->shipmentRequestPermissions());

        $response = $this->actingAs($user, 'web')
            ->from('/b2b/shipments/create')
            ->post('/b2b/shipments', $this->shipmentPayload([
                'recipient_state' => 'NY',
            ]));

        $response->assertRedirect('/b2b/shipments/create?draft=' . Shipment::query()->where('account_id', $user->account_id)->latest('created_at')->value('id'));
        $response->assertSessionHas('shipment_workflow_feedback');

        $feedback = session('shipment_workflow_feedback');
        $this->assertSame('ERR_KYC_REQUIRED', data_get($feedback, 'error_code'));
        $this->assertSame('unverified', data_get($feedback, 'kyc_status'));
        $this->assertSame('international_restricted', data_get($feedback, 'reason_code'));
        $this->assertSame('kyc_blocked', session('shipment_workflow_state'));
    }

    public function test_b2b_owner_can_submit_valid_shipment_draft_without_crashing_when_reference_exists_in_another_tenant(): void
    {
        $this->seedExistingCrossTenantShipmentReference();

        $user = $this->createPortalUser('organization', $this->shipmentRequestPermissions(), 'organization_owner');

        $response = $this->actingAs($user, 'web')
            ->post('/b2b/shipments', $this->domesticShipmentPayload());

        $response->assertRedirect();
        $this->assertStringContainsString('/b2b/shipments/create?draft=', (string) $response->headers->get('Location'));

        $this->followingRedirects()
            ->actingAs($user, 'web')
            ->post('/b2b/shipments', $this->domesticShipmentPayload())
            ->assertOk()
            ->assertSee('مقارنة العروض المتاحة');

        $shipment = Shipment::withoutGlobalScopes()
            ->where('account_id', $user->account_id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame(Shipment::STATUS_READY_FOR_RATES, $shipment->status);
        $this->assertNotSame($this->firstYearReference(), $shipment->reference_number);
    }

    public function test_b2b_admin_can_submit_valid_shipment_draft_without_crashing_when_reference_exists_in_another_tenant(): void
    {
        $this->seedExistingCrossTenantShipmentReference();

        $user = $this->createPortalUser('organization', $this->shipmentRequestPermissions(), 'organization_admin');

        $response = $this->actingAs($user, 'web')
            ->post('/b2b/shipments', $this->domesticShipmentPayload());

        $response->assertRedirect();
        $this->assertStringContainsString('/b2b/shipments/create?draft=', (string) $response->headers->get('Location'));

        $shipment = Shipment::withoutGlobalScopes()
            ->where('account_id', $user->account_id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame(Shipment::STATUS_READY_FOR_RATES, $shipment->status);
    }

    public function test_b2b_staff_can_submit_valid_shipment_draft_without_crashing_when_reference_exists_in_another_tenant(): void
    {
        $this->seedExistingCrossTenantShipmentReference();

        $user = $this->createPortalUser('organization', $this->shipmentRequestPermissions(), 'staff');

        $response = $this->actingAs($user, 'web')
            ->post('/b2b/shipments', $this->domesticShipmentPayload());

        $response->assertRedirect();
        $this->assertStringContainsString('/b2b/shipments/create?draft=', (string) $response->headers->get('Location'));

        $shipment = Shipment::withoutGlobalScopes()
            ->where('account_id', $user->account_id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame(Shipment::STATUS_READY_FOR_RATES, $shipment->status);
    }

    public function test_invalid_b2b_submission_returns_validation_errors_instead_of_crashing(): void
    {
        $user = $this->createPortalUser('organization', $this->shipmentRequestPermissions(), 'organization_owner');

        $response = $this->actingAs($user, 'web')
            ->from('/b2b/shipments/create')
            ->post('/b2b/shipments', $this->domesticShipmentPayload([
                'recipient_name' => '',
            ]));

        $response->assertRedirect('/b2b/shipments/create');
        $response->assertSessionHasErrors(['recipient_name']);

        $this->followingRedirects()
            ->actingAs($user, 'web')
            ->from('/b2b/shipments/create')
            ->post('/b2b/shipments', $this->domesticShipmentPayload([
                'recipient_name' => '',
            ]))
            ->assertOk()
            ->assertSee('name="recipient_name"', false);
    }

    public function test_us_browser_submission_requires_state_codes(): void
    {
        $user = $this->createPortalUser('organization', $this->shipmentRequestPermissions(), 'organization_owner');

        $response = $this->actingAs($user, 'web')
            ->from('/b2b/shipments/create')
            ->post('/b2b/shipments', $this->domesticShipmentPayload([
                'sender_state' => '',
                'recipient_state' => '',
            ]));

        $response->assertRedirect('/b2b/shipments/create');
        $response->assertSessionHasErrors(['sender_state', 'recipient_state']);

        $this->assertNull(Shipment::query()->where('account_id', $user->account_id)->first());
    }

    public function test_non_us_route_is_not_over_constrained_by_state_validation(): void
    {
        $user = $this->createPortalUser('individual', $this->shipmentRequestPermissions());

        $response = $this->actingAs($user, 'web')
            ->post('/b2c/shipments', $this->shipmentPayload([
                'sender_country' => 'SA',
                'sender_city' => 'Riyadh',
                'sender_state' => '',
                'sender_postal_code' => '12211',
                'recipient_country' => 'SA',
                'recipient_city' => 'Jeddah',
                'recipient_state' => '',
                'recipient_postal_code' => '21577',
            ]));

        $response->assertRedirect();
        $this->assertStringContainsString('/b2c/shipments/create?draft=', (string) $response->headers->get('Location'));
        $response->assertSessionDoesntHaveErrors();

        $shipment = Shipment::query()
            ->where('account_id', $user->account_id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame(Shipment::STATUS_READY_FOR_RATES, $shipment->status);
        $this->assertNull($shipment->sender_state);
        $this->assertNull($shipment->recipient_state);
    }

    public function test_b2b_cross_tenant_shipment_page_returns_not_found(): void
    {
        $viewer = $this->createPortalUser('organization', $this->shipmentRequestPermissions(), 'organization_owner');
        $otherAccount = Account::factory()->create([
            'type' => 'organization',
            'status' => 'active',
        ]);

        $shipment = Shipment::factory()->create([
            'account_id' => $otherAccount->id,
            'reference_number' => 'SHP-XT-' . Str::upper(Str::random(8)),
        ]);

        $this->actingAs($viewer, 'web')
            ->get('/b2b/shipments/' . $shipment->id)
            ->assertNotFound();
    }

    public function test_default_demo_b2b_owner_can_open_shipment_draft_page(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', 'sultan@techco.sa')
            ->firstOrFail();

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/create')
            ->assertOk();
    }

    public function test_default_demo_b2b_admin_can_open_shipment_draft_page(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', 'hind@techco.sa')
            ->firstOrFail();

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/create')
            ->assertOk();
    }

    public function test_default_demo_b2b_staff_can_open_shipment_draft_page(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', 'majed@techco.sa')
            ->firstOrFail();

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/create')
            ->assertOk();
    }

    public function test_default_demo_individual_holder_can_open_b2c_shipment_draft_page(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', 'mohammed@example.sa')
            ->firstOrFail();

        $this->actingAs($user, 'web')
            ->get('/b2c/shipments/create')
            ->assertOk();
    }

    private function createPortalUser(string $accountType, array $permissions, ?string $roleName = null): User
    {
        $account = Account::factory()->create([
            'type' => $accountType,
            'name' => ucfirst($accountType) . ' Portal ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, $permissions, $roleName ?? ('shipment_web_flow_' . $accountType));

        return $user;
    }

    /**
     * @return array<int, string>
     */
    private function shipmentRequestPermissions(): array
    {
        return [
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function shipmentPayload(array $overrides = []): array
    {
        return array_replace_recursive([
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
            'parcels' => [
                [
                    'weight' => 1.5,
                    'length' => 20,
                    'width' => 15,
                    'height' => 10,
                ],
            ],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function domesticShipmentPayload(array $overrides = []): array
    {
        return $this->shipmentPayload(array_replace_recursive([
            'sender_country' => 'US',
            'sender_city' => 'Austin',
            'sender_state' => 'TX',
            'sender_postal_code' => '73301',
            'recipient_country' => 'US',
            'recipient_city' => 'Dallas',
            'recipient_state' => 'TX',
            'recipient_postal_code' => '75001',
        ], $overrides));
    }

    private function seedExistingCrossTenantShipmentReference(): void
    {
        $otherAccount = Account::factory()->create([
            'type' => 'organization',
            'status' => 'active',
        ]);

        Shipment::withoutGlobalScopes()->create([
            'account_id' => $otherAccount->id,
            'reference_number' => $this->firstYearReference(),
            'source' => Shipment::SOURCE_DIRECT,
            'status' => Shipment::STATUS_DRAFT,
            'sender_name' => 'Existing Sender',
            'sender_phone' => '+12025550001',
            'sender_address' => '1 Existing St',
            'sender_address_1' => '1 Existing St',
            'sender_city' => 'New York',
            'sender_country' => 'US',
            'recipient_name' => 'Existing Receiver',
            'recipient_phone' => '+12025550002',
            'recipient_address' => '2 Existing Ave',
            'recipient_address_1' => '2 Existing Ave',
            'recipient_city' => 'Boston',
            'recipient_country' => 'US',
            'is_international' => false,
            'parcels_count' => 1,
            'pieces' => 1,
            'total_weight' => 1,
            'weight' => 1,
            'chargeable_weight' => 1,
            'content_description' => 'Existing shipment',
        ]);
    }

    private function firstYearReference(): string
    {
        return 'SHP-' . now()->format('Y') . '0001';
    }
}
