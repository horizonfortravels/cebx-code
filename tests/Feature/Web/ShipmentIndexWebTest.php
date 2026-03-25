<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentIndexWebTest extends TestCase
{
    public function test_b2c_shipments_page_renders_clean_arabic_copy(): void
    {
        $user = $this->createShipmentIndexUser('individual', 'individual');
        $this->createShipmentForUser($user, [
            'reference_number' => 'B2C-INDEX-01',
            'recipient_city' => 'New York',
            'recipient_country' => 'US',
        ]);

        $this->actingAs($user, 'web')
            ->get('/b2c/shipments')
            ->assertOk()
            ->assertSee('مساحة الشحنات الفردية')
            ->assertSee('سجل الشحنات')
            ->assertSee('بدء طلب شحنة')
            ->assertDontSee('ط§ظ„ط´ط­ظ†ط§طھ')
            ->assertDontSee('ط·آ');
    }

    public function test_b2b_shipments_page_renders_clean_arabic_copy(): void
    {
        $user = $this->createShipmentIndexUser('organization', 'organization_owner');
        $this->createShipmentForUser($user, [
            'reference_number' => 'B2B-INDEX-01',
            'recipient_name' => 'Recipient One',
        ]);

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments')
            ->assertOk()
            ->assertSee('لوحة تشغيل الشحنات')
            ->assertSee('سجل الشحنات')
            ->assertSee('بدء طلب شحنة لفريقك')
            ->assertDontSee('ط§ظ„ط´ط­ظ†ط§طھ')
            ->assertDontSee('ط·آ');
    }

    public function test_b2b_shipments_index_paginates_and_opens_older_shipments(): void
    {
        $user = $this->createShipmentIndexUser('organization', 'organization_owner');

        foreach (range(1, 12) as $index) {
            $this->createShipmentForUser($user, [
                'reference_number' => 'BROWSE-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'recipient_name' => 'Recipient ' . $index,
                'created_at' => now()->subDays(12 - $index),
                'updated_at' => now()->subDays(12 - $index),
            ]);
        }

        $olderShipment = Shipment::query()
            ->where('account_id', (string) $user->account_id)
            ->where('reference_number', 'BROWSE-01')
            ->firstOrFail();

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments')
            ->assertOk()
            ->assertSee('BROWSE-12')
            ->assertDontSee('BROWSE-01')
            ->assertSee('التالي');

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments?page=2')
            ->assertOk()
            ->assertSee('BROWSE-01')
            ->assertSee('/b2b/shipments/' . $olderShipment->id, false);

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $olderShipment->id)
            ->assertOk()
            ->assertSee('BROWSE-01');
    }

    public function test_cross_tenant_shipment_list_and_detail_do_not_leak(): void
    {
        $userA = $this->createShipmentIndexUser('organization', 'organization_owner');
        $userB = $this->createShipmentIndexUser('organization', 'organization_owner');
        $shipmentB = $this->createShipmentForUser($userB, [
            'reference_number' => 'TENANT-B-01',
        ]);

        $this->actingAs($userA, 'web')
            ->get('/b2b/shipments')
            ->assertOk()
            ->assertDontSee('TENANT-B-01');

        $this->actingAs($userA, 'web')
            ->get('/b2b/shipments/' . $shipmentB->id)
            ->assertNotFound();
    }

    private function createShipmentIndexUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'B2C Shipments ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'B2B Shipments ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, ['shipments.read', 'shipments.create', 'tracking.read'], 'shipment_index_web_' . $persona . '_' . $accountType);

        return $user;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createShipmentForUser(User $user, array $attributes = []): Shipment
    {
        return Shipment::factory()->create(array_merge([
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'status' => Shipment::STATUS_DRAFT,
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
            'recipient_city' => 'Riyadh',
            'recipient_country' => 'SA',
        ], $attributes));
    }
}
