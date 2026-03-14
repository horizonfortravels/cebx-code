<?php

namespace Tests\Feature\E2E;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiSmokeMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const EXTERNAL_OWNER_C_EMAIL = 'e2e.c.organization_owner@example.test';
    private const EXTERNAL_STAFF_C_EMAIL = 'e2e.c.staff@example.test';
    private const EXTERNAL_OWNER_D_EMAIL = 'e2e.d.organization_owner@example.test';
    private const INTERNAL_SUPER_ADMIN_EMAIL = 'e2e.internal.super_admin@example.test';
    private const INTERNAL_OPS_READONLY_EMAIL = 'e2e.internal.ops_readonly@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function it_validates_api_smoke_matrix_for_rbac_and_tenant_isolation_using_e2e_identities(): void
    {
        $this->skipIfMissingTables([
            'accounts',
            'users',
            'roles',
            'permissions',
            'role_permission',
            'user_role',
            'internal_roles',
            'internal_role_permission',
            'internal_user_role',
            'shipments',
        ]);

        $accountC = $this->accountBySlugOrName('e2e-account-c', 'E2E Account C');
        $accountD = $this->accountBySlugOrName('e2e-account-d', 'E2E Account D');

        $externalOwnerC = $this->userByEmail(self::EXTERNAL_OWNER_C_EMAIL);
        $externalStaffC = $this->userByEmail(self::EXTERNAL_STAFF_C_EMAIL);
        $externalOwnerD = $this->userByEmail(self::EXTERNAL_OWNER_D_EMAIL);
        $internalSuperAdmin = $this->userByEmail(self::INTERNAL_SUPER_ADMIN_EMAIL);
        $internalOpsReadonly = $this->userByEmail(self::INTERNAL_OPS_READONLY_EMAIL);

        $this->forceExternalPermissions($externalOwnerC, (string) $accountC->id, [
            'shipments.read',
            'orders.read',
            'wallet.balance',
            'billing.manage',
            'reports.read',
        ]);

        Sanctum::actingAs($externalOwnerC);
        $this->getJson('/api/v1/shipments')->assertOk();
        $this->getJson('/api/v1/orders')->assertOk();
        $this->postJson('/api/v1/billing/wallets', ['currency' => 'USD'])->assertStatus(201);
        $this->getJson('/api/v1/reports/saved')->assertOk();

        DB::table('user_role')->where('user_id', (string) $externalStaffC->id)->delete();
        Sanctum::actingAs($externalStaffC);
        $this->getJson('/api/v1/shipments')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $crossTenantShipment = $this->createShipmentForAccount(
            accountId: (string) $accountD->id,
            userId: (string) $externalOwnerD->id
        );

        Sanctum::actingAs($externalOwnerC);
        $this->getJson('/api/v1/shipments/' . $crossTenantShipment->id)->assertNotFound();

        $this->forceInternalPermissions($internalSuperAdmin, [
            'tenancy.context.select',
            'admin.access',
            'api_keys.read',
        ]);

        Sanctum::actingAs($internalSuperAdmin);
        $this->getJson('/api/v1/internal/tenant-context/ping')
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_REQUIRED');

        DB::table('internal_user_role')->where('user_id', (string) $internalOpsReadonly->id)->delete();
        $this->forceInternalPermissions($internalOpsReadonly, ['reports.read']);

        Sanctum::actingAs($internalOpsReadonly);
        $this->withHeaders(['X-Tenant-Account-Id' => (string) $accountC->id])
            ->getJson('/api/v1/internal/tenant-context/ping')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_FORBIDDEN');

        Sanctum::actingAs($internalSuperAdmin);
        $this->withHeaders(['X-Tenant-Account-Id' => (string) $accountC->id])
            ->getJson('/api/v1/admin/api-keys')
            ->assertOk();
    }

    /**
     * @param array<int, string> $tables
     */
    private function skipIfMissingTables(array $tables): void
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped(sprintf('%s table is not available in this environment.', $table));
            }
        }
    }

    private function accountBySlugOrName(string $slug, string $name): Account
    {
        /** @var Account|null $account */
        $account = Account::withoutGlobalScopes()
            ->where(function ($query) use ($slug, $name): void {
                if (Schema::hasColumn('accounts', 'slug')) {
                    $query->where('slug', $slug);
                }

                $query->orWhere('name', $name);
            })
            ->first();

        if (!$account) {
            $this->fail(sprintf('Expected account not found for slug "%s" / name "%s".', $slug, $name));
        }

        return $account;
    }

    private function userByEmail(string $email): User
    {
        /** @var User|null $user */
        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        if (!$user) {
            $this->fail(sprintf('Expected seeded E2E user was not found: %s', $email));
        }

        return $user;
    }

    /**
     * @param array<int, string> $permissionKeys
     */
    private function forceExternalPermissions(User $user, string $accountId, array $permissionKeys): void
    {
        DB::table('user_role')->where('user_id', (string) $user->id)->delete();

        $roleId = (string) Str::uuid();
        $rolePayload = [
            'id' => $roleId,
            'account_id' => $accountId,
            'name' => 'e2e_external_smoke_' . Str::lower(Str::random(8)),
            'display_name' => 'E2E External Smoke',
            'description' => 'E2E external smoke role',
            'is_system' => false,
            'template' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('roles', 'slug')) {
            $rolePayload['slug'] = Str::slug((string) $rolePayload['name'], '_');
        }
        if (Schema::hasColumn('roles', 'deleted_at')) {
            $rolePayload['deleted_at'] = null;
        }

        DB::table('roles')->insert($rolePayload);

        foreach ($permissionKeys as $key) {
            $permission = $this->upsertPermission($key, 'external');
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => (string) $permission->id],
                ['granted_at' => now()]
            );
        }

        DB::table('user_role')->insert([
            'user_id' => (string) $user->id,
            'role_id' => $roleId,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);
    }

    /**
     * @param array<int, string> $permissionKeys
     */
    private function forceInternalPermissions(User $user, array $permissionKeys): void
    {
        DB::table('internal_user_role')->where('user_id', (string) $user->id)->delete();

        $roleId = (string) Str::uuid();
        DB::table('internal_roles')->insert([
            'id' => $roleId,
            'name' => 'e2e_internal_smoke_' . Str::lower(Str::random(8)),
            'display_name' => 'E2E Internal Smoke',
            'description' => 'E2E internal smoke role',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        foreach ($permissionKeys as $key) {
            $permission = $this->upsertPermission($key, 'internal');
            DB::table('internal_role_permission')->updateOrInsert(
                ['internal_role_id' => $roleId, 'permission_id' => (string) $permission->id],
                ['granted_at' => now()]
            );
        }

        DB::table('internal_user_role')->insert([
            'user_id' => (string) $user->id,
            'internal_role_id' => $roleId,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);
    }

    private function createShipmentForAccount(string $accountId, string $userId): Shipment
    {
        $overrides = [
            'account_id' => $accountId,
            'user_id' => $userId,
            'source' => 'direct',
            'status' => 'draft',
        ];

        if (Schema::hasColumn('shipments', 'created_by')) {
            $overrides['created_by'] = $userId;
        }

        return Shipment::factory()->create($overrides);
    }

    private function upsertPermission(string $key, string $audience): Permission
    {
        $values = [
            'group' => explode('.', $key)[0],
            'display_name' => $key,
            'description' => $key,
        ];

        if (Schema::hasColumn('permissions', 'audience')) {
            $values['audience'] = $audience;
        }

        return Permission::query()->updateOrCreate(['key' => $key], $values);
    }
}
