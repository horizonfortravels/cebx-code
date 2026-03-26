<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\CarrierDocument;
use App\Models\CarrierError;
use App\Models\CarrierShipment;
use App\Models\ContentDeclaration;
use App\Models\Parcel;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Models\WalletHold;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedexShipmentCreateApiTest extends TestCase
{
    public function test_shipment_without_active_wallet_reservation_is_blocked_before_fedex_call(): void
    {
        $this->configureFedex();

        Http::preventStrayRequests();
        Http::fake();

        $user = $this->createCarrierActor();
        $shipment = $this->createPaymentPendingShipment($user, false);

        $this->postJson('/api/v1/shipments/' . $shipment->id . '/carrier/create', [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_WALLET_RESERVATION_REQUIRED');

        Http::assertNothingSent();
    }

    public function test_payment_pending_shipment_creates_fedex_shipment_and_persists_normalized_fields(): void
    {
        $this->configureFedex();

        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake($this->fedexShipFakeResponses($this->shipSuccessBody()));

        $user = $this->createCarrierActor();
        $shipment = $this->createPaymentPendingShipment($user);

        $this->postJson('/api/v1/shipments/' . $shipment->id . '/carrier/create', [
            'idempotency_key' => 'SHIP-IDEMP-001',
            'correlation_id' => 'REQ-FDX-CREATE-001',
        ], $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.carrier_shipment_id', '794699999999')
            ->assertJsonPath('data.tracking_number', '794699999999')
            ->assertJsonPath('data.awb_number', '794699999999')
            ->assertJsonPath('data.carrier', 'fedex')
            ->assertJsonPath('data.service_code', 'INTERNATIONAL_PRIORITY')
            ->assertJsonPath('data.correlation_id', 'REQ-FDX-CREATE-001')
            ->assertJsonPath('data.idempotency_key', 'SHIP-IDEMP-001');

        $carrierShipment = CarrierShipment::query()->where('shipment_id', (string) $shipment->id)->firstOrFail();

        $this->assertSame('fedex', (string) $carrierShipment->carrier_code);
        $this->assertSame('FedEx', (string) $carrierShipment->carrier_name);
        $this->assertSame('794699999999', (string) $carrierShipment->carrier_shipment_id);
        $this->assertSame('794699999999', (string) $carrierShipment->tracking_number);
        $this->assertSame('794699999999', (string) $carrierShipment->awb_number);
        $this->assertSame(CarrierShipment::STATUS_LABEL_READY, (string) $carrierShipment->status);
        $this->assertSame('INTERNATIONAL_PRIORITY', (string) $carrierShipment->service_code);
        $this->assertSame('REQ-FDX-CREATE-001', (string) $carrierShipment->correlation_id);
        $this->assertSame('SHIP-IDEMP-001', (string) $carrierShipment->idempotency_key);
        $this->assertSame('created', (string) data_get($carrierShipment->carrier_metadata, 'initial_carrier_status'));
        $this->assertSame('captured', (string) data_get($carrierShipment->carrier_metadata, 'wallet_reservation.status'));
        $this->assertSame('captured_on_success', (string) data_get($carrierShipment->carrier_metadata, 'wallet_reservation.lifecycle'));
        $this->assertSame(2, (int) data_get($carrierShipment->carrier_metadata, 'document_count'));

        $freshShipment = $shipment->fresh();
        $this->assertSame(Shipment::STATUS_PURCHASED, (string) $freshShipment->status);
        $this->assertSame((string) $carrierShipment->id, (string) $freshShipment->carrierShipment->id);
        $this->assertSame((string) $freshShipment->balance_reservation_id, (string) $shipment->balance_reservation_id);
        $this->assertSame(number_format(345.15, 2, '.', ''), number_format((float) $freshShipment->reserved_amount, 2, '.', ''));

        if (Schema::hasColumn('shipments', 'tracking_number')) {
            $this->assertSame('794699999999', (string) $freshShipment->tracking_number);
        } else {
            $this->assertSame('794699999999', (string) $freshShipment->carrier_tracking_number);
        }

        $hold = WalletHold::query()->findOrFail((string) $freshShipment->balance_reservation_id);
        $wallet = BillingWallet::query()->findOrFail((string) $hold->wallet_id);

        $this->assertSame(WalletHold::STATUS_CAPTURED, (string) $hold->status);
        $this->assertNotNull($hold->captured_at);
        $this->assertSame('0.00', number_format((float) $wallet->reserved_balance, 2, '.', ''));
        $this->assertSame('654.85', number_format((float) $wallet->available_balance, 2, '.', ''));
        $this->assertSame('345.15', number_format((float) $wallet->total_debited, 2, '.', ''));

        $documents = CarrierDocument::query()
            ->where('shipment_id', (string) $shipment->id)
            ->orderBy('type')
            ->get();

        $this->assertCount(2, $documents);
        $this->assertSame(['commercial_invoice', 'label'], $documents->pluck('type')->sort()->values()->all());
        $this->assertSame(['stored_object', 'url'], $documents->pluck('retrieval_mode')->sort()->values()->all());
        $this->assertNotNull($documents->firstWhere('type', 'label')?->storage_path);
        $this->assertSame('LABEL', data_get($documents->firstWhere('type', 'label')?->carrier_metadata, 'raw.contentType'));
        $this->assertSame('PDF', data_get($documents->firstWhere('type', 'label')?->carrier_metadata, 'raw.docType'));
        Storage::disk('local')->assertExists((string) $documents->firstWhere('type', 'label')?->storage_path);

        $purchaseEvent = ShipmentEvent::query()
            ->where('shipment_id', (string) $shipment->id)
            ->where('event_type', 'shipment.purchased')
            ->first();

        $this->assertNotNull($purchaseEvent);
        $this->assertSame('purchased', (string) $purchaseEvent->normalized_status);
        $this->assertSame('REQ-FDX-CREATE-001', (string) $purchaseEvent->correlation_id);
        $this->assertSame('SHIP-IDEMP-001:shipment.purchased', (string) $purchaseEvent->idempotency_key);

        $event = ShipmentEvent::query()
            ->where('shipment_id', (string) $shipment->id)
            ->where('event_type', 'carrier.documents_available')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('label_ready', (string) $event->normalized_status);
        $this->assertSame('REQ-FDX-CREATE-001', (string) $event->correlation_id);
        $this->assertSame('SHIP-IDEMP-001:carrier.documents_available', (string) $event->idempotency_key);

        Http::assertSentCount(2);
    }

    public function test_repeated_request_with_same_idempotency_key_does_not_duplicate_creation(): void
    {
        $this->configureFedex();

        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake($this->fedexShipFakeResponses($this->shipSuccessBody()));

        $user = $this->createCarrierActor();
        $shipment = $this->createPaymentPendingShipment($user);

        $first = $this->postJson('/api/v1/shipments/' . $shipment->id . '/carrier/create', [
            'idempotency_key' => 'SHIP-IDEMP-RETRY-001',
            'correlation_id' => 'REQ-FDX-CREATE-RETRY-001',
        ], $this->authHeaders($user))->assertCreated()->json('data');

        $second = $this->postJson('/api/v1/shipments/' . $shipment->id . '/carrier/create', [
            'idempotency_key' => 'SHIP-IDEMP-RETRY-001',
            'correlation_id' => 'REQ-FDX-CREATE-RETRY-002',
        ], $this->authHeaders($user))->assertCreated()->json('data');

        $this->assertSame((string) $first['id'], (string) $second['id']);
        $this->assertSame((string) $first['carrier_shipment_id'], (string) $second['carrier_shipment_id']);
        $this->assertSame(1, CarrierShipment::query()->where('shipment_id', (string) $shipment->id)->count());

        $hold = WalletHold::query()->findOrFail((string) $shipment->fresh()->balance_reservation_id);
        $wallet = BillingWallet::query()->findOrFail((string) $hold->wallet_id);

        $this->assertSame(WalletHold::STATUS_CAPTURED, (string) $hold->status);
        $this->assertSame('0.00', number_format((float) $wallet->reserved_balance, 2, '.', ''));
        $this->assertSame('345.15', number_format((float) $wallet->total_debited, 2, '.', ''));
        $this->assertSame(2, CarrierDocument::query()->where('shipment_id', (string) $shipment->id)->count());
        $this->assertSame(2, ShipmentEvent::query()->where('shipment_id', (string) $shipment->id)->count());

        Http::assertSentCount(2);
    }

    public function test_virtualized_response_does_not_break_creation_flow(): void
    {
        $this->configureFedex();

        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake($this->fedexShipFakeResponses($this->shipSuccessBody(true)));

        $user = $this->createCarrierActor();
        $shipment = $this->createPaymentPendingShipment($user);

        $this->postJson('/api/v1/shipments/' . $shipment->id . '/carrier/create', [
            'idempotency_key' => 'SHIP-IDEMP-VIRTUAL-001',
            'correlation_id' => 'REQ-FDX-VIRTUAL-001',
        ], $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.carrier', 'fedex');

        $carrierShipment = CarrierShipment::query()->where('shipment_id', (string) $shipment->id)->firstOrFail();
        $this->assertTrue((bool) data_get($carrierShipment->carrier_metadata, 'virtualized_response'));
        $this->assertSame(CarrierShipment::STATUS_LABEL_READY, (string) $carrierShipment->status);
    }

    public function test_fedex_error_response_is_normalized_and_persisted(): void
    {
        $this->configureFedex();

        Http::preventStrayRequests();
        Http::fake($this->fedexShipFakeResponses([
            'transactionId' => 'fedex-ship-fail-001',
            'errors' => [[
                'code' => 'ACCOUNTNUMBER.REGISTRATION.REQUIRED',
                'message' => 'Please enter a valid 9-digit FedEx account number or register for a new FedEx account number.',
            ]],
        ], 400));

        $user = $this->createCarrierActor();
        $shipment = $this->createPaymentPendingShipment($user);

        $this->postJson('/api/v1/shipments/' . $shipment->id . '/carrier/create', [
            'idempotency_key' => 'SHIP-IDEMP-FAIL-001',
            'correlation_id' => 'REQ-FDX-FAIL-001',
        ], $this->authHeaders($user))
            ->assertStatus(502)
            ->assertJsonPath('error_code', 'ERR_CARRIER_CREATE_FAILED');

        $carrierShipment = CarrierShipment::query()->where('shipment_id', (string) $shipment->id)->firstOrFail();
        $carrierError = CarrierError::query()->where('shipment_id', (string) $shipment->id)->latest()->firstOrFail();

        $this->assertSame(CarrierShipment::STATUS_FAILED, (string) $carrierShipment->status);
        $this->assertSame('fedex', (string) $carrierError->carrier_code);
        $this->assertSame(CarrierError::OP_CREATE_SHIPMENT, (string) $carrierError->operation);
        $this->assertSame(CarrierError::ERR_VALIDATION, (string) $carrierError->internal_code);
        $this->assertSame('ACCOUNTNUMBER.REGISTRATION.REQUIRED', (string) $carrierError->carrier_error_code);
        $this->assertNotEmpty($carrierShipment->request_payload);
        $this->assertNotEmpty($carrierShipment->response_payload);
        $this->assertSame(Shipment::STATUS_FAILED, (string) $shipment->fresh()->status);
        $this->assertSame('active', (string) data_get($carrierShipment->carrier_metadata, 'wallet_reservation.status'));
        $this->assertSame('kept_active_on_failure', (string) data_get($carrierShipment->carrier_metadata, 'wallet_reservation.lifecycle'));

        $hold = WalletHold::query()->findOrFail((string) $shipment->fresh()->balance_reservation_id);
        $wallet = BillingWallet::query()->findOrFail((string) $hold->wallet_id);

        $this->assertSame(WalletHold::STATUS_ACTIVE, (string) $hold->status);
        $this->assertNull($hold->captured_at);
        $this->assertSame('345.15', number_format((float) $wallet->reserved_balance, 2, '.', ''));
        $this->assertSame('1000.00', number_format((float) $wallet->available_balance, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $wallet->total_debited, 2, '.', ''));
    }

    public function test_cross_tenant_carrier_creation_access_returns_404(): void
    {
        $this->configureFedex();

        Http::preventStrayRequests();
        Http::fake();

        $owner = $this->createCarrierActor();
        $otherTenant = $this->createCarrierActor();
        $shipment = $this->createPaymentPendingShipment($owner);

        $this->postJson(
            '/api/v1/shipments/' . $shipment->id . '/carrier/create',
            [],
            $this->authHeaders($otherTenant)
        )->assertNotFound();

        Http::assertNothingSent();
    }

    private function configureFedex(): void
    {
        config()->set('features.carrier_fedex', true);
        config()->set('services.fedex.client_id', 'fedex-test-client');
        config()->set('services.fedex.client_secret', 'fedex-test-secret');
        config()->set('services.fedex.account_number', '123456789');
        config()->set('services.fedex.base_url', 'https://apis-sandbox.fedex.com');
        config()->set('services.fedex.oauth_url', 'https://apis-sandbox.fedex.com/oauth/token');
        config()->set('services.fedex.locale', 'en_US');
        config()->set('services.fedex.carrier_codes', ['FDXE']);
    }

    private function createCarrierActor(): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'FedEx Carrier Org ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, [
            'shipments.create',
            'shipments.update_draft',
            'shipments.manage',
            'rates.read',
            'quotes.read',
            'quotes.manage',
            'dg.read',
            'dg.manage',
            'billing.view',
            'billing.manage',
        ], 'fedex_ship_actor');

        return $user;
    }

    private function createPaymentPendingShipment(User $user, bool $withReservation = true): Shipment
    {
        $attributes = [
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'created_by' => (string) $user->id,
            'status' => Shipment::STATUS_PAYMENT_PENDING,
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

        if (Schema::hasColumn('shipments', 'sender_email')) {
            $attributes['sender_email'] = 'sender@example.test';
        }

        if (Schema::hasColumn('shipments', 'recipient_email')) {
            $attributes['recipient_email'] = 'recipient@example.test';
        }

        if (Schema::hasColumn('shipments', 'recipient_state')) {
            $attributes['recipient_state'] = 'NY';
        }

        if (Schema::hasColumn('shipments', 'sender_state')) {
            $attributes['sender_state'] = 'RI';
            $attributes['sender_city'] = 'Providence';
            $attributes['sender_postal_code'] = '02903';
            $attributes['sender_country'] = 'US';
        }

        if (Schema::hasColumn('shipments', 'total_charge')) {
            $attributes['total_charge'] = 345.15;
        } elseif (Schema::hasColumn('shipments', 'total_cost')) {
            $attributes['total_cost'] = 345.15;
        }

        if (Schema::hasColumn('shipments', 'service_code')) {
            $attributes['service_code'] = 'INTERNATIONAL_PRIORITY';
        }

        if (Schema::hasColumn('shipments', 'service_name')) {
            $attributes['service_name'] = 'FedEx International Priority';
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
            'service_code' => 'INTERNATIONAL_PRIORITY',
            'service_name' => 'FedEx International Priority',
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
            'pricing_breakdown' => ['stage' => 'net_only'],
            'rule_evaluation_log' => [
                'pricing_stage' => 'net_only',
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
            'status' => Shipment::STATUS_PAYMENT_PENDING,
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
            'locale' => 'en',
        ]);

        if ($withReservation) {
            $wallet = BillingWallet::factory()->funded(1000)->create([
                'account_id' => (string) $user->account_id,
                'currency' => 'USD',
                'reserved_balance' => 345.15,
            ]);

            $hold = WalletHold::query()->create([
                'wallet_id' => (string) $wallet->id,
                'account_id' => (string) $user->account_id,
                'amount' => 345.15,
                'currency' => 'USD',
                'shipment_id' => (string) $shipment->id,
                'source' => 'shipment_preflight',
                'status' => WalletHold::STATUS_ACTIVE,
                'idempotency_key' => 'HOLD-' . Str::upper(Str::random(12)),
                'correlation_id' => 'HOLD-CORR-' . Str::upper(Str::random(8)),
                'actor_id' => (string) $user->id,
            ]);

            $shipment->update([
                'balance_reservation_id' => (string) $hold->id,
                'reserved_amount' => 345.15,
            ]);
        }

        return $shipment->fresh([
            'selectedRateOption',
            'rateQuote',
            'balanceReservation',
            'contentDeclaration',
            'parcels',
        ]);
    }

    /**
     * @param array<string, mixed> $shipBody
     * @return array<string, \Closure>
     */
    private function fedexShipFakeResponses(array $shipBody, int $shipStatus = 200): array
    {
        return [
            'https://apis-sandbox.fedex.com/oauth/token' => fn () => Http::response([
                'access_token' => 'fedex-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/ship/v1/shipments' => fn () => Http::response($shipBody, $shipStatus),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipSuccessBody(bool $virtualized = false): array
    {
        $alerts = $virtualized ? [[
            'code' => 'VIRTUAL.RESPONSE',
            'message' => 'This is a Virtual Response.',
            'alertType' => 'NOTE',
        ]] : [];

        return [
            'transactionId' => 'fedex-ship-tx-001',
            'customerTransactionId' => 'REQ-FDX-CREATE-001',
            'output' => [
                'alerts' => $alerts,
                'transactionShipments' => [[
                    'serviceType' => 'INTERNATIONAL_PRIORITY',
                    'serviceName' => 'FedEx International Priority',
                    'masterTrackingNumber' => '794699999999',
                    'alerts' => $alerts,
                    'pieceResponses' => [[
                        'trackingNumber' => '794699999999',
                        'alerts' => $alerts,
                        'packageDocuments' => [[
                            'docType' => 'PDF',
                            'contentType' => 'LABEL',
                            'copiesToPrint' => 1,
                            'encodedLabel' => base64_encode('fake-fedex-label'),
                        ]],
                    ]],
                    'completedShipmentDetail' => [
                        'carrierCode' => 'FDXE',
                        'masterTrackingNumber' => '794699999999',
                    ],
                    'shipmentDocuments' => [[
                        'docType' => 'COMMERCIAL_INVOICE',
                        'url' => 'https://sandbox-docs.fedex.test/invoice-001.pdf',
                    ]],
                ]],
            ],
        ];
    }
}
