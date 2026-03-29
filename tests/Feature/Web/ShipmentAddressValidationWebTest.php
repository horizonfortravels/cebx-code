<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\Address;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentAddressValidationWebTest extends TestCase
{
    public function test_sender_validation_shows_normalized_suggestion_and_apply_updates_only_sender_fields(): void
    {
        $user = $this->createPortalUser('organization', 'organization_owner');

        $validatePayload = $this->shipmentPayload([
            'sender_address_1' => ' 1 Market   Street ',
            'sender_city' => ' Providence ',
            'sender_state' => 'rhode island',
            'sender_postal_code' => '029031234',
            'sender_country' => 'us',
            'address_validation_action' => 'validate_sender',
        ]);

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post(route('b2b.shipments.address-validation'), $validatePayload)
            ->assertOk()
            ->assertSee('data-testid="sender-address-validation"', false)
            ->assertSee('name="sender_state" value="rhode island"', false)
            ->assertSee('02903-1234', false)
            ->assertSee('RI', false);

        $applyPayload = array_merge($this->shipmentPayload(), [
            'address_validation_action' => 'apply_sender',
            'address_validation_suggestions' => [
                'sender' => [
                    'sender_address_1' => '1 Market Street',
                    'sender_address_2' => '',
                    'sender_city' => 'Providence',
                    'sender_state' => 'RI',
                    'sender_postal_code' => '02903-1234',
                    'sender_country' => 'US',
                ],
            ],
        ]);

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post(route('b2b.shipments.address-validation'), $applyPayload)
            ->assertOk()
            ->assertSee('name="sender_state" value="RI"', false)
            ->assertSee('name="sender_postal_code" value="02903-1234"', false)
            ->assertSee('name="sender_country" maxlength="2" value="US"', false);
    }

    public function test_recipient_saved_address_can_be_validated_without_losing_prefill_context(): void
    {
        $user = $this->createPortalUser('organization', 'organization_owner');
        $recipient = Address::factory()->create([
            'account_id' => (string) $user->account_id,
            'type' => 'recipient',
            'label' => 'Recipient Dock',
            'contact_name' => 'Receiver',
            'company_name' => 'Receiver Co',
            'phone' => '+12125550123',
            'email' => 'receiver@example.test',
            'address_line_1' => '350 5th Ave',
            'address_line_2' => 'Floor 20',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10118',
            'country' => 'US',
        ]);

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post(route('b2b.shipments.address-validation'), array_merge($this->shipmentPayload([
                'recipient_address_id' => (string) $recipient->id,
                'recipient_name' => 'Receiver',
                'recipient_company' => 'Receiver Co',
                'recipient_phone' => '+12125550123',
                'recipient_email' => 'receiver@example.test',
                'recipient_address_1' => '350 5th Ave',
                'recipient_address_2' => 'Floor 20',
                'recipient_city' => 'New York',
                'recipient_state' => 'NY',
                'recipient_postal_code' => '10118',
                'recipient_country' => 'US',
            ]), [
                'address_validation_action' => 'validate_recipient',
            ]))
            ->assertOk()
            ->assertSee('data-testid="selected-recipient-address-banner"', false)
            ->assertSee('data-testid="recipient-address-validation"', false)
            ->assertDontSee('data-testid="apply-recipient-address-suggestion"', false);
    }

    public function test_provider_unavailability_is_non_blocking_for_valid_draft_creation(): void
    {
        config()->set('services.address_validation.driver', 'disabled');

        $user = $this->createPortalUser('organization', 'organization_owner');

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post(route('b2b.shipments.address-validation'), array_merge($this->shipmentPayload(), [
                'address_validation_action' => 'validate_sender',
            ]))
            ->assertOk()
            ->assertSee('data-testid="address-validation-provider-warning"', false)
            ->assertSee('data-testid="sender-address-validation"', false);

        $response = $this->actingAs($user, 'web')
            ->post(route('b2b.shipments.store'), $this->shipmentPayload());

        $response->assertRedirect();
        $this->assertStringContainsString('/b2b/shipments/create?draft=', (string) $response->headers->get('Location'));

        $shipment = Shipment::query()
            ->where('account_id', (string) $user->account_id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame(Shipment::STATUS_READY_FOR_RATES, (string) $shipment->status);
    }

    public function test_saved_address_prefill_still_creates_a_new_valid_draft_after_validation(): void
    {
        $user = $this->createPortalUser('organization', 'organization_owner');
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

        $this->actingAs($user, 'web')
            ->post(route('b2b.shipments.address-validation'), array_merge($this->shipmentPayload([
                'sender_address_id' => (string) $sender->id,
                'sender_name' => 'Sender Contact',
                'sender_company' => 'Sender Co',
                'sender_phone' => '+14015550001',
                'sender_email' => 'sender@example.test',
                'sender_address_1' => '10 Sender Way',
                'sender_address_2' => 'Dock 2',
                'sender_city' => 'Providence',
                'sender_state' => 'RI',
                'sender_postal_code' => '02903',
                'sender_country' => 'US',
                'recipient_address_id' => (string) $recipient->id,
                'recipient_name' => 'Recipient Contact',
                'recipient_company' => 'Recipient Co',
                'recipient_phone' => '+12125550001',
                'recipient_email' => 'recipient@example.test',
                'recipient_address_1' => '350 5th Ave',
                'recipient_address_2' => 'Floor 20',
                'recipient_city' => 'New York',
                'recipient_state' => 'NY',
                'recipient_postal_code' => '10118',
                'recipient_country' => 'US',
            ]), [
                'address_validation_action' => 'validate_sender',
            ]))
            ->assertRedirect();

        $response = $this->actingAs($user, 'web')
            ->post(route('b2b.shipments.store'), $this->shipmentPayload([
                'sender_address_id' => (string) $sender->id,
                'sender_name' => 'Sender Contact',
                'sender_company' => 'Sender Co',
                'sender_phone' => '+14015550001',
                'sender_email' => 'sender@example.test',
                'sender_address_1' => '10 Sender Way',
                'sender_address_2' => 'Dock 2',
                'sender_city' => 'Providence',
                'sender_state' => 'RI',
                'sender_postal_code' => '02903',
                'sender_country' => 'US',
                'recipient_address_id' => (string) $recipient->id,
                'recipient_name' => 'Recipient Contact',
                'recipient_company' => 'Recipient Co',
                'recipient_phone' => '+12125550001',
                'recipient_email' => 'recipient@example.test',
                'recipient_address_1' => '350 5th Ave',
                'recipient_address_2' => 'Floor 20',
                'recipient_city' => 'New York',
                'recipient_state' => 'NY',
                'recipient_postal_code' => '10118',
                'recipient_country' => 'US',
            ]));

        $response->assertRedirect();

        $shipment = Shipment::query()
            ->where('account_id', (string) $user->account_id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame(Shipment::STATUS_READY_FOR_RATES, (string) $shipment->status);
        $this->assertSame('Sender Contact', (string) $shipment->sender_name);
        $this->assertSame('Recipient Contact', (string) $shipment->recipient_name);
        $this->assertSame('RI', (string) $shipment->sender_state);
        $this->assertSame('NY', (string) $shipment->recipient_state);
    }

    private function createPortalUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'Validation B2C ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'Validation B2B ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'en',
        ]);

        $this->grantTenantPermissions($user, [
            'addresses.read',
            'addresses.manage',
            'shipments.read',
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
        ], 'shipment_address_validation_' . $persona . '_' . Str::lower(Str::random(6)));

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function shipmentPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'sender_name' => 'Sender',
            'sender_phone' => '+14015550001',
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
}
