<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\Address;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentAddressBookWebTest extends TestCase
{
    #[DataProvider('portalProvider')]
    public function test_portal_users_can_manage_saved_addresses(
        string $accountType,
        string $persona,
        string $indexRoute,
        string $createRoute,
        string $storeRoute,
        string $editRoute,
        string $updateRoute,
        string $destroyRoute
    ): void {
        $user = $this->createPortalUser($accountType, $persona, $this->addressBookPermissions());

        $this->actingAs($user, 'web')
            ->get(route($indexRoute))
            ->assertOk()
            ->assertSee(route($createRoute), false);

        $this->actingAs($user, 'web')
            ->get(route($createRoute))
            ->assertOk()
            ->assertSee('name="label"', false)
            ->assertSee('name="contact_name"', false);

        $this->actingAs($user, 'web')
            ->post(route($storeRoute), $this->addressPayload())
            ->assertRedirect(route($indexRoute));

        $address = Address::query()
            ->where('account_id', (string) $user->account_id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('Warehouse Alpha', (string) $address->label);

        $this->actingAs($user, 'web')
            ->get(route($editRoute, ['id' => (string) $address->id]))
            ->assertOk()
            ->assertSee('value="Warehouse Alpha"', false);

        $this->actingAs($user, 'web')
            ->patch(route($updateRoute, ['id' => (string) $address->id]), $this->addressPayload([
                'label' => 'Warehouse Beta',
                'city' => 'Jeddah',
                'type' => 'recipient',
            ]))
            ->assertRedirect(route($indexRoute));

        $address->refresh();

        $this->assertSame('Warehouse Beta', (string) $address->label);
        $this->assertSame('Jeddah', (string) $address->city);
        $this->assertSame('recipient', (string) $address->type);

        $this->actingAs($user, 'web')
            ->delete(route($destroyRoute, ['id' => (string) $address->id]))
            ->assertRedirect(route($indexRoute));

        $this->assertFalse(
            Address::query()
                ->where('account_id', (string) $user->account_id)
                ->where('id', (string) $address->id)
                ->exists()
        );
    }

    #[DataProvider('portalProviderWithShipmentRoutes')]
    public function test_saved_addresses_prefill_sender_and_recipient_fields_on_create_page(
        string $accountType,
        string $persona,
        string $createRoute
    ): void {
        $user = $this->createPortalUser($accountType, $persona, $this->shipmentFlowPermissions());
        $sender = Address::factory()->create([
            'account_id' => (string) $user->account_id,
            'type' => 'sender',
            'label' => 'Sender Hub',
            'contact_name' => 'Sender Contact',
            'company_name' => 'Sender Co',
            'phone' => '+14015550001',
            'email' => 'sender@example.test',
            'address_line_1' => '10 Sender Way',
            'address_line_2' => 'Dock 2',
            'city' => 'Providence',
            'state' => 'RI',
            'postal_code' => '02903',
            'country' => 'US',
        ]);
        $recipient = Address::factory()->create([
            'account_id' => (string) $user->account_id,
            'type' => 'recipient',
            'label' => 'Recipient Hub',
            'contact_name' => 'Recipient Contact',
            'company_name' => 'Recipient Co',
            'phone' => '+12125550001',
            'email' => 'recipient@example.test',
            'address_line_1' => '350 5th Ave',
            'address_line_2' => 'Floor 20',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10118',
            'country' => 'US',
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route($createRoute, [
                'sender_address' => (string) $sender->id,
                'recipient_address' => (string) $recipient->id,
            ]));

        $response->assertOk()
            ->assertSee('name="sender_address_id" value="' . $sender->id . '"', false)
            ->assertSee('name="recipient_address_id" value="' . $recipient->id . '"', false)
            ->assertSee('value="Sender Contact"', false)
            ->assertSee('value="Sender Co"', false)
            ->assertSee('value="sender@example.test"', false)
            ->assertSee('value="Dock 2"', false)
            ->assertSee('value="Recipient Contact"', false)
            ->assertSee('value="Recipient Co"', false)
            ->assertSee('value="recipient@example.test"', false)
            ->assertSee('value="Floor 20"', false);
    }

    public function test_b2b_same_tenant_org_user_can_reuse_address_created_by_another_org_user(): void
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Address Team ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $owner = $this->createPortalUserForAccount($account, 'organization_owner', $this->shipmentFlowPermissions());
        $staff = $this->createPortalUserForAccount($account, 'staff', $this->shipmentFlowPermissions());

        $address = Address::factory()->create([
            'account_id' => (string) $owner->account_id,
            'type' => 'sender',
            'label' => 'Shared Warehouse',
            'contact_name' => 'Shared Sender',
            'company_name' => 'Shared Co',
            'phone' => '+14015550077',
            'email' => 'shared@example.test',
            'address_line_1' => '25 Shared Lane',
            'city' => 'Providence',
            'state' => 'RI',
            'postal_code' => '02903',
            'country' => 'US',
        ]);

        $this->actingAs($staff, 'web')
            ->get(route('b2b.shipments.create', ['sender_address' => (string) $address->id]))
            ->assertOk()
            ->assertSee('name="sender_address_id" value="' . $address->id . '"', false)
            ->assertSee('value="Shared Sender"', false);

        $response = $this->actingAs($staff, 'web')
            ->post(route('b2b.shipments.store'), $this->shipmentPayload([
                'sender_address_id' => (string) $address->id,
                'sender_name' => 'Shared Sender',
                'sender_company' => 'Shared Co',
                'sender_phone' => '+14015550077',
                'sender_email' => 'shared@example.test',
                'sender_address_1' => '25 Shared Lane',
                'sender_city' => 'Providence',
                'sender_state' => 'RI',
                'sender_postal_code' => '02903',
                'sender_country' => 'US',
                'recipient_name' => 'Receiver',
                'recipient_phone' => '+12125550123',
                'recipient_address_1' => '350 5th Ave',
                'recipient_city' => 'New York',
                'recipient_state' => 'NY',
                'recipient_postal_code' => '10118',
                'recipient_country' => 'US',
            ]));

        $response->assertRedirect();

        $shipment = Shipment::query()
            ->where('account_id', (string) $account->id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('Shared Sender', (string) $shipment->sender_name);
        $this->assertSame('+14015550077', (string) $shipment->sender_phone);
        $this->assertSame('RI', (string) $shipment->sender_state);
    }

    #[DataProvider('portalProviderWithShipmentRoutes')]
    public function test_cross_tenant_address_access_is_not_found_on_portal_surfaces(
        string $accountType,
        string $persona,
        string $createRoute,
        string $storeRoute,
        string $editRoute,
        string $updateRoute,
        string $destroyRoute
    ): void {
        $viewer = $this->createPortalUser($accountType, $persona . '_viewer', $this->shipmentFlowPermissions());
        $other = $this->createPortalUser($accountType, $persona . '_other', $this->shipmentFlowPermissions());
        $address = Address::factory()->create([
            'account_id' => (string) $other->account_id,
            'type' => 'sender',
        ]);

        $this->actingAs($viewer, 'web')
            ->get(route($editRoute, ['id' => (string) $address->id]))
            ->assertNotFound();

        $this->actingAs($viewer, 'web')
            ->patch(route($updateRoute, ['id' => (string) $address->id]), $this->addressPayload())
            ->assertNotFound();

        $this->actingAs($viewer, 'web')
            ->delete(route($destroyRoute, ['id' => (string) $address->id]))
            ->assertNotFound();

        $this->actingAs($viewer, 'web')
            ->get(route($createRoute, ['sender_address' => (string) $address->id]))
            ->assertNotFound();

        $this->actingAs($viewer, 'web')
            ->post(route($storeRoute), $this->shipmentPayload([
                'sender_address_id' => (string) $address->id,
                'sender_name' => 'Viewer Sender',
                'sender_phone' => '+14015550101',
                'sender_address_1' => '1 Market Street',
                'sender_city' => 'Providence',
                'sender_state' => 'RI',
                'sender_postal_code' => '02903',
                'sender_country' => 'US',
                'recipient_name' => 'Viewer Recipient',
                'recipient_phone' => '+12125550123',
                'recipient_address_1' => '350 5th Ave',
                'recipient_city' => 'New York',
                'recipient_state' => 'NY',
                'recipient_postal_code' => '10118',
                'recipient_country' => 'US',
            ]))
            ->assertNotFound();
    }

    public static function portalProvider(): array
    {
        return [
            'b2c' => ['individual', 'individual', 'b2c.addresses.index', 'b2c.addresses.create', 'b2c.addresses.store', 'b2c.addresses.edit', 'b2c.addresses.update', 'b2c.addresses.destroy'],
            'b2b' => ['organization', 'organization_owner', 'b2b.addresses.index', 'b2b.addresses.create', 'b2b.addresses.store', 'b2b.addresses.edit', 'b2b.addresses.update', 'b2b.addresses.destroy'],
        ];
    }

    public static function portalProviderWithShipmentRoutes(): array
    {
        return [
            'b2c' => ['individual', 'individual', 'b2c.shipments.create', 'b2c.shipments.store', 'b2c.addresses.edit', 'b2c.addresses.update', 'b2c.addresses.destroy'],
            'b2b' => ['organization', 'organization_owner', 'b2b.shipments.create', 'b2b.shipments.store', 'b2b.addresses.edit', 'b2b.addresses.update', 'b2b.addresses.destroy'],
        ];
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createPortalUser(string $accountType, string $persona, array $permissions): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'Address B2C ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'Address B2B ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        return $this->createPortalUserForAccount($account, $persona, $permissions);
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createPortalUserForAccount(Account $account, string $persona, array $permissions): User
    {
        $user = User::factory()->create([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'ar',
        ]);

        $this->grantTenantPermissions($user, $permissions, 'shipment_address_book_' . $persona . '_' . Str::lower(Str::random(6)));

        return $user;
    }

    /**
     * @return array<int, string>
     */
    private function addressBookPermissions(): array
    {
        return [
            'addresses.read',
            'addresses.manage',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function shipmentFlowPermissions(): array
    {
        return [
            'addresses.read',
            'addresses.manage',
            'shipments.read',
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
    private function addressPayload(array $overrides = []): array
    {
        return array_replace([
            'type' => 'both',
            'label' => 'Warehouse Alpha',
            'contact_name' => 'Warehouse Contact',
            'company_name' => 'Warehouse Co',
            'phone' => '+966500000001',
            'email' => 'warehouse@example.test',
            'address_line_1' => '1 Logistics Ave',
            'address_line_2' => 'Bay 4',
            'city' => 'Riyadh',
            'state' => null,
            'postal_code' => '12211',
            'country' => 'SA',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function shipmentPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'sender_name' => 'Sender',
            'sender_phone' => '+14015550101',
            'sender_address_1' => '1 Market Street',
            'sender_city' => 'Providence',
            'sender_state' => 'RI',
            'sender_postal_code' => '02903',
            'sender_country' => 'US',
            'recipient_name' => 'Recipient',
            'recipient_phone' => '+12125550123',
            'recipient_address_1' => '350 5th Ave',
            'recipient_city' => 'New York',
            'recipient_state' => 'NY',
            'recipient_postal_code' => '10118',
            'recipient_country' => 'US',
            'parcels' => [[
                'weight' => 1.5,
                'length' => 20,
                'width' => 15,
                'height' => 10,
            ]],
        ], $overrides);
    }
}
