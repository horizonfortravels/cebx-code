<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\KycVerification;
use App\Models\OrganizationProfile;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsSchemaAwareAuditLogs;
use Tests\TestCase;

class InternalAccountsManagementWebTest extends TestCase
{
    use AssertsSchemaAwareAuditLogs;
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_create_individual_account_from_internal_accounts_center(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');

        $response = $this->actingAs($actor, 'web')->post(route('internal.accounts.store'), [
            'account_name' => 'I2B Individual Account',
            'account_type' => 'individual',
            'owner_name' => 'I2B Individual Owner',
            'owner_email' => 'i2b-individual-owner@example.test',
            'owner_phone' => '+966500000001',
            'language' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'country' => 'SA',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $account = Account::query()->withoutGlobalScopes()->where('name', 'I2B Individual Account')->firstOrFail();

        $this->assertSame('individual', $account->type);
        $this->assertSame('pending', $account->status);
        $this->assertNull(
            OrganizationProfile::query()->withoutGlobalScopes()->where('account_id', (string) $account->id)->first()
        );

        $owner = User::query()->withoutGlobalScopes()->where('email', 'i2b-individual-owner@example.test')->firstOrFail();
        $this->assertSame((string) $account->id, (string) $owner->account_id);
        $this->assertTrue((bool) $owner->is_owner);

        $this->assertDatabaseHas('kyc_verifications', [
            'account_id' => (string) $account->id,
            'verification_type' => 'individual',
            'status' => KycVerification::STATUS_UNVERIFIED,
        ]);

        $this->assertAuditLogRecorded(
            'account.created',
            (string) $actor->id,
            (string) $account->id,
            'Account',
            (string) $account->id,
        );
    }

    #[Test]
    public function super_admin_can_create_organization_account_from_internal_accounts_center(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');

        $response = $this->actingAs($actor, 'web')->post(route('internal.accounts.store'), [
            'account_name' => 'I2B Organization Account',
            'account_type' => 'organization',
            'owner_name' => 'I2B Organization Owner',
            'owner_email' => 'i2b-organization-owner@example.test',
            'language' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'country' => 'SA',
            'legal_name' => 'I2B Organization LLC',
            'trade_name' => 'I2B Org',
            'registration_number' => 'CR-300300300',
            'tax_id' => 'VAT-300300300',
            'industry' => 'logistics',
            'company_size' => 'medium',
            'org_country' => 'SA',
            'org_city' => 'Riyadh',
            'org_email' => 'ops@i2b-org.example.test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $account = Account::query()->withoutGlobalScopes()->where('name', 'I2B Organization Account')->firstOrFail();

        $this->assertSame('organization', $account->type);
        $this->assertSame('pending', $account->status);
        $this->assertDatabaseHas('organization_profiles', [
            'account_id' => (string) $account->id,
            'legal_name' => 'I2B Organization LLC',
            'trade_name' => 'I2B Org',
            'registration_number' => 'CR-300300300',
        ]);
    }

    #[Test]
    public function super_admin_can_edit_account_profile_from_internal_accounts_center(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $account = $this->accountBySlug('e2e-account-c');

        OrganizationProfile::query()->withoutGlobalScopes()->updateOrCreate(
            ['account_id' => (string) $account->id],
            ['legal_name' => 'Before Org Name']
        );

        $response = $this->actingAs($actor, 'web')->put(route('internal.accounts.update', $account), [
            'name' => 'Edited E2E Account C',
            'language' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'country' => 'SA',
            'contact_phone' => '+966500000222',
            'contact_email' => 'support@edited-account-c.example.test',
            'city' => 'Jeddah',
            'legal_name' => 'Edited Account C Logistics LLC',
            'trade_name' => 'Edited Trade Name',
            'registration_number' => 'CR-999000111',
            'tax_id' => 'VAT-999000111',
            'industry' => 'supply-chain',
            'company_size' => 'large',
            'org_country' => 'SA',
            'org_city' => 'Jeddah',
            'org_email' => 'ops@edited-account-c.example.test',
            'website' => 'https://edited-account-c.example.test',
        ]);

        $response->assertRedirect(route('internal.accounts.show', $account));
        $response->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame('Edited E2E Account C', $account->name);
        $this->assertSame('support@edited-account-c.example.test', $account->contact_email);

        $profile = OrganizationProfile::query()->withoutGlobalScopes()->where('account_id', (string) $account->id)->firstOrFail();
        $this->assertSame('Edited Account C Logistics LLC', $profile->legal_name);
        $this->assertSame('Edited Trade Name', $profile->trade_name);
        $this->assertSame('ops@edited-account-c.example.test', $profile->email);

        $this->assertAuditLogRecorded(
            'account.updated',
            (string) $actor->id,
            (string) $account->id,
            'Account',
            (string) $account->id,
        );
    }

    #[Test]
    public function super_admin_can_activate_deactivate_suspend_and_unsuspend_accounts_from_internal_accounts_center(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $account = Account::factory()->individual()->create([
            'name' => 'Lifecycle Account',
            'slug' => 'lifecycle-account',
            'status' => 'pending',
        ]);
        $owner = User::factory()->owner()->create([
            'account_id' => (string) $account->id,
            'email' => 'lifecycle-owner@example.test',
            'user_type' => 'external',
        ]);

        $owner->createToken('before-activate');
        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.activate', $account), ['note' => 'Ready for onboarding'])
            ->assertRedirect(route('internal.accounts.show', $account));
        $this->assertSame('active', $account->fresh()->status);

        $owner->createToken('before-suspend');
        $this->assertGreaterThan(0, DB::table('personal_access_tokens')->where('tokenable_id', (string) $owner->id)->count());
        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.suspend', $account), ['note' => 'Fraud review'])
            ->assertRedirect(route('internal.accounts.show', $account));
        $this->assertSame('suspended', $account->fresh()->status);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', (string) $owner->id)->count());

        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.unsuspend', $account), ['note' => 'Review complete'])
            ->assertRedirect(route('internal.accounts.show', $account));
        $this->assertSame('active', $account->fresh()->status);

        $owner->createToken('before-deactivate');
        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.deactivate', $account), ['note' => 'Customer closure'])
            ->assertRedirect(route('internal.accounts.show', $account));
        $this->assertSame('closed', $account->fresh()->status);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', (string) $owner->id)->count());

        foreach ([
            'account.activated',
            'account.suspended',
            'account.unsuspended',
            'account.deactivated',
        ] as $action) {
            $this->assertAuditLogRecorded(
                $action,
                (string) $actor->id,
                (string) $account->id,
                'Account',
                (string) $account->id,
            );
        }
    }

    #[Test]
    public function support_can_read_accounts_but_cannot_see_or_submit_management_or_lifecycle_actions(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);
        $support = $this->userByEmail('e2e.internal.support@example.test');
        $account = $this->accountBySlug('e2e-account-a');

        $detail = $this->actingAs($support, 'web')
            ->get(route('internal.accounts.show', $account))
            ->assertOk();

        $detail->assertDontSee(route('internal.accounts.create'), false);
        $detail->assertDontSee(route('internal.accounts.edit', $account), false);
        $detail->assertDontSee(route('internal.accounts.suspend', $account), false);

        $this->actingAs($support, 'web')
            ->get(route('internal.accounts.create'))
            ->assertForbidden();

        $this->actingAs($support, 'web')
            ->get(route('internal.accounts.edit', $account))
            ->assertForbidden();

        $this->actingAs($support, 'web')
            ->post(route('internal.accounts.suspend', $account))
            ->assertForbidden();
    }

    #[Test]
    public function ops_readonly_and_carrier_manager_are_forbidden_from_account_management_routes(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);
        $account = $this->accountBySlug('e2e-account-a');

        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user, 'web')
                ->get(route('internal.accounts.create'))
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->get(route('internal.accounts.edit', $account))
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->post(route('internal.accounts.deactivate', $account))
                ->assertForbidden();
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_account_management_routes(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');
        $account = $this->accountBySlug('e2e-account-c');

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.accounts.create'))
            ->assertForbidden();

        $this->actingAs($externalUser, 'web')
            ->post(route('internal.accounts.store'), [
                'account_name' => 'Should Not Work',
                'account_type' => 'individual',
                'owner_name' => 'Nope',
                'owner_email' => 'should-not-work@example.test',
            ])
            ->assertForbidden();

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.accounts.edit', $account))
            ->assertForbidden();

        $this->actingAs($externalUser, 'web')
            ->post(route('internal.accounts.suspend', $account))
            ->assertForbidden();
    }

    private function userByEmail(string $email): User
    {
        return User::query()->withoutGlobalScopes()->where('email', $email)->firstOrFail();
    }

    private function accountBySlug(string $slug): Account
    {
        return Account::query()->withoutGlobalScopes()->where('slug', $slug)->firstOrFail();
    }
}
