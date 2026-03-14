<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\Store;
use App\Models\AuditLog;
use App\Services\StoreService;
use App\Services\AuditService;
use App\Exceptions\BusinessException;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * FR-IAM-009: Multi-Store Management — Unit Tests (22 tests)
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    protected StoreService $service;
    protected Account $account;
    protected User $owner;
    protected User $storeManager;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        AuditService::resetRequestId();
        $this->service = app(StoreService::class);

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => true,
        ]);

        $mgrRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['stores.manage', 'stores.read'],
            'store_manager'
        );
        $this->storeManager = $this->createUserWithRole((string) $this->account->id, (string) $mgrRole->id, [
            'is_owner' => false,
        ]);

        $this->member = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => false,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // Create Store
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_create_store()
    {
        $store = $this->service->createStore(
            $this->account->id,
            ['name' => 'متجري الأول', 'platform' => 'manual'],
            $this->owner
        );

        $this->assertNotNull($store->id);
        $this->assertEquals('متجري الأول', $store->name);
        $this->assertEquals('manual', $store->platform);
        $this->assertTrue($store->is_default); // First store is default
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function first_store_is_always_default()
    {
        $store = $this->service->createStore(
            $this->account->id,
            ['name' => 'Store One'],
            $this->owner
        );

        $this->assertTrue($store->is_default);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function second_store_is_not_default()
    {
        $this->service->createStore($this->account->id, ['name' => 'Store 1'], $this->owner);
        $store2 = $this->service->createStore($this->account->id, ['name' => 'Store 2'], $this->owner);

        $this->assertFalse($store2->is_default);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_manager_can_create_store()
    {
        $store = $this->service->createStore(
            $this->account->id,
            ['name' => 'Manager Store'],
            $this->storeManager
        );

        $this->assertNotNull($store->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_without_permission_cannot_create()
    {
        $this->expectException(BusinessException::class);
        $this->service->createStore(
            $this->account->id,
            ['name' => 'Blocked Store'],
            $this->member
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function create_generates_slug()
    {
        $store = $this->service->createStore(
            $this->account->id,
            ['name' => 'My Test Store'],
            $this->owner
        );

        $this->assertNotEmpty($store->slug);
        $this->assertStringContainsString('my-test-store', $store->slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function create_logs_audit()
    {
        $this->service->createStore(
            $this->account->id,
            ['name' => 'Audit Store'],
            $this->owner
        );

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'store.created')
            ->where('account_id', $this->account->id)
            ->first();

        $this->assertNotNull($log);
    }

    // ─── Duplicate Name ──────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function duplicate_name_within_account_is_rejected()
    {
        $this->service->createStore($this->account->id, ['name' => 'Same Name'], $this->owner);

        $this->expectException(BusinessException::class);
        $this->service->createStore($this->account->id, ['name' => 'Same Name'], $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function same_name_in_different_accounts_is_allowed()
    {
        $otherAccount = Account::factory()->create();
        $otherOwner = User::factory()->create(['account_id' => $otherAccount->id, 'is_owner' => true]);

        $this->service->createStore($this->account->id, ['name' => 'Shared Name'], $this->owner);
        $store2 = $this->service->createStore($otherAccount->id, ['name' => 'Shared Name'], $otherOwner);

        $this->assertNotNull($store2->id);
    }

    // ─── Max Limit ───────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function max_stores_limit_is_enforced()
    {
        // Create max stores
        for ($i = 1; $i <= Store::MAX_STORES_PER_ACCOUNT; $i++) {
            Store::factory()->create([
                'account_id' => $this->account->id,
                'name'       => "Store $i",
                'slug'       => "store-$i",
            ]);
        }

        $this->expectException(BusinessException::class);
        $this->service->createStore($this->account->id, ['name' => 'Overflow'], $this->owner);
    }

    // ═══════════════════════════════════════════════════════════════
    // List & Get Store
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_lists_all_stores_for_account()
    {
        Store::factory()->count(3)->create([
            'account_id' => $this->account->id,
        ]);

        $stores = $this->service->listStores($this->account->id);

        $this->assertCount(3, $stores);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_status()
    {
        Store::factory()->create(['account_id' => $this->account->id, 'status' => 'active', 'name' => 'A']);
        Store::factory()->create(['account_id' => $this->account->id, 'status' => 'inactive', 'name' => 'B']);

        $active = $this->service->listStores($this->account->id, ['status' => 'active']);
        $this->assertCount(1, $active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_platform()
    {
        Store::factory()->create(['account_id' => $this->account->id, 'platform' => 'shopify', 'name' => 'S1']);
        Store::factory()->create(['account_id' => $this->account->id, 'platform' => 'manual', 'name' => 'S2']);

        $shopify = $this->service->listStores($this->account->id, ['platform' => 'shopify']);
        $this->assertCount(1, $shopify);
    }

    // ═══════════════════════════════════════════════════════════════
    // Update Store
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_store()
    {
        $store = Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Old Name']);

        $result = $this->service->updateStore(
            $this->account->id, $store->id,
            ['name' => 'New Name', 'city' => 'الرياض'],
            $this->owner
        );

        $this->assertEquals('New Name', $result->name);
        $this->assertEquals('الرياض', $result->city);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_rejects_duplicate_name()
    {
        Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Existing']);
        $store2 = Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Other']);

        $this->expectException(BusinessException::class);
        $this->service->updateStore(
            $this->account->id, $store2->id,
            ['name' => 'Existing'],
            $this->owner
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // Set Default
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_set_default_store()
    {
        $store1 = Store::factory()->default()->create(['account_id' => $this->account->id, 'name' => 'S1']);
        $store2 = Store::factory()->create(['account_id' => $this->account->id, 'name' => 'S2']);

        $this->service->setDefault($this->account->id, $store2->id, $this->owner);

        $this->assertFalse($store1->fresh()->is_default);
        $this->assertTrue($store2->fresh()->is_default);
    }

    // ═══════════════════════════════════════════════════════════════
    // Delete Store
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_delete_non_default_store()
    {
        Store::factory()->default()->create(['account_id' => $this->account->id, 'name' => 'Default']);
        $store2 = Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Deletable']);

        $this->service->deleteStore($this->account->id, $store2->id, $this->owner);

        $this->assertSoftDeleted('stores', ['id' => $store2->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_delete_default_store_when_others_exist()
    {
        $default = Store::factory()->default()->create(['account_id' => $this->account->id, 'name' => 'Default']);
        Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Other']);

        $this->expectException(BusinessException::class);
        $this->service->deleteStore($this->account->id, $default->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_delete_default_store_if_only_one()
    {
        $store = Store::factory()->default()->create(['account_id' => $this->account->id, 'name' => 'Only One']);

        $this->service->deleteStore($this->account->id, $store->id, $this->owner);

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
    }

    // ═══════════════════════════════════════════════════════════════
    // Toggle Status & Stats
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function toggle_status_activates_inactive_store()
    {
        $store = Store::factory()->inactive()->create(['account_id' => $this->account->id]);

        $result = $this->service->toggleStatus($this->account->id, $store->id, $this->owner);

        $this->assertEquals('active', $result->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_store_stats()
    {
        Store::factory()->count(2)->create(['account_id' => $this->account->id, 'status' => 'active']);
        Store::factory()->inactive()->create(['account_id' => $this->account->id, 'name' => 'Inact']);

        $stats = $this->service->getStoreStats($this->account->id);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        $this->assertEquals(1, $stats['inactive']);
        $this->assertEquals(Store::MAX_STORES_PER_ACCOUNT, $stats['max_allowed']);
    }
}
