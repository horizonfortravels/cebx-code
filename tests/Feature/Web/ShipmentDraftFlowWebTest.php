<?php

namespace Tests\Feature\Web;

use App\Models\Account;
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
            ->assertSee('بدء طلب شحنة للحساب الفردي')
            ->assertSee('مراحل هذا التدفق')
            ->assertSee('جاهز للانتقال لاحقًا إلى التسعير');
    }

    public function test_invalid_b2c_submission_shows_validation_guidance(): void
    {
        $user = $this->createPortalUser('individual', $this->shipmentRequestPermissions());

        $response = $this->actingAs($user, 'web')
            ->post('/b2c/shipments', $this->shipmentPayload([
                'sender_postal_code' => null,
                'recipient_postal_code' => null,
            ]));

        $response->assertRedirect();
        $this->assertStringContainsString('/b2c/shipments/create', (string) $response->headers->get('Location'));

        $this->followingRedirects()
            ->actingAs($user, 'web')
            ->post('/b2c/shipments', $this->shipmentPayload([
                'sender_postal_code' => null,
                'recipient_postal_code' => null,
            ]))
            ->assertOk()
            ->assertSee('أخطاء قابلة للتصحيح قبل التسعير')
            ->assertSee('الخطوة التالية');
    }

    public function test_b2b_submission_shows_kyc_guidance_when_blocked(): void
    {
        $user = $this->createPortalUser('organization', $this->shipmentRequestPermissions());

        $this->followingRedirects()
            ->actingAs($user, 'web')
            ->post('/b2b/shipments', $this->shipmentPayload())
            ->assertOk()
            ->assertSee('حالة التحقق')
            ->assertSee('إكمال التحقق من الهوية')
            ->assertSee('موقوف بسبب التحقق أو القيود');
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

    private function createPortalUser(string $accountType, array $permissions): User
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

        $this->grantTenantPermissions($user, $permissions, 'shipment_web_flow_' . $accountType);

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
}
