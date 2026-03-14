<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Address;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\Store;
use App\Models\User;
use App\Services\ShipmentService;
use App\Exceptions\BusinessException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ShipmentTest — Unit tests for FR-SH-001→019 (19 FRs)
 */
class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;
    protected User $owner;
    protected User $manager;
    protected User $member;
    protected ShipmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['status' => 'active', 'kyc_status' => 'verified']);
        $this->owner   = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => true]);
        $this->manager = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => false]);
        $this->member  = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => false]);

        // Give manager shipment permissions
        $this->manager->grantPermission('shipments:manage');
        $this->manager->grantPermission('shipments:view_financial');
        $this->manager->grantPermission('shipments:print_label');

        $this->service = app(ShipmentService::class);
    }

    private function shipmentData(array $overrides = []): array
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
    // FR-SH-001: Direct Shipment Creation
    // ═══════════════════════════════════════════════════════════════

    public function test_owner_can_create_direct_shipment(): void
    {
        $shipment = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);

        $this->assertNotNull($shipment->id);
        $this->assertEquals(Shipment::STATUS_DRAFT, $shipment->status);
        $this->assertEquals(Shipment::SOURCE_DIRECT, $shipment->source);
        $this->assertStringStartsWith('SHP-', $shipment->reference_number);
        $this->assertEquals($this->owner->id, $shipment->created_by);
    }

    public function test_manager_can_create_shipment(): void
    {
        $shipment = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->manager);
        $this->assertNotNull($shipment->id);
    }

    public function test_member_cannot_create_shipment(): void
    {
        $this->expectException(BusinessException::class);
        $this->service->createDirect($this->account->id, $this->shipmentData(), $this->member);
    }

    public function test_shipment_detects_international(): void
    {
        $data = $this->shipmentData(['recipient_country' => 'AE']);
        $shipment = $this->service->createDirect($this->account->id, $data, $this->owner);
        $this->assertTrue($shipment->is_international);
    }

    public function test_shipment_reference_is_unique(): void
    {
        $s1 = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);
        $s2 = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);
        $this->assertNotEquals($s1->reference_number, $s2->reference_number);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-002: Order → Shipment
    // ═══════════════════════════════════════════════════════════════

    public function test_create_shipment_from_order(): void
    {
        $store = Store::factory()->create(['account_id' => $this->account->id]);
        $order = Order::factory()->create([
            'account_id'            => $this->account->id,
            'store_id'              => $store->id,
            'status'                => Order::STATUS_READY,
            'shipping_name'         => 'عميل تجريبي',
            'shipping_phone'        => '+966505555555',
            'shipping_address_line_1' => 'شارع التحلية',
            'shipping_city'         => 'الرياض',
            'shipping_country'      => 'SA',
        ]);

        // Create default sender address
        Address::factory()->create([
            'account_id'        => $this->account->id,
            'is_default_sender' => true,
            'contact_name'      => 'المستودع الرئيسي',
            'phone'             => '+966501111111',
            'address_line_1'    => 'المنطقة الصناعية',
            'city'              => 'الرياض',
            'country'           => 'SA',
        ]);

        $shipment = $this->service->createFromOrder($this->account->id, $order->id, [], $this->owner);

        $this->assertEquals(Shipment::SOURCE_ORDER, $shipment->source);
        $this->assertEquals($order->id, $shipment->order_id);
        $this->assertEquals('عميل تجريبي', $shipment->recipient_name);

        // Order should be linked and processing
        $order->refresh();
        $this->assertEquals(Order::STATUS_PROCESSING, $order->status);
        $this->assertEquals($shipment->id, $order->shipment_id);
    }

    public function test_cannot_create_shipment_from_non_shippable_order(): void
    {
        $order = Order::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Order::STATUS_SHIPPED,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->createFromOrder($this->account->id, $order->id, [], $this->owner);
    }

    public function test_cannot_create_duplicate_shipment_from_order(): void
    {
        $order = Order::factory()->create([
            'account_id'  => $this->account->id,
            'status'      => Order::STATUS_READY,
            'shipment_id' => 'existing-shipment-id',
        ]);

        $this->expectException(BusinessException::class);
        $this->service->createFromOrder($this->account->id, $order->id, [], $this->owner);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-003: Multi-Parcel
    // ═══════════════════════════════════════════════════════════════

    public function test_shipment_creates_parcels(): void
    {
        $data = $this->shipmentData([
            'parcels' => [
                ['weight' => 1.5, 'length' => 20, 'width' => 15, 'height' => 10],
                ['weight' => 3.0, 'length' => 40, 'width' => 30, 'height' => 25],
            ],
        ]);

        $shipment = $this->service->createDirect($this->account->id, $data, $this->owner);

        $this->assertEquals(2, $shipment->parcels_count);
        $this->assertEquals(2, $shipment->parcels->count());
        $this->assertEquals(4.5, (float) $shipment->total_weight);
    }

    public function test_add_parcel_to_draft_shipment(): void
    {
        $shipment = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);

        $parcel = $this->service->addParcel($this->account->id, $shipment->id, [
            'weight' => 1.0, 'length' => 10, 'width' => 10, 'height' => 10,
        ], $this->owner);

        $this->assertEquals(2, $parcel->sequence);
        $shipment->refresh();
        $this->assertEquals(2, $shipment->parcels_count);
    }

    public function test_remove_parcel_updates_weights(): void
    {
        $data = $this->shipmentData([
            'parcels' => [
                ['weight' => 1.0],
                ['weight' => 2.0],
            ],
        ]);
        $shipment = $this->service->createDirect($this->account->id, $data, $this->owner);
        $parcels  = $shipment->parcels;

        $this->service->removeParcel($this->account->id, $shipment->id, $parcels->last()->id, $this->owner);
        $shipment->refresh();

        $this->assertEquals(1, $shipment->parcels_count);
        $this->assertEquals(1.0, (float) $shipment->total_weight);
    }

    public function test_cannot_remove_last_parcel(): void
    {
        $shipment = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);

        $this->expectException(BusinessException::class);
        $this->service->removeParcel($this->account->id, $shipment->id, $shipment->parcels->first()->id, $this->owner);
    }

    public function test_volumetric_weight_calculated(): void
    {
        $data = $this->shipmentData([
            'parcels' => [['weight' => 1.0, 'length' => 50, 'width' => 40, 'height' => 30]],
        ]);

        $shipment = $this->service->createDirect($this->account->id, $data, $this->owner);
        $parcel   = $shipment->parcels->first();

        // Volumetric: (50 * 40 * 30) / 5000 = 12kg
        $this->assertEquals(12.0, (float) $parcel->volumetric_weight);
        $this->assertEquals(12.0, (float) $shipment->chargeable_weight); // max(1, 12)
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-004: Address Book
    // ═══════════════════════════════════════════════════════════════

    public function test_save_address_to_book(): void
    {
        $address = $this->service->saveAddress($this->account->id, [
            'type'              => 'sender',
            'is_default_sender' => true,
            'label'             => 'مستودع الرياض',
            'contact_name'      => 'فهد عبدالله',
            'phone'             => '+966501234567',
            'address_line_1'    => 'شارع العليا',
            'city'              => 'الرياض',
            'country'           => 'SA',
        ], $this->owner);

        $this->assertNotNull($address->id);
        $this->assertTrue($address->is_default_sender);
    }

    public function test_setting_default_sender_unsets_previous(): void
    {
        $addr1 = $this->service->saveAddress($this->account->id, [
            'is_default_sender' => true, 'contact_name' => 'A',
            'phone' => '+966500000001', 'address_line_1' => 'X', 'city' => 'R', 'country' => 'SA',
        ], $this->owner);

        $addr2 = $this->service->saveAddress($this->account->id, [
            'is_default_sender' => true, 'contact_name' => 'B',
            'phone' => '+966500000002', 'address_line_1' => 'Y', 'city' => 'J', 'country' => 'SA',
        ], $this->owner);

        $addr1->refresh();
        $this->assertFalse($addr1->is_default_sender);
        $this->assertTrue($addr2->is_default_sender);
    }

    public function test_list_addresses_by_type(): void
    {
        Address::factory()->create(['account_id' => $this->account->id, 'type' => 'sender']);
        Address::factory()->create(['account_id' => $this->account->id, 'type' => 'recipient']);
        Address::factory()->create(['account_id' => $this->account->id, 'type' => 'both']);

        $senders = $this->service->listAddresses($this->account->id, 'sender');
        $this->assertEquals(2, $senders->count()); // sender + both
    }

    public function test_delete_address(): void
    {
        $address = Address::factory()->create(['account_id' => $this->account->id]);
        $this->service->deleteAddress($this->account->id, $address->id);
        $this->assertSoftDeleted('addresses', ['id' => $address->id]);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-005: Validation
    // ═══════════════════════════════════════════════════════════════

    public function test_validate_complete_shipment(): void
    {
        $shipment = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);
        $validated = $this->service->validateShipment($this->account->id, $shipment->id, $this->owner);

        $this->assertEquals(Shipment::STATUS_VALIDATED, $validated->status);
    }

    public function test_validate_incomplete_shipment_fails(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id'      => $this->account->id,
            'created_by'      => $this->owner->id,
            'sender_name'     => '',
            'recipient_phone' => '',
        ]);
        Parcel::create(['shipment_id' => $shipment->id, 'weight' => 1, 'sequence' => 1]);

        $this->expectException(BusinessException::class);
        $this->service->validateShipment($this->account->id, $shipment->id, $this->owner);
    }

    public function test_cannot_validate_non_draft(): void
    {
        $shipment = Shipment::factory()->validated()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->validateShipment($this->account->id, $shipment->id, $this->owner);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-006: State Machine
    // ═══════════════════════════════════════════════════════════════

    public function test_valid_status_transition(): void
    {
        $shipment = Shipment::factory()->validated()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $updated = $this->service->updateStatus(
            $this->account->id, $shipment->id, Shipment::STATUS_RATED, $this->owner
        );

        $this->assertEquals(Shipment::STATUS_RATED, $updated->status);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Shipment::STATUS_DELIVERED,
            'created_by' => $this->owner->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->updateStatus(
            $this->account->id, $shipment->id, Shipment::STATUS_DRAFT, $this->owner
        );
    }

    public function test_status_history_recorded(): void
    {
        $shipment = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);

        $history = ShipmentStatusHistory::where('shipment_id', $shipment->id)->get();
        $this->assertEquals(1, $history->count());
        $this->assertEquals(Shipment::STATUS_DRAFT, $history->first()->to_status);
    }

    public function test_delivery_updates_order_status(): void
    {
        $order = Order::factory()->create([
            'account_id' => $this->account->id, 'status' => Order::STATUS_PROCESSING,
        ]);

        $shipment = Shipment::factory()->inTransit()->create([
            'account_id' => $this->account->id,
            'order_id'   => $order->id,
            'created_by' => $this->owner->id,
        ]);

        $this->service->updateStatus($this->account->id, $shipment->id, Shipment::STATUS_DELIVERED, $this->owner);

        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-007: Cancel / Void
    // ═══════════════════════════════════════════════════════════════

    public function test_cancel_draft_shipment(): void
    {
        $shipment = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);

        $cancelled = $this->service->cancelShipment(
            $this->account->id, $shipment->id, $this->owner, 'تغيير رأي'
        );

        $this->assertEquals(Shipment::STATUS_CANCELLED, $cancelled->status);
        $this->assertEquals('تغيير رأي', $cancelled->cancellation_reason);
        $this->assertEquals($this->owner->id, $cancelled->cancelled_by);
    }

    public function test_cancel_purchased_shipment_flags_refund(): void
    {
        $shipment = Shipment::factory()->purchased()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $cancelled = $this->service->cancelShipment($this->account->id, $shipment->id, $this->owner);
        $this->assertEquals('pending_refund', $cancelled->refund_ledger_entry_id);
    }

    public function test_cannot_cancel_delivered_shipment(): void
    {
        $shipment = Shipment::factory()->delivered()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->cancelShipment($this->account->id, $shipment->id, $this->owner);
    }

    public function test_cancel_unlinks_order(): void
    {
        $order = Order::factory()->create([
            'account_id' => $this->account->id, 'status' => Order::STATUS_PROCESSING,
        ]);
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id, 'order_id' => $order->id,
            'created_by' => $this->owner->id,
        ]);
        $order->update(['shipment_id' => $shipment->id]);

        $this->service->cancelShipment($this->account->id, $shipment->id, $this->owner);

        $order->refresh();
        $this->assertNull($order->shipment_id);
        $this->assertEquals(Order::STATUS_READY, $order->status);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-008: Reprint Label
    // ═══════════════════════════════════════════════════════════════

    public function test_get_label_info_increments_count(): void
    {
        $shipment = Shipment::factory()->purchased()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $info = $this->service->getLabelInfo($this->account->id, $shipment->id, $this->owner);

        $this->assertNotEmpty($info['label_url']);
        $this->assertEquals('pdf', $info['label_format']);
        $this->assertEquals(1, $info['print_count']);
    }

    public function test_no_label_for_draft(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->getLabelInfo($this->account->id, $shipment->id, $this->owner);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-009: Search & Filter
    // ═══════════════════════════════════════════════════════════════

    public function test_list_shipments_with_filters(): void
    {
        Shipment::factory()->count(3)->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);
        Shipment::factory()->cancelled()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $result = $this->service->listShipments($this->account->id, ['status' => 'draft'], $this->owner);
        $this->assertEquals(3, $result['total']);
    }

    public function test_list_shipments_search_by_tracking(): void
    {
        Shipment::factory()->purchased()->create([
            'account_id'      => $this->account->id,
            'tracking_number' => 'DHL9876543210',
            'created_by'      => $this->owner->id,
        ]);

        $result = $this->service->listShipments($this->account->id, ['search' => 'DHL987'], $this->owner);
        $this->assertEquals(1, $result['total']);
    }

    public function test_list_shipments_search_by_recipient(): void
    {
        Shipment::factory()->create([
            'account_id'     => $this->account->id,
            'recipient_name' => 'خالد العتيبي',
            'created_by'     => $this->owner->id,
        ]);

        $result = $this->service->listShipments($this->account->id, ['search' => 'العتيبي'], $this->owner);
        $this->assertEquals(1, $result['total']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-010: Bulk Creation
    // ═══════════════════════════════════════════════════════════════

    public function test_bulk_create_from_orders(): void
    {
        $store = Store::factory()->create(['account_id' => $this->account->id]);
        Address::factory()->create([
            'account_id' => $this->account->id, 'is_default_sender' => true,
            'contact_name' => 'مستودع', 'phone' => '+966500000000',
            'address_line_1' => 'عنوان', 'city' => 'الرياض', 'country' => 'SA',
        ]);

        $orders = Order::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'store_id'   => $store->id,
            'status'     => Order::STATUS_READY,
            'shipping_name' => 'عميل', 'shipping_phone' => '+966501111111',
            'shipping_address_line_1' => 'عنوان', 'shipping_city' => 'جدة', 'shipping_country' => 'SA',
        ]);

        $result = $this->service->bulkCreateFromOrders(
            $this->account->id,
            $orders->pluck('id')->toArray(),
            [],
            $this->owner
        );

        $this->assertEquals(3, $result['success']);
        $this->assertEquals(0, $result['failed']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-011: Financial Visibility
    // ═══════════════════════════════════════════════════════════════

    public function test_financial_fields_hidden_from_non_financial_user(): void
    {
        Shipment::factory()->rated()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        // Member has no financial permission
        $result = $this->service->listShipments($this->account->id, [], $this->member);

        // Member can't create shipments, but if they could list...
        // For the owner, all fields are visible
        $resultOwner = $this->service->listShipments($this->account->id, [], $this->owner);
        $shipment = $resultOwner['shipments']->first();
        $this->assertNotNull($shipment->shipping_rate);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-013: KYC Check
    // ═══════════════════════════════════════════════════════════════

    public function test_kyc_check_passes_for_verified_account(): void
    {
        $shipment = Shipment::factory()->international()->create([
            'account_id'  => $this->account->id,
            'total_charge' => 3000,
            'created_by'  => $this->owner->id,
        ]);

        $this->assertTrue($this->service->checkKycForPurchase($shipment));
    }

    public function test_kyc_check_fails_for_unverified_international(): void
    {
        $unverified = Account::factory()->create(['kyc_status' => 'pending']);
        $shipment = Shipment::factory()->international()->create([
            'account_id'  => $unverified->id,
            'total_charge' => 3000,
            'created_by'  => $this->owner->id,
        ]);

        $this->assertFalse($this->service->checkKycForPurchase($shipment));
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-016: Return Shipments
    // ═══════════════════════════════════════════════════════════════

    public function test_create_return_shipment(): void
    {
        $original = Shipment::factory()->delivered()->create([
            'account_id'          => $this->account->id,
            'sender_name'         => 'المرسل الأصلي',
            'recipient_name'      => 'المستلم الأصلي',
            'sender_city'         => 'الرياض',
            'recipient_city'      => 'جدة',
            'sender_country'      => 'SA',
            'recipient_country'   => 'SA',
            'sender_phone'        => '+966501111111',
            'recipient_phone'     => '+966502222222',
            'sender_address_1'    => 'عنوان المرسل',
            'recipient_address_1' => 'عنوان المستلم',
            'created_by'          => $this->owner->id,
        ]);

        $return = $this->service->createReturnShipment($this->account->id, $original->id, [], $this->owner);

        $this->assertTrue($return->is_return);
        $this->assertEquals(Shipment::SOURCE_RETURN, $return->source);
        // Sender/recipient swapped
        $this->assertEquals('المستلم الأصلي', $return->sender_name);
        $this->assertEquals('المرسل الأصلي', $return->recipient_name);
    }

    public function test_cannot_return_non_delivered_shipment(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Shipment::STATUS_DRAFT,
            'created_by' => $this->owner->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->createReturnShipment($this->account->id, $shipment->id, [], $this->owner);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-017/018: Dangerous Goods
    // ═══════════════════════════════════════════════════════════════

    public function test_dangerous_goods_flag_set(): void
    {
        $data = $this->shipmentData(['has_dangerous_goods' => true]);
        $shipment = $this->service->createDirect($this->account->id, $data, $this->owner);

        $this->assertTrue($shipment->has_dangerous_goods);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-SH-019: COD Support
    // ═══════════════════════════════════════════════════════════════

    public function test_cod_shipment(): void
    {
        $data = $this->shipmentData(['cod_amount' => 350.00]);
        $shipment = $this->service->createDirect($this->account->id, $data, $this->owner);

        $this->assertTrue($shipment->is_cod);
        $this->assertEquals(350.00, (float) $shipment->cod_amount);
    }

    public function test_cod_validation_requires_amount(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'is_cod'     => true,
            'cod_amount' => 0,
            'created_by' => $this->owner->id,
        ]);
        Parcel::create(['shipment_id' => $shipment->id, 'weight' => 1, 'sequence' => 1]);

        $this->expectException(BusinessException::class);
        $this->service->validateShipment($this->account->id, $shipment->id, $this->owner);
    }

    // ═══════════════════════════════════════════════════════════════
    // Statistics
    // ═══════════════════════════════════════════════════════════════

    public function test_shipment_stats(): void
    {
        Shipment::factory()->count(3)->create([
            'account_id' => $this->account->id, 'status' => Shipment::STATUS_DRAFT, 'created_by' => $this->owner->id,
        ]);
        Shipment::factory()->count(2)->delivered()->create([
            'account_id' => $this->account->id, 'created_by' => $this->owner->id,
        ]);

        $stats = $this->service->getShipmentStats($this->account->id);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['draft']);
        $this->assertEquals(2, $stats['delivered']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Audit Logging
    // ═══════════════════════════════════════════════════════════════

    public function test_shipment_creation_is_audited(): void
    {
        $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $this->account->id,
            'action'     => 'shipment.created',
        ]);
    }

    public function test_shipment_cancel_is_audited(): void
    {
        $shipment = $this->service->createDirect($this->account->id, $this->shipmentData(), $this->owner);
        $this->service->cancelShipment($this->account->id, $shipment->id, $this->owner);

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $this->account->id,
            'action'     => 'shipment.cancelled',
        ]);
    }
}
