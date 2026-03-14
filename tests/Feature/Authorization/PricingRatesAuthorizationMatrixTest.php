<?php

namespace Tests\Feature\Authorization;

use App\Models\Account;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PricingRatesAuthorizationMatrixTest extends TestCase
{
    #[Test]
    public function external_same_tenant_with_read_permissions_can_access_pricing_and_quote_endpoints(): void
    {
        $this->ensureRateQuoteTablesExist();
        $this->skipIfMissingTables(['pricing_breakdowns']);

        $account = Account::factory()->create();
        $user = $this->createExternalUser($account);
        $this->grantTenantPermissions($user, ['pricing.read', 'quotes.read'], 'pricing_rates_reader');

        $shipment = $this->createShipment((string) $account->id, (string) $user->id);
        $quote = $this->createRateQuote((string) $account->id, (string) $user->id, (string) $shipment->id);
        $this->createRateOption((string) $quote->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/pricing/breakdowns')->assertOk();
        $this->getJson('/api/v1/rate-quotes/' . $quote->id)->assertOk();
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/offers')->assertOk();
    }

    #[Test]
    public function external_same_tenant_missing_read_permissions_gets_403(): void
    {
        $this->ensureRateQuoteTablesExist();
        $this->skipIfMissingTables(['pricing_breakdowns']);

        $account = Account::factory()->create();
        $user = $this->createExternalUser($account);
        $shipment = $this->createShipment((string) $account->id, (string) $user->id);
        $quote = $this->createRateQuote((string) $account->id, (string) $user->id, (string) $shipment->id);
        $this->createRateOption((string) $quote->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/pricing/breakdowns')->assertStatus(403);
        $this->getJson('/api/v1/rate-quotes/' . $quote->id)->assertStatus(403);
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/offers')->assertStatus(403);
    }

    #[Test]
    public function external_cross_tenant_rate_quote_id_returns_404(): void
    {
        $this->ensureRateQuoteTablesExist();

        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        $userA = $this->createExternalUser($accountA);
        $userB = $this->createExternalUser($accountB);

        $this->grantTenantPermissions($userA, ['quotes.read'], 'quote_reader_a');

        $shipmentForAccountB = $this->createShipment((string) $accountB->id, (string) $userB->id);
        $quoteForAccountB = $this->createRateQuote((string) $accountB->id, (string) $userB->id, (string) $shipmentForAccountB->id);
        $this->createRateOption((string) $quoteForAccountB->id);

        Sanctum::actingAs($userA);

        $this->getJson('/api/v1/rate-quotes/' . $quoteForAccountB->id)->assertNotFound();
        $this->getJson('/api/v1/shipments/' . $shipmentForAccountB->id . '/offers')->assertNotFound();
    }

    #[Test]
    public function manage_endpoints_require_manage_permissions(): void
    {
        $this->ensureRateQuoteTablesExist();

        $account = Account::factory()->create();
        $user = $this->createExternalUser($account);

        $this->grantTenantPermissions($user, ['pricing.read', 'quotes.read'], 'pricing_rates_read_only');

        $quote = $this->createRateQuote((string) $account->id, (string) $user->id);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/pricing/rule-sets', [
            'name' => 'Read only should fail',
        ])->assertStatus(403);

        $this->postJson('/api/v1/rate-quotes/' . $quote->id . '/select', [
            'strategy' => 'cheapest',
        ])->assertStatus(403);
    }

    private function createExternalUser(Account $account): User
    {
        return User::factory()->create([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);
    }

    private function createShipment(string $accountId, string $userId): Shipment
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $accountId,
            'user_id' => $userId,
            'created_by' => $userId,
            'status' => Shipment::STATUS_RATED,
        ]);

        return Shipment::query()->whereKey($shipment->id)->firstOrFail();
    }

    private function createRateQuote(string $accountId, string $userId, ?string $shipmentId = null): RateQuote
    {
        $quoteId = (string) Str::uuid();
        $payload = ['id' => $quoteId];

        if (Schema::hasColumn('rate_quotes', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('rate_quotes', 'shipment_id')) {
            $payload['shipment_id'] = $shipmentId;
        }
        if (Schema::hasColumn('rate_quotes', 'origin_country')) {
            $payload['origin_country'] = 'SA';
        }
        if (Schema::hasColumn('rate_quotes', 'origin_city')) {
            $payload['origin_city'] = 'Riyadh';
        }
        if (Schema::hasColumn('rate_quotes', 'destination_country')) {
            $payload['destination_country'] = 'SA';
        }
        if (Schema::hasColumn('rate_quotes', 'destination_city')) {
            $payload['destination_city'] = 'Jeddah';
        }
        if (Schema::hasColumn('rate_quotes', 'total_weight')) {
            $payload['total_weight'] = 1.0;
        }
        if (Schema::hasColumn('rate_quotes', 'chargeable_weight')) {
            $payload['chargeable_weight'] = 1.0;
        }
        if (Schema::hasColumn('rate_quotes', 'parcels_count')) {
            $payload['parcels_count'] = 1;
        }
        if (Schema::hasColumn('rate_quotes', 'is_cod')) {
            $payload['is_cod'] = false;
        }
        if (Schema::hasColumn('rate_quotes', 'cod_amount')) {
            $payload['cod_amount'] = 0;
        }
        if (Schema::hasColumn('rate_quotes', 'is_insured')) {
            $payload['is_insured'] = false;
        }
        if (Schema::hasColumn('rate_quotes', 'insurance_value')) {
            $payload['insurance_value'] = 0;
        }
        if (Schema::hasColumn('rate_quotes', 'currency')) {
            $payload['currency'] = 'SAR';
        }
        if (Schema::hasColumn('rate_quotes', 'status')) {
            $payload['status'] = 'completed';
        }
        if (Schema::hasColumn('rate_quotes', 'options_count')) {
            $payload['options_count'] = 0;
        }
        if (Schema::hasColumn('rate_quotes', 'expires_at')) {
            $payload['expires_at'] = now()->addMinutes(30);
        }
        if (Schema::hasColumn('rate_quotes', 'is_expired')) {
            $payload['is_expired'] = false;
        }
        if (Schema::hasColumn('rate_quotes', 'selected_option_id')) {
            $payload['selected_option_id'] = null;
        }
        if (Schema::hasColumn('rate_quotes', 'correlation_id')) {
            $payload['correlation_id'] = 'RQ-MATRIX-' . strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));
        }
        if (Schema::hasColumn('rate_quotes', 'request_metadata')) {
            $payload['request_metadata'] = json_encode([]);
        }
        if (Schema::hasColumn('rate_quotes', 'error_message')) {
            $payload['error_message'] = null;
        }
        if (Schema::hasColumn('rate_quotes', 'requested_by')) {
            $payload['requested_by'] = $userId;
        }
        if (Schema::hasColumn('rate_quotes', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('rate_quotes', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table('rate_quotes')->insert($payload);

        if ($shipmentId !== null && Schema::hasColumn('shipments', 'rate_quote_id')) {
            DB::table('shipments')
                ->where('id', $shipmentId)
                ->update(['rate_quote_id' => $quoteId]);
        }

        return RateQuote::withoutGlobalScopes()
            ->where('id', $quoteId)
            ->firstOrFail();
    }

    private function createRateOption(string $quoteId): RateOption
    {
        return RateOption::query()->create([
            'rate_quote_id' => $quoteId,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'service_code' => 'INTERNATIONAL_PRIORITY',
            'service_name' => 'FedEx International Priority',
            'net_rate' => 100,
            'fuel_surcharge' => 5,
            'other_surcharges' => 0,
            'total_net_rate' => 105,
            'markup_amount' => 10,
            'service_fee' => 2,
            'retail_rate_before_rounding' => 117,
            'retail_rate' => 117,
            'profit_margin' => 12,
            'currency' => 'USD',
            'estimated_days_min' => 2,
            'estimated_days_max' => 3,
            'pricing_breakdown' => ['stage' => 'retail'],
            'rule_evaluation_log' => ['pricing_stage' => 'retail'],
            'is_available' => true,
            'unavailable_reason' => null,
        ]);
    }

    private function ensureRateQuoteTablesExist(): void
    {
        if (!Schema::hasTable('rate_quotes')) {
            Schema::create('rate_quotes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('account_id', 36);
                $table->string('shipment_id', 36)->nullable();
                $table->string('origin_country', 2)->default('SA');
                $table->string('origin_city', 100)->nullable();
                $table->string('destination_country', 2)->default('SA');
                $table->string('destination_city', 100)->nullable();
                $table->decimal('total_weight', 10, 3)->default(1);
                $table->decimal('chargeable_weight', 10, 3)->nullable();
                $table->integer('parcels_count')->default(1);
                $table->boolean('is_cod')->default(false);
                $table->decimal('cod_amount', 15, 2)->default(0);
                $table->boolean('is_insured')->default(false);
                $table->decimal('insurance_value', 15, 2)->default(0);
                $table->string('currency', 3)->default('SAR');
                $table->string('status', 32)->default('completed');
                $table->integer('options_count')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_expired')->default(false);
                $table->string('selected_option_id', 36)->nullable();
                $table->string('correlation_id', 100)->nullable();
                $table->json('request_metadata')->nullable();
                $table->string('error_message', 500)->nullable();
                $table->string('requested_by', 36)->nullable();
                $table->timestamps();

                $table->index(['account_id', 'status']);
                $table->index('shipment_id');
            });
        }

        if (!Schema::hasTable('rate_options')) {
            Schema::create('rate_options', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('rate_quote_id', 36);
                $table->string('carrier_code', 50)->nullable();
                $table->string('carrier_name', 100)->nullable();
                $table->string('service_code', 50)->nullable();
                $table->string('service_name', 200)->nullable();
                $table->decimal('net_rate', 15, 2)->default(0);
                $table->decimal('fuel_surcharge', 15, 2)->default(0);
                $table->decimal('other_surcharges', 15, 2)->default(0);
                $table->decimal('total_net_rate', 15, 2)->default(0);
                $table->decimal('markup_amount', 15, 2)->default(0);
                $table->decimal('service_fee', 15, 2)->default(0);
                $table->decimal('retail_rate_before_rounding', 15, 2)->default(0);
                $table->decimal('retail_rate', 15, 2)->default(0);
                $table->decimal('profit_margin', 15, 2)->default(0);
                $table->string('currency', 3)->default('SAR');
                $table->integer('estimated_days_min')->nullable();
                $table->integer('estimated_days_max')->nullable();
                $table->timestamp('estimated_delivery_at')->nullable();
                $table->boolean('is_cheapest')->default(false);
                $table->boolean('is_fastest')->default(false);
                $table->boolean('is_best_value')->default(false);
                $table->boolean('is_recommended')->default(false);
                $table->string('pricing_rule_id', 36)->nullable();
                $table->json('pricing_breakdown')->nullable();
                $table->json('rule_evaluation_log')->nullable();
                $table->boolean('is_available')->default(true);
                $table->string('unavailable_reason', 300)->nullable();
                $table->timestamps();

                $table->index('rate_quote_id');
            });
        }
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
}

