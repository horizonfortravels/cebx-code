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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationsAuthorizationMatrixTest extends TestCase
{
    #[Test]
    public function same_tenant_with_notifications_read_gets_2xx_on_read_endpoints(): void
    {
        $this->skipIfMissingTables(['notifications']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, ['notifications.read']);

        $this->createNotification((string) $account->id, (string) $user->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')->assertOk();
        $this->getJson('/api/v1/notifications/in-app')->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertOk();
    }

    #[Test]
    public function same_tenant_missing_notifications_read_gets_403(): void
    {
        $this->skipIfMissingTables(['notifications']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function manage_endpoint_requires_notifications_manage_permission(): void
    {
        $this->skipIfMissingTables(['notifications']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->createNotification((string) $account->id, (string) $user->id);
        $this->grantExternalPermissions((string) $user->id, (string) $account->id, ['notifications.read']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, [
            'notifications.read',
            'notifications.manage',
        ]);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    #[Test]
    public function cross_tenant_notification_id_access_returns_404(): void
    {
        $this->skipIfMissingTables(['notifications']);

        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $userA = $this->createUser([
            'account_id' => (string) $accountA->id,
            'user_type' => 'external',
        ]);

        $userB = $this->createUser([
            'account_id' => (string) $accountB->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $userA->id, (string) $accountA->id, [
            'notifications.read',
            'notifications.manage',
        ]);

        $notificationId = $this->createNotification((string) $accountB->id, (string) $userB->id);

        Sanctum::actingAs($userA);

        $this->postJson('/api/v1/notifications/' . $notificationId . '/read')
            ->assertNotFound();
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

    /**
     * @param array<int, string> $permissions
     */
    private function grantExternalPermissions(string $userId, string $accountId, array $permissions): void
    {
        $roleId = (string) Str::uuid();

        $rolePayload = [
            'id' => $roleId,
            'account_id' => $accountId,
            'name' => 'ntf_matrix_' . Str::random(8),
            'display_name' => 'Notifications Matrix Role',
            'description' => 'Notifications authorization matrix role',
            'is_system' => false,
            'template' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('roles', 'slug')) {
            $rolePayload['slug'] = Str::slug($rolePayload['name']);
        }
        if (Schema::hasColumn('roles', 'deleted_at')) {
            $rolePayload['deleted_at'] = null;
        }

        DB::table('roles')->insert($rolePayload);

        foreach ($permissions as $permissionKey) {
            $permission = $this->upsertPermission($permissionKey, 'external');

            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permission->id,
            ], [
                'granted_at' => now(),
            ]);
        }

        DB::table('user_role')->updateOrInsert([
            'user_id' => $userId,
            'role_id' => $roleId,
        ], [
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);
    }

    private function createNotification(string $accountId, string $userId): string
    {
        $notificationId = (string) Str::uuid();

        DB::table('notifications')->insert(array_filter([
            'id' => $notificationId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'event_type' => Schema::hasColumn('notifications', 'event_type') ? 'shipment.created' : null,
            'type' => Schema::hasColumn('notifications', 'type') ? 'shipment.created' : null,
            'title' => Schema::hasColumn('notifications', 'title') ? 'Test notification' : null,
            'entity_type' => Schema::hasColumn('notifications', 'entity_type') ? 'shipment' : null,
            'entity_id' => Schema::hasColumn('notifications', 'entity_id') ? (string) Str::uuid() : null,
            'event_data' => Schema::hasColumn('notifications', 'event_data') ? json_encode(['ref' => 'test']) : null,
            'channel' => Schema::hasColumn('notifications', 'channel') ? 'in_app' : null,
            'destination' => Schema::hasColumn('notifications', 'destination') ? $userId : null,
            'language' => Schema::hasColumn('notifications', 'language') ? 'en' : null,
            'subject' => Schema::hasColumn('notifications', 'subject') ? 'Test notification' : null,
            'body' => Schema::hasColumn('notifications', 'body') ? 'Body' : null,
            'status' => Schema::hasColumn('notifications', 'status') ? 'queued' : null,
            'is_batched' => Schema::hasColumn('notifications', 'is_batched') ? false : null,
            'is_throttled' => Schema::hasColumn('notifications', 'is_throttled') ? false : null,
            'read_at' => null,
            'created_at' => Schema::hasColumn('notifications', 'created_at') ? now() : null,
            'updated_at' => Schema::hasColumn('notifications', 'updated_at') ? now() : null,
        ], static fn ($value): bool => $value !== null));

        return $notificationId;
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
