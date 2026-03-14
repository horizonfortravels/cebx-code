<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\Store;
use App\Models\AuditLog;
use App\Services\AuditService;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * FR-IAM-009: Multi-Store Management — Integration Tests (20 tests)
 */
class StoreApiTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    protected Account $account;
    protected User $owner;
    protected User $storeManager;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        AuditService::resetRequestId();

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
    // POST /stores (Create)
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_create_store()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/stores', [
                'name'     => 'متجري الأول',
                'platform' => 'manual',
                'city'     => 'الرياض',
                'country'  => 'SA',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'متجري الأول')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'slug', 'status', 'platform', 'platform_display',
                    'contact', 'address', 'currency', 'is_default',
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_manager_can_create_store()
    {
        $response = $this->actingAs($this->storeManager)
            ->postJson('/api/v1/stores', ['name' => 'Manager Store']);

        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_without_permission_cannot_create()
    {
        $response = $this->actingAs($this->member)
            ->postJson('/api/v1/stores', ['name' => 'Blocked']);

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function duplicate_name_returns_422()
    {
        Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Existing Store']);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/stores', ['name' => 'Existing Store']);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_STORE_EXISTS');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function max_stores_limit_returns_422()
    {
        for ($i = 1; $i <= Store::MAX_STORES_PER_ACCOUNT; $i++) {
            Store::factory()->create([
                'account_id' => $this->account->id,
                'name'       => "Store $i",
                'slug'       => "store-$i",
            ]);
        }

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/stores', ['name' => 'Overflow Store']);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_MAX_STORES_REACHED');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function name_is_required()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/stores', ['platform' => 'manual']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function create_is_audit_logged()
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/stores', ['name' => 'Audit Test Store']);

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'store.created')
            ->first();

        $this->assertNotNull($log);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /stores (List)
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_list_stores()
    {
        Store::factory()->count(3)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/stores');

        $response->assertOk()
            ->assertJsonPath('meta.count', 3);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function list_filters_by_status()
    {
        Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Active', 'status' => 'active']);
        Store::factory()->inactive()->create(['account_id' => $this->account->id, 'name' => 'Inactive']);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/stores?status=active');

        $response->assertOk()
            ->assertJsonPath('meta.count', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function list_filters_by_platform()
    {
        Store::factory()->shopify()->create(['account_id' => $this->account->id, 'name' => 'Shopify Store']);
        Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Manual Store']);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/stores?platform=shopify');

        $response->assertOk()
            ->assertJsonPath('meta.count', 1);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /stores/{id} (Show)
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_view_single_store()
    {
        $store = Store::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/stores/{$store->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $store->id)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'platform_display', 'contact', 'address'],
            ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // PUT /stores/{id} (Update)
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_store()
    {
        $store = Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Old']);

        $response = $this->actingAs($this->owner)
            ->putJson("/api/v1/stores/{$store->id}", [
                'name' => 'New Name',
                'city' => 'جدة',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.address.city', 'جدة');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_rejects_duplicate_name()
    {
        Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Taken']);
        $store2 = Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Other']);

        $response = $this->actingAs($this->owner)
            ->putJson("/api/v1/stores/{$store2->id}", ['name' => 'Taken']);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // DELETE /stores/{id}
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_delete_non_default_store()
    {
        Store::factory()->default()->create(['account_id' => $this->account->id, 'name' => 'Default']);
        $store2 = Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Deletable']);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/stores/{$store2->id}");

        $response->assertOk();
        $this->assertSoftDeleted('stores', ['id' => $store2->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_delete_default_store()
    {
        $default = Store::factory()->default()->create(['account_id' => $this->account->id, 'name' => 'Default']);
        Store::factory()->create(['account_id' => $this->account->id, 'name' => 'Other']);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/stores/{$default->id}");

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /stores/{id}/set-default
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_set_default_store()
    {
        $store1 = Store::factory()->default()->create(['account_id' => $this->account->id, 'name' => 'S1']);
        $store2 = Store::factory()->create(['account_id' => $this->account->id, 'name' => 'S2']);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/stores/{$store2->id}/set-default");

        $response->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($store1->fresh()->is_default);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /stores/{id}/toggle-status
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_toggle_store_status()
    {
        $store = Store::factory()->create(['account_id' => $this->account->id, 'status' => 'active']);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/stores/{$store->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /stores/stats
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_view_store_stats()
    {
        Store::factory()->count(2)->create(['account_id' => $this->account->id]);
        Store::factory()->inactive()->create(['account_id' => $this->account->id, 'name' => 'Inact']);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/stores/stats');

        $response->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.active', 2)
            ->assertJsonPath('data.inactive', 1)
            ->assertJsonPath('data.max_allowed', Store::MAX_STORES_PER_ACCOUNT);
    }
}
