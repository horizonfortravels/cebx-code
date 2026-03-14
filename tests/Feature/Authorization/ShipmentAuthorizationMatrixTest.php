<?php

namespace Tests\Feature\Authorization;

use App\Models\Account;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ShipmentAuthorizationMatrixTest extends TestCase
{
    #[Test]
    public function test_external_same_tenant_with_permission_can_view_shipments_list(): void
    {
        if (!Schema::hasTable('shipments')) {
            $this->markTestSkipped('shipments table is not available in this environment.');
        }

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermission((string) $user->id, (string) $account->id, 'shipments.read');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/shipments');

        $response->assertOk();
    }

    #[Test]
    public function test_external_request_flow_permissions_can_create_a_shipment_draft(): void
    {
        if (!Schema::hasTable('shipments')) {
            $this->markTestSkipped('shipments table is not available in this environment.');
        }

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, [
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/shipments', $this->shipmentDraftPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    #[Test]
    public function test_external_same_tenant_missing_permission_gets_403(): void
    {
        if (!Schema::hasTable('shipments')) {
            $this->markTestSkipped('shipments table is not available in this environment.');
        }

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $shipment = $this->createShipment((string) $account->id, (string) $user->id);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/shipments/' . $shipment->id);

        $response->assertStatus(403);
    }

    #[Test]
    public function test_external_cross_tenant_resource_returns_404_even_with_permission(): void
    {
        if (!Schema::hasTable('shipments')) {
            $this->markTestSkipped('shipments table is not available in this environment.');
        }

        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $userB = $this->createUser([
            'account_id' => (string) $accountB->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermission((string) $userB->id, (string) $accountB->id, 'shipments.read');

        $shipmentA = $this->createShipment((string) $accountA->id, (string) $userB->id);

        Sanctum::actingAs($userB);

        $response = $this->getJson('/api/v1/shipments/' . $shipmentA->id);

        $response->assertNotFound();
    }

    #[Test]
    public function test_external_request_flow_permissions_cannot_cancel_shipments_without_manage_grant(): void
    {
        if (!Schema::hasTable('shipments')) {
            $this->markTestSkipped('shipments table is not available in this environment.');
        }

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, [
            'shipments.read',
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
        ]);

        $shipment = $this->createShipment((string) $account->id, (string) $user->id);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/shipments/' . $shipment->id . '/cancel')
            ->assertStatus(403);
    }

    #[Test]
    public function test_internal_with_tenant_context_permission_can_resolve_context(): void
    {
        $tenant = $this->createAccount();

        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $this->grantInternalPermission((string) $internalUser->id, 'tenancy.context.select');

        Sanctum::actingAs($internalUser);

        $response = $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->getJson('/api/v1/internal/tenant-context/ping');

        $response->assertOk();
        $this->assertSame((string) $tenant->id, (string) $response->json('data.current_account_id'));
    }

    private function grantExternalPermission(string $userId, string $accountId, string $key): void
    {
        $permission = $this->upsertPermission($key, 'external');
        $roleId = (string) Str::uuid();

        DB::table('roles')->insert([
            'id' => $roleId,
            'account_id' => $accountId,
            'name' => 'ext_' . Str::random(8),
            'display_name' => 'External Matrix Role',
            'description' => 'External matrix role',
            'is_system' => false,
            'template' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        DB::table('role_permission')->insert([
            'role_id' => $roleId,
            'permission_id' => $permission->id,
            'granted_at' => now(),
        ]);

        DB::table('user_role')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);
    }

    /**
     * @param array<int, string> $keys
     */
    private function grantExternalPermissions(string $userId, string $accountId, array $keys): void
    {
        foreach ($keys as $key) {
            $this->grantExternalPermission($userId, $accountId, $key);
        }
    }

    private function grantInternalPermission(string $userId, string $key): void
    {
        $permission = $this->upsertPermission($key, 'internal');
        $roleId = (string) Str::uuid();

        DB::table('internal_roles')->insert([
            'id' => $roleId,
            'name' => 'int_' . Str::random(8),
            'display_name' => 'Internal Matrix Role',
            'description' => 'Internal matrix role',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        DB::table('internal_role_permission')->insert([
            'internal_role_id' => $roleId,
            'permission_id' => $permission->id,
            'granted_at' => now(),
        ]);

        DB::table('internal_user_role')->insert([
            'user_id' => $userId,
            'internal_role_id' => $roleId,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);
    }

    private function createAccount(): Account
    {
        $payload = [
            'name' => 'Account ' . Str::random(8),
        ];

        if (Schema::hasColumn('accounts', 'slug')) {
            $payload['slug'] = 'acct-' . Str::lower(Str::random(8));
        }
        if (Schema::hasColumn('accounts', 'status')) {
            $payload['status'] = 'active';
        }
        if (Schema::hasColumn('accounts', 'type')) {
            $payload['type'] = 'organization';
        }
        if (Schema::hasColumn('accounts', 'kyc_status')) {
            $payload['kyc_status'] = 'not_submitted';
        }
        if (Schema::hasColumn('accounts', 'settings')) {
            $payload['settings'] = [];
        }
        if (Schema::hasColumn('accounts', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('accounts', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $accountId = $this->insertRowAndReturnId('accounts', $payload);

        /** @var Account $account */
        $account = Account::withoutGlobalScopes()->where('id', $accountId)->firstOrFail();

        return $account;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createUser(array $overrides): User
    {
        $payload = [
            'name' => 'User ' . Str::random(8),
            'email' => Str::lower(Str::random(10)) . '@example.test',
            'password' => Hash::make('Password1!'),
        ];

        if (Schema::hasColumn('users', 'account_id')) {
            $payload['account_id'] = $overrides['account_id'] ?? null;
        }
        if (Schema::hasColumn('users', 'user_type')) {
            $payload['user_type'] = $overrides['user_type'] ?? 'external';
        }
        if (Schema::hasColumn('users', 'status')) {
            $payload['status'] = $overrides['status'] ?? 'active';
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $payload['is_active'] = $overrides['is_active'] ?? true;
        }
        if (Schema::hasColumn('users', 'is_owner')) {
            $payload['is_owner'] = $overrides['is_owner'] ?? false;
        }
        if (Schema::hasColumn('users', 'is_super_admin')) {
            $payload['is_super_admin'] = $overrides['is_super_admin'] ?? false;
        }
        if (Schema::hasColumn('users', 'role')) {
            $payload['role'] = $overrides['role'] ?? 'operator';
        }
        if (Schema::hasColumn('users', 'role_name')) {
            $payload['role_name'] = $overrides['role_name'] ?? 'operator';
        }
        if (Schema::hasColumn('users', 'locale')) {
            $payload['locale'] = $overrides['locale'] ?? 'en';
        }
        if (Schema::hasColumn('users', 'timezone')) {
            $payload['timezone'] = $overrides['timezone'] ?? 'UTC';
        }
        if (Schema::hasColumn('users', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('users', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $userId = $this->insertRowAndReturnId('users', $payload);

        /** @var User $user */
        $user = User::withoutGlobalScopes()->where('id', $userId)->firstOrFail();

        return $user;
    }

    private function createShipment(string $accountId, string $userId): object
    {
        $payload = [];

        if (Schema::hasColumn('shipments', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('shipments', 'user_id')) {
            $payload['user_id'] = $userId;
        }
        if (Schema::hasColumn('shipments', 'created_by')) {
            $payload['created_by'] = $userId;
        }
        if (Schema::hasColumn('shipments', 'reference_number')) {
            $payload['reference_number'] = 'SHP-T-' . strtoupper(Str::random(10));
        }
        if (Schema::hasColumn('shipments', 'type')) {
            $payload['type'] = 'domestic';
        }
        if (Schema::hasColumn('shipments', 'source')) {
            $payload['source'] = 'direct';
        }
        if (Schema::hasColumn('shipments', 'status')) {
            $payload['status'] = 'draft';
        }
        if (Schema::hasColumn('shipments', 'sender_name')) {
            $payload['sender_name'] = 'Sender';
        }
        if (Schema::hasColumn('shipments', 'sender_phone')) {
            $payload['sender_phone'] = '+966500000001';
        }
        if (Schema::hasColumn('shipments', 'sender_city')) {
            $payload['sender_city'] = 'Riyadh';
        }
        if (Schema::hasColumn('shipments', 'sender_country')) {
            $payload['sender_country'] = 'SA';
        }
        if (Schema::hasColumn('shipments', 'sender_address_1')) {
            $payload['sender_address_1'] = 'Street 1';
        }
        if (Schema::hasColumn('shipments', 'recipient_name')) {
            $payload['recipient_name'] = 'Recipient';
        }
        if (Schema::hasColumn('shipments', 'recipient_phone')) {
            $payload['recipient_phone'] = '+966500000002';
        }
        if (Schema::hasColumn('shipments', 'recipient_city')) {
            $payload['recipient_city'] = 'Jeddah';
        }
        if (Schema::hasColumn('shipments', 'recipient_country')) {
            $payload['recipient_country'] = 'SA';
        }
        if (Schema::hasColumn('shipments', 'recipient_address_1')) {
            $payload['recipient_address_1'] = 'Street 2';
        }
        if (Schema::hasColumn('shipments', 'currency')) {
            $payload['currency'] = 'SAR';
        }
        if (Schema::hasColumn('shipments', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('shipments', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $shipmentId = $this->insertRowAndReturnId('shipments', $payload);

        return DB::table('shipments')->where('id', $shipmentId)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentDraftPayload(): array
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

    /**
     * @param array<string, mixed> $payload
     */
    private function insertRowAndReturnId(string $table, array $payload): string|int
    {
        if (!array_key_exists('id', $payload) && !$this->isNumericId($table)) {
            $payload['id'] = (string) Str::uuid();
        }

        if ($this->isNumericId($table)) {
            unset($payload['id']);
            return DB::table($table)->insertGetId($payload);
        }

        DB::table($table)->insert($payload);

        return $payload['id'];
    }

    private function isNumericId(string $table): bool
    {
        if (!Schema::hasColumn($table, 'id')) {
            return false;
        }

        $type = strtolower((string) Schema::getColumnType($table, 'id'));

        return in_array($type, [
            'integer', 'int', 'tinyint', 'smallint', 'mediumint', 'bigint',
            'biginteger', 'unsignedinteger', 'unsignedbiginteger',
        ], true);
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
