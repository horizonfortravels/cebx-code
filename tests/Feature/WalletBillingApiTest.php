<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AuditService;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * FR-IAM-017 + FR-IAM-019 + FR-IAM-020: Integration Tests (20 tests)
 */
class WalletBillingApiTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    /**
     * DDL compatibility helpers in this class require running without transactions.
     *
     * @var array<int, string|null>
     */
    protected $connectionsToTransact = [];

    protected Account $account;
    protected User $owner;
    protected User $financeUser;
    protected User $member;
    private static int $emailCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        AuditService::resetRequestId();
        $this->ensureWalletBillingSchemaCompatibility();

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => true,
            'user_type'  => 'external',
            'email'      => $this->uniqueEmail('owner'),
        ]);
        $this->grantTenantPermissions($this->owner, [
            'wallet.balance',
            'wallet.ledger',
            'wallet.topup',
            'wallet.configure',
            'wallet.manage',
            'billing.view',
            'billing.manage',
        ], 'wallet_owner');

        $this->financeUser = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => false,
            'user_type'  => 'external',
            'email'      => $this->uniqueEmail('finance'),
        ]);
        $this->grantTenantPermissions($this->financeUser, [
            'wallet.balance',
            'wallet.ledger',
            'wallet.topup',
            'wallet.configure',
            'billing.view',
            'billing.manage',
        ], 'wallet_finance');

        $this->member = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => false,
            'user_type'  => 'external',
            'email'      => $this->uniqueEmail('member'),
        ]);
    }

    private function uniqueEmail(string $prefix): string
    {
        self::$emailCounter++;

        return sprintf('%s-%d-%s@example.test', $prefix, self::$emailCounter, Str::lower((string) Str::uuid()));
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /wallet
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_gets_full_wallet_details()
    {
        Wallet::factory()->withBalance(500)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/wallet');

        $response->assertOk()
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.available_balance', '500.00');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_without_permission_gets_forbidden_for_wallet()
    {
        Wallet::factory()->withBalance(500)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/wallet');

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function wallet_auto_created_on_first_access()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/wallet');

        $response->assertOk();
        $this->assertDatabaseHas('wallets', ['account_id' => $this->account->id]);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /wallet/topup
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_topup()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/wallet/topup', [
                'amount'       => 500,
                'reference_id' => 'PAY-001',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', '500.00');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function finance_user_can_topup()
    {
        $response = $this->actingAs($this->financeUser)
            ->postJson('/api/v1/wallet/topup', [
                'amount'       => 200,
                'reference_id' => 'PAY-002',
            ]);

        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_cannot_topup()
    {
        $response = $this->actingAs($this->member)
            ->postJson('/api/v1/wallet/topup', [
                'amount'       => 100,
                'reference_id' => 'PAY-X',
            ]);

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function topup_validates_amount()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/wallet/topup', [
                'amount'       => -10,
                'reference_id' => 'PAY-NEG',
            ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function topup_creates_audit_log()
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/wallet/topup', [
                'amount'       => 100,
                'reference_id' => 'PAY-AL',
            ]);

        $query = AuditLog::withoutGlobalScopes();
        if (Schema::hasColumn('audit_logs', 'action')) {
            $query->where('action', 'wallet.topup');
        } else {
            $query->where('event', 'wallet.topup');
        }

        $log = $query->first();

        $this->assertNotNull($log);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /wallet/ledger
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function finance_user_can_view_ledger()
    {
        Wallet::factory()->withBalance(1000)->create(['account_id' => $this->account->id]);

        // Create some entries
        $this->actingAs($this->owner)->postJson('/api/v1/wallet/topup', ['amount' => 500, 'reference_id' => 'R1']);

        $response = $this->actingAs($this->financeUser)
            ->getJson('/api/v1/wallet/ledger');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'wallet',
                    'entries' => [['id', 'type', 'type_label', 'amount', 'running_balance']],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_cannot_view_ledger()
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/wallet/ledger');

        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════
    // PUT /wallet/threshold
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_set_threshold()
    {
        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/wallet/threshold', ['threshold' => 200]);

        $response->assertOk()
            ->assertJsonPath('data.low_balance_threshold', '200.00');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_cannot_set_threshold()
    {
        $response = $this->actingAs($this->member)
            ->putJson('/api/v1/wallet/threshold', ['threshold' => 100]);

        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════
    // Billing: Payment Methods
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_list_payment_methods()
    {
        PaymentMethod::factory()->count(2)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/billing/methods');

        $response->assertOk()
            ->assertJsonPath('meta.count', 2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_cannot_list_payment_methods()
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/billing/methods');

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_add_payment_method()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/billing/methods', [
                'provider'        => 'visa',
                'last_four'       => '4242',
                'expiry_month'    => '12',
                'expiry_year'     => '2028',
                'cardholder_name' => 'Ahmed Test',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.provider', 'visa')
            ->assertJsonPath('data.last_four', '4242')
            ->assertJsonPath('data.is_masked', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_remove_payment_method()
    {
        $method = PaymentMethod::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/billing/methods/{$method->id}");

        $response->assertOk();
        $this->assertSoftDeleted('payment_methods', ['id' => $method->id]);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-IAM-020: Disabled Account Masking
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function suspended_account_shows_masked_cards()
    {
        $this->account->update(['status' => 'suspended']);
        PaymentMethod::factory()->create([
            'account_id' => $this->account->id,
            'provider'   => 'visa',
            'last_four'  => '9999',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/billing/methods');

        $response->assertOk();
        $methods = $response->json('data');
        $this->assertTrue($methods[0]['is_masked']);
        $this->assertEquals('••••', $methods[0]['last_four']);
        $this->assertEquals('account_disabled', $methods[0]['mask_reason']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_add_card_to_suspended_account()
    {
        $this->account->update(['status' => 'suspended']);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/billing/methods', [
                'provider'  => 'visa',
                'last_four' => '1234',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_ACCOUNT_DISABLED');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /wallet/permissions
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function permissions_endpoint_returns_list()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/wallet/permissions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'wallet.balance', 'wallet.ledger', 'wallet.topup',
                    'wallet.configure', 'billing.view', 'billing.manage',
                ],
            ]);
    }

    private function ensureWalletBillingSchemaCompatibility(): void
    {
        if (Schema::hasTable('wallets')) {
            Schema::table('wallets', function (Blueprint $table): void {
                if (!Schema::hasColumn('wallets', 'currency')) {
                    $table->string('currency', 3)->default('SAR')->after('account_id');
                }

                if (!Schema::hasColumn('wallets', 'locked_balance')) {
                    $table->decimal('locked_balance', 15, 2)->default(0)->after('available_balance');
                }

                if (!Schema::hasColumn('wallets', 'low_balance_threshold')) {
                    $table->decimal('low_balance_threshold', 15, 2)->nullable()->after('locked_balance');
                }

                if (!Schema::hasColumn('wallets', 'status')) {
                    $table->string('status', 32)->default('active')->after('low_balance_threshold');
                }
            });
        }

        if (Schema::hasTable('wallet_ledger_entries')) {
            Schema::table('wallet_ledger_entries', function (Blueprint $table): void {
                if (!Schema::hasColumn('wallet_ledger_entries', 'type')) {
                    $table->string('type', 32)->nullable()->after('wallet_id');
                }

                if (!Schema::hasColumn('wallet_ledger_entries', 'actor_user_id')) {
                    $table->uuid('actor_user_id')->nullable()->after('reference_id');
                }

                if (!Schema::hasColumn('wallet_ledger_entries', 'description')) {
                    $table->string('description', 500)->nullable()->after('actor_user_id');
                }
            });
        }

        if (!Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('account_id')->index();
                $table->string('type', 32)->default('card');
                $table->string('label', 100)->nullable();
                $table->string('provider', 50)->nullable();
                $table->string('last_four', 4)->nullable();
                $table->string('expiry_month', 2)->nullable();
                $table->string('expiry_year', 4)->nullable();
                $table->string('cardholder_name', 150)->nullable();
                $table->text('gateway_token')->nullable();
                $table->string('gateway_customer_id', 255)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_masked_override')->default(false);
                $table->uuid('added_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
}
