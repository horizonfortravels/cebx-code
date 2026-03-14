<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountCreationTest extends TestCase
{
    use RefreshDatabase;

    private AccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountService();
    }

    // ─── AC: نجاح — إنشاء حساب بمعلومات صحيحة ────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_account_with_valid_data(): void
    {
        $result = $this->service->createAccount([
            'account_name' => 'شركة الشحن السريع',
            'account_type' => 'organization',
            'name'         => 'أحمد محمد',
            'email'        => 'ahmed@fastship.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->assertInstanceOf(Account::class, $result['account']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertEquals('شركة الشحن السريع', $result['account']->name);
        $this->assertEquals('organization', $result['account']->type);
        $this->assertEquals('active', $result['account']->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_unique_account_id(): void
    {
        $result1 = $this->service->createAccount([
            'account_name' => 'Company A',
            'name'         => 'User A',
            'email'        => 'a@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $result2 = $this->service->createAccount([
            'account_name' => 'Company B',
            'name'         => 'User B',
            'email'        => 'b@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->assertNotEquals($result1['account']->id, $result2['account']->id);
        $this->assertTrue(\Ramsey\Uuid\Uuid::isValid($result1['account']->id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_owner_user_linked_to_account(): void
    {
        $result = $this->service->createAccount([
            'account_name' => 'Test Co',
            'name'         => 'Owner',
            'email'        => 'owner@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->assertTrue($result['user']->is_owner);
        $this->assertEquals($result['account']->id, $result['user']->account_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defaults_to_individual_account_type(): void
    {
        $result = $this->service->createAccount([
            'account_name' => 'Solo User',
            'name'         => 'User',
            'email'        => 'solo@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->assertEquals('individual', $result['account']->type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_audit_log_on_account_creation(): void
    {
        $result = $this->service->createAccount([
            'account_name' => 'Audited Co',
            'name'         => 'Owner',
            'email'        => 'audit@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'account_id'  => $result['account']->id,
            'action'      => 'account.created',
            'entity_type' => 'Account',
            'entity_id'   => $result['account']->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_default_settings(): void
    {
        $result = $this->service->createAccount([
            'account_name' => 'Settings Co',
            'name'         => 'User',
            'email'        => 'settings@test.com',
            'password'     => 'Str0ng!Pass',
            'timezone'     => 'Asia/Riyadh',
        ]);

        $settings = $result['account']->settings;
        $this->assertEquals('USD', $settings['currency']);
        $this->assertEquals('Asia/Riyadh', $settings['timezone']);
    }

    // ─── AC: حالة حدية — اسم حساب طويل جداً ────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function slug_generation_handles_duplicates(): void
    {
        Account::factory()->create(['slug' => 'test-company']);

        $slug = Account::generateSlug('Test Company');
        $this->assertEquals('test-company-1', $slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function account_has_uuid_primary_key(): void
    {
        $account = Account::factory()->create();
        $this->assertTrue(\Ramsey\Uuid\Uuid::isValid($account->id));
    }
}
