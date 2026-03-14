<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\PermissionResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class CreateInternalSuperAdmin extends Command
{
    private const USER_NAME = 'Internal Super Admin';
    private const USER_EMAIL = 'internal.admin@example.test';
    private const USER_PASSWORD = 'Password123!';
    private const ROLE_NAME = 'super_admin';

    protected $signature = 'app:create-internal-super-admin';

    protected $description = 'Create or update the internal super admin user using internal RBAC only';

    public function handle(PermissionResolver $permissionResolver): int
    {
        try {
            $this->assertRequiredSchema();

            $roleId = $this->ensureCanonicalSuperAdminRole();

            [$user, $action] = DB::transaction(function () use ($roleId): array {
                $user = $this->resolveSingleUserByEmail();
                $action = $user === null ? 'created' : 'updated';

                $payload = $this->userPayload();

                if ($user === null) {
                    $user = User::query()
                        ->withoutGlobalScopes()
                        ->create(array_merge(['email' => self::USER_EMAIL], $payload));
                } else {
                    $user->forceFill(array_merge(['email' => self::USER_EMAIL], $payload))->save();
                }

                if (Schema::hasTable('user_role') && Schema::hasColumn('user_role', 'user_id')) {
                    DB::table('user_role')
                        ->where('user_id', (string) $user->id)
                        ->delete();
                }

                $pivotValues = [];
                if (Schema::hasColumn('internal_user_role', 'assigned_by')) {
                    $pivotValues['assigned_by'] = null;
                }
                if (Schema::hasColumn('internal_user_role', 'assigned_at')) {
                    $pivotValues['assigned_at'] = now();
                }

                DB::table('internal_user_role')->updateOrInsert(
                    [
                        'user_id' => (string) $user->id,
                        'internal_role_id' => $roleId,
                    ],
                    $pivotValues
                );

                return [
                    User::query()->withoutGlobalScopes()->findOrFail($user->id),
                    $action,
                ];
            });

            $adminAccess = $permissionResolver->can($user, 'admin.access');
            $tenantContextSelect = $permissionResolver->can($user, 'tenancy.context.select');

            $this->info(sprintf('Internal super admin %s.', $action));
            $this->line(sprintf('Email: %s', self::USER_EMAIL));
            $this->line(sprintf('User type: %s', (string) $user->user_type));
            $this->line(sprintf('Status: %s', (string) $user->status));
            $this->line(sprintf('Role: %s', self::ROLE_NAME));
            $this->line(sprintf('admin.access: %s', $adminAccess ? 'true' : 'false'));
            $this->line(sprintf('tenancy.context.select: %s', $tenantContextSelect ? 'true' : 'false'));

            if (!$adminAccess || !$tenantContextSelect) {
                $this->error('Permission verification failed for the internal super admin user.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function assertRequiredSchema(): void
    {
        $requirements = [
            'users' => ['id', 'email', 'name', 'password', 'account_id', 'user_type', 'status', 'locale', 'timezone', 'email_verified_at'],
            'permissions' => ['id', 'key'],
            'internal_roles' => ['id', 'name'],
            'internal_user_role' => ['user_id', 'internal_role_id'],
            'internal_role_permission' => ['internal_role_id', 'permission_id'],
        ];

        foreach ($requirements as $table => $columns) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException(sprintf('Required table [%s] is missing. Run the latest migrations first.', $table));
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    throw new RuntimeException(sprintf('Required column [%s.%s] is missing. Run the latest migrations first.', $table, $column));
                }
            }
        }
    }

    private function ensureCanonicalSuperAdminRole(): string
    {
        $roleId = $this->resolveInternalSuperAdminRoleId();

        if ($roleId !== null && $this->internalRoleHasRequiredPermissions($roleId)) {
            return $roleId;
        }

        $this->line('Seeding canonical roles and permissions...');

        $exitCode = Artisan::call('db:seed', [
            '--class' => RolesAndPermissionsSeeder::class,
            '--force' => true,
        ]);

        if ($exitCode !== self::SUCCESS) {
            throw new RuntimeException(sprintf(
                'Failed to run %s. %s',
                RolesAndPermissionsSeeder::class,
                trim(Artisan::output())
            ));
        }

        $roleId = $this->resolveInternalSuperAdminRoleId();

        if ($roleId === null) {
            throw new RuntimeException('Canonical internal role [super_admin] was not created.');
        }

        if (!$this->internalRoleHasRequiredPermissions($roleId)) {
            throw new RuntimeException('Canonical internal role [super_admin] is missing required permissions.');
        }

        return $roleId;
    }

    private function resolveInternalSuperAdminRoleId(): ?string
    {
        $roleId = DB::table('internal_roles')
            ->where('name', self::ROLE_NAME)
            ->value('id');

        return $roleId === null ? null : (string) $roleId;
    }

    private function internalRoleHasRequiredPermissions(string $roleId): bool
    {
        $requiredPermissions = ['admin.access', 'tenancy.context.select'];

        $granted = DB::table('internal_role_permission as irp')
            ->join('permissions as p', 'p.id', '=', 'irp.permission_id')
            ->where('irp.internal_role_id', $roleId)
            ->whereIn('p.key', $requiredPermissions)
            ->pluck('p.key')
            ->map(static fn ($key): string => (string) $key)
            ->unique()
            ->values()
            ->all();

        sort($granted);
        sort($requiredPermissions);

        return $granted === $requiredPermissions;
    }

    private function resolveSingleUserByEmail(): ?User
    {
        $users = User::query()
            ->withoutGlobalScopes()
            ->where('email', self::USER_EMAIL)
            ->get();

        if ($users->count() > 1) {
            throw new RuntimeException(sprintf(
                'Multiple users already exist with email [%s]. Resolve duplicates before running this command.',
                self::USER_EMAIL
            ));
        }

        return $users->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(): array
    {
        $payload = [
            'name' => self::USER_NAME,
            'password' => Hash::make(self::USER_PASSWORD),
            'account_id' => null,
            'user_type' => 'internal',
            'status' => 'active',
            'locale' => 'en',
            'timezone' => 'UTC',
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'is_owner')) {
            $payload['is_owner'] = false;
        }

        if (Schema::hasColumn('users', 'deleted_at')) {
            $payload['deleted_at'] = null;
        }

        return $payload;
    }
}
