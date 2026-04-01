<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\KycVerification;
use App\Models\OrganizationProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Support\Kyc\AccountKycStatusMapper;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalAccountsReadCenterWebTest extends TestCase
{
    use RefreshDatabase;

    private Account $individualAccount;
    private Account $organizationAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->individualAccount = $this->accountBySlug('e2e-account-a');
        $this->organizationAccount = $this->accountBySlug('e2e-account-c');

        $this->seedReadCenterFixtures();
    }

    #[Test]
    public function super_admin_and_support_can_open_accounts_index_and_details(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.accounts.index'))
                ->assertOk()
                ->assertSeeText('حسابات العملاء')
                ->assertSeeText('E2E Account A')
                ->assertSeeText('E2E Account C');

            $this->assertHasNavigationLink($index, 'internal.accounts.index');

            $this->actingAs($user, 'web')
                ->get(route('internal.accounts.show', $this->individualAccount))
                ->assertOk()
                ->assertSeeText('ملخص الحساب')
                ->assertSeeText('قاعدة الحساب الفردي')
                ->assertSeeText('E2E A Individual User');

            $this->actingAs($user, 'web')
                ->get(route('internal.accounts.show', $this->organizationAccount))
                ->assertOk()
                ->assertSeeText('ملخص الحساب')
                ->assertSeeText('ملخص المؤسسة')
                ->assertSeeText('E2E Account C Logistics LLC');
        }
    }

    #[Test]
    public function accounts_index_supports_search_and_basic_filters(): void
    {
        $user = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($user, 'web')
            ->get(route('internal.accounts.index', ['q' => 'E2E Account C']))
            ->assertOk()
            ->assertSeeText('E2E Account C')
            ->assertDontSeeText('E2E Account A');

        $this->actingAs($user, 'web')
            ->get(route('internal.accounts.index', ['type' => 'individual']))
            ->assertOk()
            ->assertSeeText('E2E Account A')
            ->assertDontSeeText('E2E Account C');

        $this->actingAs($user, 'web')
            ->get(route('internal.accounts.index', ['status' => 'suspended']))
            ->assertOk()
            ->assertSeeText('E2E Account C')
            ->assertDontSeeText('E2E Account A');

        $this->actingAs($user, 'web')
            ->get(route('internal.accounts.index', ['kyc' => KycVerification::STATUS_APPROVED]))
            ->assertOk()
            ->assertSeeText('E2E Account A')
            ->assertDontSeeText('E2E Account C');

        $restrictionResponse = $this->actingAs($user, 'web')
            ->get(route('internal.accounts.index', ['restriction' => 'restricted']))
            ->assertOk();

        if (Schema::hasTable('verification_restrictions')) {
            $restrictionResponse
                ->assertSeeText('E2E Account C')
                ->assertDontSeeText('E2E Account A');
        } else {
            $restrictionResponse
                ->assertSeeText('E2E Account A')
                ->assertSeeText('E2E Account C');
        }
    }

    #[Test]
    public function organization_and_individual_details_render_different_summaries(): void
    {
        $user = $this->userByEmail('e2e.internal.super_admin@example.test');

        $organizationDetail = $this->actingAs($user, 'web')
            ->get(route('internal.accounts.show', $this->organizationAccount))
            ->assertOk()
            ->assertSeeText('ملخص المؤسسة')
            ->assertSeeText('آخر الشحنات');

        if (Schema::hasTable('verification_restrictions')) {
            $organizationDetail->assertSeeText('تعليق الشحن الدولي');
        }

        $this->actingAs($user, 'web')
            ->get(route('internal.accounts.show', $this->individualAccount))
            ->assertOk()
            ->assertSeeText('قاعدة الحساب الفردي')
            ->assertDontSeeText('ملخص المؤسسة');
    }

    #[Test]
    public function super_admin_and_support_see_accounts_navigation_while_denied_roles_do_not(): void
    {
        $superAdminPage = $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('admin.index'))
            ->assertOk();
        $this->assertHasNavigationLink($superAdminPage, 'internal.accounts.index');

        $supportPage = $this->actingAs($this->userByEmail('e2e.internal.support@example.test'), 'web')
            ->get(route('internal.home'))
            ->assertOk();
        $this->assertHasNavigationLink($supportPage, 'internal.accounts.index');

        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertMissingNavigationLink($page, 'internal.accounts.index');
        }
    }

    #[Test]
    public function ops_readonly_and_carrier_manager_are_forbidden_from_accounts_routes(): void
    {
        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.accounts.index'))
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.accounts.show', $this->organizationAccount))
            );
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_accounts_routes(): void
    {
        $user = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->actingAs($user, 'web')
            ->get(route('internal.accounts.index'))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');

        $this->actingAs($user, 'web')
            ->get(route('internal.accounts.show', $this->individualAccount))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');
    }

    private function seedReadCenterFixtures(): void
    {
        $individualOwner = $this->userByEmail('e2e.a.individual@example.test');
        $organizationOwner = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->individualAccount->forceFill([
            'status' => 'active',
            'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_APPROVED),
        ])->save();

        $this->organizationAccount->forceFill([
            'status' => 'suspended',
            'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_PENDING),
        ])->save();

        OrganizationProfile::query()->updateOrCreate(
            ['account_id' => (string) $this->organizationAccount->id],
            [
                'legal_name' => 'E2E Account C Logistics LLC',
                'trade_name' => 'E2E Account C Trade',
                'registration_number' => 'CR-200200200',
                'industry' => 'logistics',
                'company_size' => 'medium',
                'country' => 'SA',
                'city' => 'Riyadh',
                'email' => 'ops@e2e-account-c.example.test',
            ]
        );

        KycVerification::query()->create([
            'account_id' => (string) $this->individualAccount->id,
            'status' => KycVerification::STATUS_APPROVED,
            'verification_type' => 'individual',
            'verification_level' => 'basic',
            'review_count' => 1,
            'reviewed_at' => now(),
            'expires_at' => now()->addMonths(6),
        ]);

        KycVerification::query()->create([
            'account_id' => (string) $this->organizationAccount->id,
            'status' => KycVerification::STATUS_PENDING,
            'verification_type' => 'organization',
            'verification_level' => 'enhanced',
            'required_documents' => ['commercial_registration' => 'Commercial Registration'],
            'submitted_documents' => ['commercial_registration' => 'kyc/e2e-account-c.pdf'],
            'review_count' => 0,
            'submitted_at' => now()->subDay(),
        ]);

        if (Schema::hasTable('verification_restrictions')) {
            VerificationRestriction::query()->updateOrCreate(
                ['restriction_key' => 'pending_international_shipping'],
                [
                    'name' => 'تعليق الشحن الدولي',
                    'description' => 'يظل الشحن الدولي محدودًا حتى اكتمال مراجعة التحقق.',
                    'applies_to_statuses' => [KycVerification::STATUS_PENDING],
                    'restriction_type' => VerificationRestriction::TYPE_BLOCK_FEATURE,
                    'feature_key' => 'international_shipping',
                    'is_active' => true,
                ]
            );
        }

        BillingWallet::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'account_id' => (string) $this->individualAccount->id,
                'currency' => 'USD',
            ],
            [
                'available_balance' => 950.00,
                'reserved_balance' => 50.00,
                'total_credited' => 1000.00,
                'total_debited' => 0.00,
                'status' => 'active',
                'allow_negative' => false,
            ]
        );

        BillingWallet::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'account_id' => (string) $this->organizationAccount->id,
                'currency' => 'USD',
            ],
            [
                'available_balance' => 5000.00,
                'reserved_balance' => 750.00,
                'total_credited' => 5750.00,
                'total_debited' => 0.00,
                'status' => 'active',
                'allow_negative' => false,
            ]
        );

        Shipment::factory()->delivered()->create([
            'account_id' => (string) $this->individualAccount->id,
            'user_id' => (string) $individualOwner->id,
            'created_by' => (string) $individualOwner->id,
            'tracking_number' => 'I2A-IND-0001',
        ]);

        Shipment::factory()->inTransit()->count(2)->create([
            'account_id' => (string) $this->organizationAccount->id,
            'user_id' => (string) $organizationOwner->id,
            'created_by' => (string) $organizationOwner->id,
        ]);
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function accountBySlug(string $slug): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function assertHasNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertSee('href="' . route($routeName) . '"', false);
    }

    private function assertMissingNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertDontSee('href="' . route($routeName) . '"', false);
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSeeText('هذه الصفحة ليست ضمن دورك الحالي');
    }
}
