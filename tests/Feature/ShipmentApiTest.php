<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Address;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ShipmentApiTest — Integration tests for SH module API endpoints
 */
class ShipmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;
    protected User $owner;
    protected User $manager;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['status' => 'active', 'kyc_status' => 'verified']);
        $this->owner   = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => true]);
        $this->manager = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => false]);
        $this->member  = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => false]);

        $this->manager->grantPermission('shipments:manage');
        $this->manager->grantPermission('shipments:print_label');
    }

    private function shipmentPayload(array $overrides = []): array
    {
        return array_merge([
            'sender_name'         => 'مستودع الرياض',
            'sender_phone'        => '+966501234567',
            'sender_address_1'    => 'شارع الملك فهد',
            'sender_city'         => 'الرياض',
            'sender_country'      => 'SA',
            'recipient_name'      => 'أحمد محمد',
            'recipient_phone'     => '+966509876543',
            'recipient_address_1' => 'حي الحمراء',
            'recipient_city'      => 'جدة',
            'recipient_country'   => 'SA',
            'parcels' => [
                ['weight' => 2.5, 'length' => 30, 'width' => 20, 'height' => 15],
            ],
        ], $overrides);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/shipments — FR-SH-001
    // ═══════════════════════════════════════════════════════════════

    public function test_create_shipment_as_owner(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/shipments', $this->shipmentPayload());

        $response->assertStatus(201)
                 ->assertJsonPath('data.status', 'draft')
                 ->assertJsonPath('data.source', 'direct')
                 ->assertJsonPath('data.recipient_name', 'أحمد محمد');
    }

    public function test_create_shipment_as_manager(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/shipments', $this->shipmentPayload())
             ->assertStatus(201);
    }

    public function test_create_shipment_as_member_forbidden(): void
    {
        $this->actingAs($this->member)->postJson('/api/v1/shipments', $this->shipmentPayload())
             ->assertStatus(422);
    }

    public function test_create_shipment_validation_errors(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/shipments', []);
        $response->assertStatus(422);
    }

    public function test_create_shipment_with_multiple_parcels(): void
    {
        $payload = $this->shipmentPayload([
            'parcels' => [
                ['weight' => 1.5, 'length' => 20, 'width' => 15, 'height' => 10],
                ['weight' => 3.0, 'length' => 40, 'width' => 30, 'height' => 25],
            ],
        ]);

        $response = $this->actingAs($this->owner)->postJson('/api/v1/shipments', $payload);
        $response->assertStatus(201)
                 ->assertJsonPath('data.parcels_count', 2);
    }

    public function test_create_cod_shipment(): void
    {
        $payload = $this->shipmentPayload(['cod_amount' => 250.00]);

        $this->actingAs($this->owner)->postJson('/api/v1/shipments', $payload)
             ->assertStatus(201)
             ->assertJsonPath('data.is_cod', true)
             ->assertJsonPath('data.cod_amount', '250.00');
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/shipments/from-order/{id} — FR-SH-002
    // ═══════════════════════════════════════════════════════════════

    public function test_create_shipment_from_order(): void
    {
        $store = Store::factory()->create(['account_id' => $this->account->id]);
        Address::factory()->create([
            'account_id' => $this->account->id, 'is_default_sender' => true,
            'contact_name' => 'مستودع', 'phone' => '+966500000000',
            'address_line_1' => 'عنوان', 'city' => 'الرياض', 'country' => 'SA',
        ]);
        $order = Order::factory()->create([
            'account_id' => $this->account->id, 'store_id' => $store->id,
            'status' => Order::STATUS_READY,
            'shipping_name' => 'عميل', 'shipping_phone' => '+966501111111',
            'shipping_address_line_1' => 'عنوان', 'shipping_city' => 'جدة', 'shipping_country' => 'SA',
        ]);

        $this->actingAs($this->owner)->postJson("/api/v1/shipments/from-order/{$order->id}")
             ->assertStatus(201)
             ->assertJsonPath('data.source', 'order');
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/shipments/{id}/validate — FR-SH-005
    // ═══════════════════════════════════════════════════════════════

    public function test_validate_shipment(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/shipments', $this->shipmentPayload());
        $id = $response->json('data.id');

        $this->actingAs($this->owner)->postJson("/api/v1/shipments/{$id}/validate")
             ->assertOk()
             ->assertJsonPath('data.status', 'validated');
    }

    // ═══════════════════════════════════════════════════════════════
    // PUT /api/v1/shipments/{id}/status — FR-SH-006
    // ═══════════════════════════════════════════════════════════════

    public function test_update_shipment_status(): void
    {
        $shipment = Shipment::factory()->validated()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->putJson("/api/v1/shipments/{$shipment->id}/status", [
            'status' => 'rated', 'reason' => 'تم التسعير',
        ])->assertOk()->assertJsonPath('data.status', 'rated');
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $shipment = Shipment::factory()->delivered()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->putJson("/api/v1/shipments/{$shipment->id}/status", [
            'status' => 'draft',
        ])->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/shipments/{id}/cancel — FR-SH-007
    // ═══════════════════════════════════════════════════════════════

    public function test_cancel_shipment(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->postJson("/api/v1/shipments/{$shipment->id}/cancel", [
            'reason' => 'عنوان خاطئ',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_delivered(): void
    {
        $shipment = Shipment::factory()->delivered()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->postJson("/api/v1/shipments/{$shipment->id}/cancel")
             ->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /api/v1/shipments/{id}/label — FR-SH-008
    // ═══════════════════════════════════════════════════════════════

    public function test_get_label(): void
    {
        $shipment = Shipment::factory()->purchased()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->getJson("/api/v1/shipments/{$shipment->id}/label")
             ->assertOk()
             ->assertJsonStructure(['data' => ['label_url', 'label_format', 'print_count']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /api/v1/shipments — FR-SH-009
    // ═══════════════════════════════════════════════════════════════

    public function test_list_shipments(): void
    {
        Shipment::factory()->count(5)->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->getJson('/api/v1/shipments')
             ->assertOk()
             ->assertJsonPath('data.total', 5);
    }

    public function test_filter_by_status(): void
    {
        Shipment::factory()->count(3)->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);
        Shipment::factory()->cancelled()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->getJson('/api/v1/shipments?status=cancelled')
             ->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_search_by_tracking(): void
    {
        Shipment::factory()->purchased()->create([
            'account_id' => $this->account->id,
            'tracking_number' => 'TESTTRACK123',
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->getJson('/api/v1/shipments?search=TESTTRACK')
             ->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /api/v1/shipments/{id} — Single Shipment
    // ═══════════════════════════════════════════════════════════════

    public function test_show_shipment(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->getJson("/api/v1/shipments/{$shipment->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $shipment->id);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/shipments/bulk — FR-SH-010
    // ═══════════════════════════════════════════════════════════════

    public function test_bulk_create(): void
    {
        $store = Store::factory()->create(['account_id' => $this->account->id]);
        Address::factory()->create([
            'account_id' => $this->account->id, 'is_default_sender' => true,
            'contact_name' => 'مستودع', 'phone' => '+966500000000',
            'address_line_1' => 'عنوان', 'city' => 'الرياض', 'country' => 'SA',
        ]);
        $orders = Order::factory()->count(2)->create([
            'account_id' => $this->account->id, 'store_id' => $store->id,
            'status' => Order::STATUS_READY,
            'shipping_name' => 'عميل', 'shipping_phone' => '+966501111111',
            'shipping_address_line_1' => 'عنوان', 'shipping_city' => 'جدة', 'shipping_country' => 'SA',
        ]);

        $this->actingAs($this->owner)->postJson('/api/v1/shipments/bulk', [
            'order_ids' => $orders->pluck('id')->toArray(),
        ])->assertOk()->assertJsonPath('data.success', 2);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/shipments/{id}/return — FR-SH-016
    // ═══════════════════════════════════════════════════════════════

    public function test_create_return_shipment(): void
    {
        $shipment = Shipment::factory()->delivered()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
            'sender_name' => 'أصلي', 'recipient_name' => 'مستلم',
            'sender_phone' => '+966501111111', 'recipient_phone' => '+966502222222',
            'sender_address_1' => 'عنوان1', 'recipient_address_1' => 'عنوان2',
            'sender_city' => 'الرياض', 'recipient_city' => 'جدة',
            'sender_country' => 'SA', 'recipient_country' => 'SA',
        ]);

        $this->actingAs($this->owner)->postJson("/api/v1/shipments/{$shipment->id}/return")
             ->assertStatus(201)
             ->assertJsonPath('data.is_return', true)
             ->assertJsonPath('data.source', 'return');
    }

    // ═══════════════════════════════════════════════════════════════
    // Parcels — FR-SH-003
    // ═══════════════════════════════════════════════════════════════

    public function test_add_parcel(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/shipments', $this->shipmentPayload());
        $id = $response->json('data.id');

        $this->actingAs($this->owner)->postJson("/api/v1/shipments/{$id}/parcels", [
            'weight' => 1.5, 'length' => 20, 'width' => 15, 'height' => 10,
        ])->assertStatus(201);
    }

    // ═══════════════════════════════════════════════════════════════
    // Address Book — FR-SH-004
    // ═══════════════════════════════════════════════════════════════

    public function test_list_addresses(): void
    {
        Address::factory()->count(3)->create(['account_id' => $this->account->id]);

        $this->actingAs($this->owner)->getJson('/api/v1/addresses')
             ->assertOk()
             ->assertJsonCount(3, 'data');
    }

    public function test_create_address(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/addresses', [
            'contact_name'   => 'مستودع جديد',
            'phone'          => '+966501234567',
            'address_line_1' => 'شارع جديد',
            'city'           => 'الدمام',
            'country'        => 'SA',
        ])->assertStatus(201);
    }

    public function test_delete_address(): void
    {
        $address = Address::factory()->create(['account_id' => $this->account->id]);

        $this->actingAs($this->owner)->deleteJson("/api/v1/addresses/{$address->id}")
             ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // Statistics
    // ═══════════════════════════════════════════════════════════════

    public function test_shipment_stats(): void
    {
        Shipment::factory()->count(2)->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->getJson('/api/v1/shipments/stats')
             ->assertOk()
             ->assertJsonPath('data.total', 2);
    }
}
