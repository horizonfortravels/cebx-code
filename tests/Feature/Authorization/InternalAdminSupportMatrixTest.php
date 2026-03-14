<?php

namespace Tests\Feature\Authorization;

use App\Models\Account;
use App\Models\Permission;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalAdminSupportMatrixTest extends TestCase
{
    #[Test]
    public function internal_support_route_requires_tenant_context_header(): void
    {
        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $this->grantInternalPermissions((string) $internalUser->id, [
            'admin.access',
            'tickets.read',
        ]);

        Sanctum::actingAs($internalUser);

        $this->getJson('/api/v1/support/tickets')
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_REQUIRED');
    }

    #[Test]
    public function internal_support_route_with_header_but_without_tenant_select_permission_is_forbidden(): void
    {
        $tenant = $this->createAccount();
        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $this->grantInternalPermissions((string) $internalUser->id, [
            'admin.access',
            'tickets.read',
        ]);

        Sanctum::actingAs($internalUser);

        $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->getJson('/api/v1/support/tickets')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_FORBIDDEN');
    }

    #[Test]
    public function internal_support_read_with_required_permissions_returns_success(): void
    {
        $tenant = $this->createAccount();
        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $this->grantInternalPermissions((string) $internalUser->id, [
            'admin.access',
            'tenancy.context.select',
            'tickets.read',
        ]);

        Sanctum::actingAs($internalUser);

        $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->getJson('/api/v1/support/tickets')
            ->assertOk();
    }

    #[Test]
    public function internal_support_mutate_with_required_permissions_returns_success(): void
    {
        $tenant = $this->createAccount();
        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $this->grantInternalPermissions((string) $internalUser->id, [
            'admin.access',
            'tenancy.context.select',
            'tickets.manage',
        ]);

        Sanctum::actingAs($internalUser);

        $this->mock(AdminService::class, function (MockInterface $mock) use ($tenant, $internalUser): void {
            $mock->shouldReceive('createTicket')
                ->once()
                ->andReturn(new SupportTicket([
                    'id' => (string) Str::uuid(),
                    'account_id' => (string) $tenant->id,
                    'user_id' => (string) $internalUser->id,
                    'subject' => 'Internal support ticket',
                    'description' => 'Created by matrix test',
                    'status' => 'open',
                ]));
        });

        $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->postJson('/api/v1/support/tickets', [
            'subject' => 'Internal support ticket',
            'description' => 'Created by matrix test',
            'category' => 'technical',
            'priority' => 'medium',
        ])->assertStatus(201);
    }

    /**
     * @param array<int, string> $keys
     */
    private function grantInternalPermissions(string $userId, array $keys): void
    {
        $roleId = (string) Str::uuid();

        DB::table('internal_roles')->insert([
            'id' => $roleId,
            'name' => 'internal_support_' . Str::random(8),
            'display_name' => 'Internal Support Role',
            'description' => 'Internal support test role',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        foreach ($keys as $key) {
            $permission = $this->upsertPermission($key, 'internal');

            DB::table('internal_role_permission')->insert([
                'internal_role_id' => $roleId,
                'permission_id' => $permission->id,
                'granted_at' => now(),
            ]);
        }

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
