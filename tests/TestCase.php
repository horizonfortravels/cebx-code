<?php
namespace Tests;

use App\Models\Account;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithStrictRbac;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function createAuthenticatedUser(string $role = 'super-admin')
    {
        $account = Account::factory()->create();

        $user = $this->createUserWithRole((string) $account->id);

        $permissions = $this->permissionsForTestRole($role);
        $this->grantTenantPermissions($user, $permissions, 'test_' . str_replace('-', '_', $role));

        return $user;
    }

    protected function authHeaders($user = null)
    {
        $user = $user ?? $this->createAuthenticatedUser();
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function createUserWithRole(string $accountId, ?string $roleId = null, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'account_id' => $accountId,
            'user_type' => 'external',
        ], $attributes));

        if ($roleId) {
            $user->roles()->syncWithoutDetaching([
                $roleId => [
                    'assigned_by' => null,
                    'assigned_at' => now(),
                ],
            ]);
        }

        return $user;
    }

    /**
     * @return array<int, string>
     */
    private function permissionsForTestRole(string $role): array
    {
        $candidateKeys = match ($role) {
            'super-admin', 'super_admin', 'organization_owner', 'owner' => [
                'account.read',
                'account.manage',
                'users.read',
                'users.manage',
                'users.invite',
                'roles.read',
                'roles.manage',
                'roles.assign',
                'stores.read',
                'stores.manage',
                'shipments.read',
                'shipments.manage',
                'shipments.print_label',
                'orders.read',
                'orders.manage',
                'wallet.balance',
                'wallet.ledger',
                'wallet.topup',
                'wallet.configure',
                'wallet.manage',
                'billing.view',
                'billing.manage',
                'api_keys.read',
                'api_keys.manage',
                'webhooks.read',
                'webhooks.manage',
            ],
            'organization_admin', 'admin' => [
                'account.read',
                'account.manage',
                'users.read',
                'users.manage',
                'users.invite',
                'roles.read',
                'stores.read',
                'stores.manage',
                'shipments.read',
                'shipments.manage',
                'orders.read',
                'orders.manage',
                'wallet.balance',
                'wallet.ledger',
                'billing.view',
                'integrations.read',
                'integrations.manage',
                'api_keys.read',
                'api_keys.manage',
                'webhooks.read',
                'webhooks.manage',
            ],
            default => ['users.read'],
        };

        $available = Permission::query()
            ->whereIn('key', $candidateKeys)
            ->pluck('key')
            ->all();

        if ($available !== []) {
            return array_values(array_unique(array_map('strval', $available)));
        }

        if (Schema::hasTable('permissions')) {
            return $candidateKeys;
        }

        return ['users.read'];
    }
}
