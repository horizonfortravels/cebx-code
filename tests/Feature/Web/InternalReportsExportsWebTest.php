<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class InternalReportsExportsWebTest extends SeededReadOnlyWebTestCase
{
    /**
     * @var array<string, array{route: string, dashboard: string, headers: array<int, string>, excluded: array<int, string>}>
     */
    private array $exports = [
        'shipments' => [
            'route' => 'internal.reports.shipments.export',
            'dashboard' => 'internal.reports.shipments',
            'headers' => [
                'shipment_reference',
                'account_name',
                'account_slug',
                'account_type',
                'status',
                'carrier',
                'service',
                'tracking_summary',
                'source',
                'international',
                'cod',
                'dangerous_goods',
                'timeline_events',
                'documents_available',
                'created_at',
            ],
            'excluded' => ['public_tracking_token', 'label_url', 'delivery_instructions'],
        ],
        'kyc' => [
            'route' => 'internal.reports.kyc.export',
            'dashboard' => 'internal.reports.kyc',
            'headers' => [
                'account_name',
                'account_slug',
                'account_type',
                'account_status',
                'kyc_status',
                'submitted_documents',
                'required_documents',
                'blocked_shipments',
                'restricted',
                'shipment_limit',
                'daily_shipment_limit',
                'international_shipping_blocked',
                'review_summary',
                'reviewed_at',
            ],
            'excluded' => ['review_notes', 'stored_path', 'file_hash'],
        ],
        'billing' => [
            'route' => 'internal.reports.billing.export',
            'dashboard' => 'internal.reports.billing',
            'headers' => [
                'account_name',
                'account_slug',
                'account_type',
                'wallet_status',
                'wallet_source',
                'currency',
                'current_balance',
                'reserved_balance',
                'available_balance',
                'low_balance',
                'active_holds',
                'topups_confirmed_24h',
                'kyc_status',
                'restriction_summary',
            ],
            'excluded' => ['checkout_url', 'payment_reference', 'gateway_metadata'],
        ],
        'compliance' => [
            'route' => 'internal.reports.compliance.export',
            'dashboard' => 'internal.reports.compliance',
            'headers' => [
                'shipment_reference',
                'account_name',
                'account_slug',
                'account_type',
                'declaration_status',
                'review_state',
                'dg_declared',
                'dangerous_goods',
                'waiver_status',
                'declared_at',
                'updated_at',
                'restriction_summary',
                'latest_audit_summary',
            ],
            'excluded' => ['waiver_hash_snapshot', 'waiver_text_snapshot', 'additional_info'],
        ],
        'carriers' => [
            'route' => 'internal.reports.carriers.export',
            'dashboard' => 'internal.reports.carriers',
            'headers' => [
                'carrier_name',
                'provider_key',
                'status',
                'configuration_state',
                'mode',
                'health_status',
                'last_connection_test',
                'shipper_account_summary',
                'recent_activity_summary',
                'last_error_summary',
            ],
            'excluded' => ['api_key', 'api_secret', 'client_secret', 'passkey', 'token', 'password'],
        ],
        'tickets' => [
            'route' => 'internal.reports.tickets.export',
            'dashboard' => 'internal.reports.tickets',
            'headers' => [
                'ticket_number',
                'subject',
                'category',
                'priority',
                'status',
                'account_name',
                'account_slug',
                'linked_shipment_reference',
                'requester_name',
                'assignee_name',
                'recent_activity_summary',
                'replies_count',
                'workflow_activity_summary',
                'updated_at',
            ],
            'excluded' => ['Internal escalation note for leadership only.', 'resolution_notes', 'description_summary'],
        ],
    ];

    #[Test]
    public function super_admin_and_support_can_export_safe_internal_report_csvs(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            foreach ($this->exports as $export) {
                $response = $this->actingAs($user, 'web')
                    ->get(route($export['route']))
                    ->assertOk()
                    ->assertHeader('content-type', 'text/csv; charset=UTF-8');

                $body = (string) $response->getContent();
                $rows = $this->parseCsv($body);

                $this->assertNotEmpty($rows);
                $this->assertSame($export['headers'], $rows[0]);
                $this->assertGreaterThan(1, count($rows));
                $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));

                foreach ($export['excluded'] as $excludedValue) {
                    $this->assertStringNotContainsString($excludedValue, $body);
                }
            }
        }
    }

    #[Test]
    public function export_controls_only_render_for_super_admin_and_support(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test' => [
                'should_see_export' => true,
                'dashboards' => array_keys($this->exports),
            ],
            'e2e.internal.support@example.test' => [
                'should_see_export' => true,
                'dashboards' => array_keys($this->exports),
            ],
            'e2e.internal.ops_readonly@example.test' => [
                'should_see_export' => false,
                'dashboards' => array_keys($this->exports),
            ],
            'e2e.internal.carrier_manager@example.test' => [
                'should_see_export' => false,
                'dashboards' => ['carriers'],
            ],
        ] as $email => $expectation) {
            $user = $this->userByEmail($email);
            $shouldSeeExport = (bool) $expectation['should_see_export'];

            foreach ($expectation['dashboards'] as $domain) {
                $export = $this->exports[$domain];
                $response = $this->actingAs($user, 'web')
                    ->get(route($export['dashboard']))
                    ->assertOk();

                if ($shouldSeeExport) {
                    $response->assertSee('data-testid="internal-report-dashboard-export-link"', false)
                        ->assertSee('href="'.route($export['route']).'"', false);
                } else {
                    $response->assertDontSee('data-testid="internal-report-dashboard-export-link"', false)
                        ->assertDontSee('href="'.route($export['route']).'"', false);
                }
            }
        }
    }

    #[Test]
    public function ops_readonly_carrier_manager_and_external_users_are_forbidden_from_internal_report_exports(): void
    {
        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            foreach ($this->exports as $export) {
                $this->assertForbiddenInternalSurface(
                    $this->actingAs($user, 'web')->get(route($export['route']))
                );
            }
        }
    }

    #[Test]
    public function exports_respect_active_dashboard_filters_for_supported_domains(): void
    {
        $support = $this->userByEmail('e2e.internal.support@example.test');

        $carrierResponse = $this->actingAs($support, 'web')
            ->get(route('internal.reports.carriers.export', ['health' => 'degraded']))
            ->assertOk();

        $carrierBody = (string) $carrierResponse->getContent();
        $carrierRows = $this->parseCsv($carrierBody);

        $this->assertNotEmpty($carrierRows);
        $this->assertSame($this->exports['carriers']['headers'], $carrierRows[0]);
        $this->assertStringContainsString('FedEx', $carrierBody);
        $this->assertStringNotContainsString('DHL Express', $carrierBody);

        $ticketResponse = $this->actingAs($support, 'web')
            ->get(route('internal.reports.tickets.export', ['shipment_scope' => 'linked']))
            ->assertOk();

        $ticketBody = (string) $ticketResponse->getContent();
        $ticketRows = $this->parseCsv($ticketBody);

        $this->assertNotEmpty($ticketRows);
        $this->assertSame($this->exports['tickets']['headers'], $ticketRows[0]);
        $linkedShipmentColumn = array_search('linked_shipment_reference', $ticketRows[0], true);

        $this->assertNotFalse($linkedShipmentColumn);

        foreach (array_slice($ticketRows, 1) as $row) {
            $this->assertNotSame('N/A', $row[$linkedShipmentColumn]);
            $this->assertNotSame('', trim($row[$linkedShipmentColumn]));
        }
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseCsv(string $csv): array
    {
        return collect(preg_split("/\r\n|\n|\r/", trim($csv)) ?: [])
            ->filter(static fn (string $line): bool => $line !== '')
            ->map(static fn (string $line): array => str_getcsv($line))
            ->values()
            ->all();
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
