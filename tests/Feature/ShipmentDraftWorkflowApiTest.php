<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentDraftWorkflowApiTest extends TestCase
{
    public function test_invalid_shipment_data_blocks_before_rates(): void
    {
        $user = $this->createShipmentActor($this->shipmentRequestPermissions());
        $shipment = $this->createDraftShipment($user, [
            'sender_country' => 'SA',
            'recipient_country' => 'US',
            'sender_postal_code' => null,
            'recipient_postal_code' => null,
        ]);

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_VALIDATION_FAILED')
            ->assertJsonPath('context.current_status', 'draft');

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/rates', [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_INVALID_STATE_FOR_RATES');

        if (Schema::hasTable('rate_quotes')) {
            $this->assertDatabaseMissing('rate_quotes', ['shipment_id' => $shipment['id']]);
        }
    }

    public function test_unverified_account_is_blocked_before_rates_for_restricted_shipment(): void
    {
        $user = $this->createShipmentActor($this->shipmentRequestPermissions());
        $shipment = $this->createDraftShipment($user, [
            'sender_country' => 'SA',
            'recipient_country' => 'US',
            'sender_postal_code' => '11564',
            'recipient_postal_code' => '10001',
        ]);

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_KYC_REQUIRED')
            ->assertJsonPath('context.current_status', 'kyc_blocked');

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment['id'],
            'status' => 'kyc_blocked',
        ]);
    }

    public function test_pending_kyc_state_blocks_correctly(): void
    {
        $user = $this->createShipmentActor($this->shipmentRequestPermissions());

        KycVerification::query()->create([
            'account_id' => $user->account_id,
            'status' => KycVerification::STATUS_PENDING,
            'verification_type' => 'account',
            'verification_level' => 'basic',
            'submitted_at' => now(),
        ]);

        $shipment = $this->createDraftShipment($user, [
            'sender_country' => 'SA',
            'recipient_country' => 'US',
            'sender_postal_code' => '11564',
            'recipient_postal_code' => '10001',
        ]);

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_KYC_PENDING_REVIEW')
            ->assertJsonPath('context.current_status', 'kyc_blocked');
    }

    public function test_rejected_kyc_state_blocks_correctly(): void
    {
        $user = $this->createShipmentActor($this->shipmentRequestPermissions());

        KycVerification::query()->create([
            'account_id' => $user->account_id,
            'status' => KycVerification::STATUS_REJECTED,
            'verification_type' => 'account',
            'verification_level' => 'basic',
            'submitted_at' => now(),
        ]);

        $shipment = $this->createDraftShipment($user, [
            'sender_country' => 'SA',
            'recipient_country' => 'US',
            'sender_postal_code' => '11564',
            'recipient_postal_code' => '10001',
        ]);

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_KYC_REJECTED')
            ->assertJsonPath('context.current_status', 'kyc_blocked');
    }

    public function test_verified_account_can_reach_ready_for_rates(): void
    {
        $user = $this->createShipmentActor($this->shipmentRequestPermissions());

        KycVerification::query()->create([
            'account_id' => $user->account_id,
            'status' => KycVerification::STATUS_APPROVED,
            'verification_type' => 'account',
            'verification_level' => 'enhanced',
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $shipment = $this->createDraftShipment($user, [
            'sender_country' => 'SA',
            'recipient_country' => 'US',
            'sender_postal_code' => '11564',
            'recipient_postal_code' => '10001',
        ]);

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_rates');

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment['id'],
            'status' => 'ready_for_rates',
            'kyc_verified' => 1,
        ]);
    }

    private function createShipmentActor(array $permissions): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Workflow Org ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, $permissions, 'shipment_phase_b_actor');

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
    private function createDraftShipment(User $user, array $overrides = []): array
    {
        $payload = array_replace_recursive($this->shipmentPayload(), $overrides);

        $response = $this->postJson('/api/v1/shipments', $payload, $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        return $response->json('data');
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentPayload(): array
    {
        return [
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
        ];
    }
}
