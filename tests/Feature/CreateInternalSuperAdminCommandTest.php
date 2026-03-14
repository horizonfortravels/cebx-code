<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateInternalSuperAdminCommandTest extends TestCase
{
    #[Test]
    public function it_updates_an_existing_user_and_keeps_the_command_idempotent(): void
    {
        $account = Account::factory()->create([
            'status' => 'active',
            'type' => 'organization',
        ]);

        $legacyRole = Role::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'name' => 'legacy_external_role',
            'display_name' => 'Legacy External Role',
            'description' => 'Legacy tenant role for command coverage.',
            'is_system' => false,
            'template' => null,
        ]);

        $user = User::query()->withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'name' => 'Legacy External User',
            'email' => 'internal.admin@example.test',
            'email_verified_at' => null,
            'password' => Hash::make('OldPassword1!'),
            'status' => 'suspended',
            'is_owner' => true,
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'user_type' => 'external',
        ]);
        $originalUserId = (string) $user->id;

        DB::table('user_role')->insert([
            'user_id' => (string) $user->id,
            'role_id' => (string) $legacyRole->id,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        DB::table('internal_roles')->where('name', 'super_admin')->delete();

        $this->artisan('app:create-internal-super-admin')->assertSuccessful();
        $this->artisan('app:create-internal-super-admin')->assertSuccessful();

        $user->refresh();

        $superAdminRoleId = (string) DB::table('internal_roles')
            ->where('name', 'super_admin')
            ->value('id');

        $this->assertSame('Internal Super Admin', $user->name);
        $this->assertSame($originalUserId, (string) $user->id);
        $this->assertNotSame('', $superAdminRoleId);
        $this->assertNull($user->account_id);
        $this->assertSame('internal', $user->user_type);
        $this->assertSame('active', $user->status);
        $this->assertSame('en', $user->locale);
        $this->assertSame('UTC', $user->timezone);
        $this->assertFalse((bool) $user->is_owner);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Password123!', (string) $user->password));

        $this->assertDatabaseHas('internal_user_role', [
            'user_id' => (string) $user->id,
            'internal_role_id' => $superAdminRoleId,
        ]);
        $this->assertSame(1, DB::table('internal_user_role')
            ->where('user_id', (string) $user->id)
            ->where('internal_role_id', $superAdminRoleId)
            ->count());
        $this->assertSame(0, DB::table('user_role')
            ->where('user_id', (string) $user->id)
            ->count());

        $resolver = app(PermissionResolver::class);

        $this->assertTrue($resolver->can($user, 'admin.access'));
        $this->assertTrue($resolver->can($user, 'tenancy.context.select'));
    }
}
