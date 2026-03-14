<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Services\DataMaskingService;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * FR-IAM-012: Financial Data Masking — Unit Tests (30 tests)
 */
class DataMaskingTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    protected Account $account;
    protected User $owner;
    protected User $printer;    // shipments:print only — NO financial access
    protected User $accountant; // financial:view + financial:profit.view + financial:cards.view
    protected User $viewer;     // financial:view only — NO profit, NO cards

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => true,
        ]);

        // Printer role — no financial permissions
        $printerRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['shipments.read', 'shipments.print_label', 'orders.read'],
            'printer_role'
        );
        $this->printer = $this->createUserWithRole((string) $this->account->id, (string) $printerRole->id, [
            'is_owner' => false,
        ]);

        // Accountant role — full financial permissions
        $accountantRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            [
                'financial.view',
                'financial.profit.view',
                'financial.cards.view',
                'financial.invoices.view',
                'financial.ledger.view',
            ],
            'accountant_role'
        );
        $this->accountant = $this->createUserWithRole((string) $this->account->id, (string) $accountantRole->id, [
            'is_owner' => false,
        ]);

        // Viewer role — financial:view only (can see totals, NOT profit/cards)
        $viewerRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['financial.view', 'financial.invoices.view'],
            'viewer_role'
        );
        $this->viewer = $this->createUserWithRole((string) $this->account->id, (string) $viewerRole->id, [
            'is_owner' => false,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // Card Number Masking
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_16_digit_card_number()
    {
        $result = DataMaskingService::maskCardNumber('4111111111111234');
        $this->assertStringEndsWith('1234', $result);
        $this->assertStringContainsString('••••', $result);
        $this->assertStringNotContainsString('4111', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_card_number_with_spaces()
    {
        $result = DataMaskingService::maskCardNumber('4111 1111 1111 1234');
        $this->assertStringEndsWith('1234', $result);
        $this->assertStringContainsString('••••', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_card_number_with_dashes()
    {
        $result = DataMaskingService::maskCardNumber('4111-1111-1111-1234');
        $this->assertStringEndsWith('1234', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_15_digit_amex()
    {
        $result = DataMaskingService::maskCardNumber('371449635398431');
        $this->assertStringEndsWith('8431', $result);
        $this->assertStringContainsString('•', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_13_digit_card()
    {
        $result = DataMaskingService::maskCardNumber('4222222221234');
        $this->assertStringEndsWith('1234', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_short_card_number()
    {
        $result = DataMaskingService::maskCardNumber('123');
        $this->assertEquals('•••', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_null_card_number()
    {
        $this->assertNull(DataMaskingService::maskCardNumber(null));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_card_number()
    {
        $this->assertEquals('', DataMaskingService::maskCardNumber(''));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_last_four_digits()
    {
        $this->assertEquals('1234', DataMaskingService::lastFourDigits('4111111111111234'));
        $this->assertEquals('8431', DataMaskingService::lastFourDigits('371449635398431'));
        $this->assertNull(DataMaskingService::lastFourDigits(null));
        $this->assertNull(DataMaskingService::lastFourDigits('12'));
    }

    // ═══════════════════════════════════════════════════════════════
    // IBAN Masking
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_iban()
    {
        $result = DataMaskingService::maskIban('SA0380000000608010167519');
        $this->assertStringStartsWith('SA03', $result);
        $this->assertStringEndsWith('7519', $result);
        $this->assertStringContainsString('•', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_short_iban()
    {
        $result = DataMaskingService::maskIban('DE1234');
        $this->assertStringStartsWith('DE', $result);
        $this->assertStringContainsString('•', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_null_iban()
    {
        $this->assertNull(DataMaskingService::maskIban(null));
    }

    // ═══════════════════════════════════════════════════════════════
    // Email Masking
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_email()
    {
        $result = DataMaskingService::maskEmail('ahmad@example.com');
        $this->assertStringContainsString('@example.com', $result);
        $this->assertStringStartsWith('a', $result);
        $this->assertStringContainsString('•', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_short_email()
    {
        $result = DataMaskingService::maskEmail('ab@x.com');
        $this->assertStringContainsString('@x.com', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // Phone Masking
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_masks_phone()
    {
        $result = DataMaskingService::maskPhone('+966501234567');
        $this->assertStringEndsWith('4567', $result);
        $this->assertStringContainsString('•', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // Financial Field Filtering — Permission-Based
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_sees_all_financial_data()
    {
        $data = $this->sampleFinancialData();
        $result = DataMaskingService::filterFinancialData($data, $this->owner);

        $this->assertEquals(150.00, $result['net_rate']);
        $this->assertEquals(200.00, $result['retail_rate']);
        $this->assertEquals(50.00, $result['profit']);
        $this->assertEquals(500.00, $result['total_amount']);
        $this->assertEquals('4111111111111234', $result['card_number']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function accountant_sees_all_financial_data()
    {
        $data = $this->sampleFinancialData();
        $result = DataMaskingService::filterFinancialData($data, $this->accountant);

        $this->assertEquals(150.00, $result['net_rate']);
        $this->assertEquals(200.00, $result['retail_rate']);
        $this->assertEquals(50.00, $result['profit']);
        $this->assertEquals(500.00, $result['total_amount']);
        $this->assertEquals('4111111111111234', $result['card_number']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function viewer_sees_totals_but_not_profit()
    {
        $data = $this->sampleFinancialData();
        $result = DataMaskingService::filterFinancialData($data, $this->viewer);

        // Can see general financial
        $this->assertEquals(500.00, $result['total_amount']);
        $this->assertEquals(50.00, $result['tax_amount']);

        // Cannot see profit-sensitive
        $this->assertNull($result['net_rate']);
        $this->assertNull($result['retail_rate']);
        $this->assertNull($result['profit']);
        $this->assertNull($result['pricing_breakdown']);

        // Cannot see card data (no cards permission)
        $this->assertNotEquals('4111111111111234', $result['card_number']);
        $this->assertStringContainsString('•', $result['card_number']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function printer_sees_no_financial_data()
    {
        $data = $this->sampleFinancialData();
        $result = DataMaskingService::filterFinancialData($data, $this->printer);

        // No general financial
        $this->assertNull($result['total_amount']);
        $this->assertNull($result['tax_amount']);
        $this->assertNull($result['subtotal']);

        // No profit data
        $this->assertNull($result['net_rate']);
        $this->assertNull($result['profit']);

        // Card data is masked (not null — shows last 4)
        $this->assertNotEquals('4111111111111234', $result['card_number']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function null_user_sees_nothing()
    {
        $data = $this->sampleFinancialData();
        $result = DataMaskingService::filterFinancialData($data, null);

        $this->assertNull($result['net_rate']);
        $this->assertNull($result['total_amount']);
        $this->assertNull($result['profit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function replace_with_stars_option()
    {
        $data = $this->sampleFinancialData();
        $result = DataMaskingService::filterFinancialData($data, $this->printer, false);

        $this->assertEquals('***', $result['net_rate']);
        $this->assertEquals('***', $result['total_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_financial_fields_are_untouched()
    {
        $data = array_merge($this->sampleFinancialData(), [
            'tracking_number' => 'TRACK123',
            'recipient_name'  => 'Ahmad',
            'weight'          => 2.5,
        ]);

        $result = DataMaskingService::filterFinancialData($data, $this->printer);

        // Non-financial fields should be untouched
        $this->assertEquals('TRACK123', $result['tracking_number']);
        $this->assertEquals('Ahmad', $result['recipient_name']);
        $this->assertEquals(2.5, $result['weight']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Collection Filtering
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_collection()
    {
        $items = [
            $this->sampleFinancialData(),
            array_merge($this->sampleFinancialData(), ['profit' => 75.00]),
        ];

        $result = DataMaskingService::filterFinancialCollection($items, $this->printer);

        $this->assertCount(2, $result);
        $this->assertNull($result[0]['profit']);
        $this->assertNull($result[1]['profit']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Permission Checks
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_view_profit_data_check()
    {
        $this->assertTrue(DataMaskingService::canViewProfitData($this->owner));
        $this->assertTrue(DataMaskingService::canViewProfitData($this->accountant));
        $this->assertFalse(DataMaskingService::canViewProfitData($this->viewer));
        $this->assertFalse(DataMaskingService::canViewProfitData($this->printer));
        $this->assertFalse(DataMaskingService::canViewProfitData(null));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_view_financial_data_check()
    {
        $this->assertTrue(DataMaskingService::canViewFinancialData($this->owner));
        $this->assertTrue(DataMaskingService::canViewFinancialData($this->accountant));
        $this->assertTrue(DataMaskingService::canViewFinancialData($this->viewer));
        $this->assertFalse(DataMaskingService::canViewFinancialData($this->printer));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_view_card_data_check()
    {
        $this->assertTrue(DataMaskingService::canViewCardData($this->owner));
        $this->assertTrue(DataMaskingService::canViewCardData($this->accountant));
        $this->assertFalse(DataMaskingService::canViewCardData($this->viewer));
        $this->assertFalse(DataMaskingService::canViewCardData($this->printer));
    }

    // ═══════════════════════════════════════════════════════════════
    // Visibility Map
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function visibility_map_for_owner()
    {
        $map = DataMaskingService::visibilityMap($this->owner);

        $this->assertTrue($map['financial_general']);
        $this->assertTrue($map['financial_profit']);
        $this->assertTrue($map['financial_cards']);
        $this->assertEmpty($map['masked_fields']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function visibility_map_for_printer()
    {
        $map = DataMaskingService::visibilityMap($this->printer);

        $this->assertFalse($map['financial_general']);
        $this->assertFalse($map['financial_profit']);
        $this->assertFalse($map['financial_cards']);
        $this->assertNotEmpty($map['masked_fields']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Audit Log Sanitization
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sanitizes_card_numbers_in_audit()
    {
        $values = [
            'card_number' => '4111111111111234',
            'name'        => 'Test',
        ];

        $sanitized = DataMaskingService::sanitizeForAuditLog($values);

        $this->assertStringContainsString('•', $sanitized['card_number']);
        $this->assertStringEndsWith('1234', $sanitized['card_number']);
        $this->assertEquals('Test', $sanitized['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_redacts_passwords_in_audit()
    {
        $values = [
            'password' => 'secret123',
            'token'    => 'abc-token-xyz',
            'name'     => 'Test',
        ];

        $sanitized = DataMaskingService::sanitizeForAuditLog($values);

        $this->assertEquals('[REDACTED]', $sanitized['password']);
        $this->assertEquals('[REDACTED]', $sanitized['token']);
        $this->assertEquals('Test', $sanitized['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sanitizes_iban_in_audit()
    {
        $values = ['iban' => 'SA0380000000608010167519'];

        $sanitized = DataMaskingService::sanitizeForAuditLog($values);

        $this->assertStringStartsWith('SA03', $sanitized['iban']);
        $this->assertStringContainsString('•', $sanitized['iban']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Helper
    // ═══════════════════════════════════════════════════════════════

    private function sampleFinancialData(): array
    {
        return [
            // Profit-sensitive fields
            'net_rate'           => 150.00,
            'retail_rate'        => 200.00,
            'profit'             => 50.00,
            'pricing_breakdown'  => ['base' => 100, 'fuel' => 30, 'vat' => 20],
            'carrier_cost'       => 120.00,

            // General financial fields
            'total_amount'       => 500.00,
            'subtotal'           => 450.00,
            'tax_amount'         => 50.00,
            'cod_amount'         => 200.00,

            // Card-sensitive fields
            'card_number'        => '4111111111111234',
            'card_holder_name'   => 'Ahmad Test',
            'card_expiry'        => '12/28',
            'iban'               => 'SA0380000000608010167519',
        ];
    }
}
