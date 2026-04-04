<?php

namespace Tests\Feature\Web;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\WebhookEvent;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalWebhookOperationsWebTest extends TestCase
{
    use RefreshDatabase;

    private Store $shopifyStore;
    private WebhookEvent $failedStoreEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->shopifyStore = Store::query()
            ->withoutGlobalScopes()
            ->where('name', 'I8A Shopify Store')
            ->firstOrFail();

        $this->failedStoreEvent = WebhookEvent::query()
            ->withoutGlobalScopes()
            ->where('store_id', (string) $this->shopifyStore->id)
            ->where('external_event_id', 'i8b-shopify-webhook-failed-001')
            ->firstOrFail();
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_webhook_index_and_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.webhooks.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-webhooks-table"', false)
                ->assertSeeText('I8A Shopify Store')
                ->assertSeeText('DHL Express inbound webhooks')
                ->assertSeeText('Retryable failures')
                ->assertDontSeeText('i8a-shopify-webhook-secret-001')
                ->assertDontSeeText('masked-signature')
                ->assertDontSeeText('masked-replay-token');

            $this->assertHasNavigationLink($index, 'internal.webhooks.index');

            $storeDetail = $this->actingAs($user, 'web')
                ->get(route('internal.webhooks.show', 'store~' . (string) $this->shopifyStore->id))
                ->assertOk()
                ->assertSee('data-testid="internal-webhook-summary-card"', false)
                ->assertSee('data-testid="internal-webhook-attempts-card"', false)
                ->assertSee('data-testid="internal-webhook-failures-card"', false)
                ->assertSeeText('Store webhook endpoint')
                ->assertSeeText('A prior processing attempt timed out for the stored delivery.')
                ->assertSeeText('External resource reference recorded')
                ->assertDontSeeText('1002-ORD')
                ->assertDontSeeText('i8a-shopify-webhook-secret-001')
                ->assertDontSeeText('payload')
                ->assertDontSeeText('connection_config');

            $trackingDetail = $this->actingAs($user, 'web')
                ->get(route('internal.webhooks.show', 'tracking~dhl'))
                ->assertOk()
                ->assertSee('data-testid="internal-webhook-summary-card"', false)
                ->assertSee('data-testid="internal-webhook-attempts-card"', false)
                ->assertSeeText('Tracking webhook endpoint')
                ->assertSeeText('Signature validation failed for the stored delivery.')
                ->assertSeeText('Webhook reference recorded')
                ->assertDontSeeText('i8b-dhl-webhook-failed-001')
                ->assertDontSeeText('masked-signature-failed')
                ->assertDontSeeText('masked-replay-token-failed')
                ->assertDontSeeText('headers')
                ->assertDontSeeText('user_agent');

            if ($email === 'e2e.internal.super_admin@example.test') {
                $storeDetail->assertSee('data-testid="internal-webhook-retry-form"', false);
            } else {
                $storeDetail->assertDontSee('data-testid="internal-webhook-retry-form"', false);
            }

            $trackingDetail->assertDontSee('data-testid="internal-webhook-retry-form"', false);
        }
    }

    #[Test]
    public function webhooks_index_supports_search_and_basic_filters(): void
    {
        $user = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($user, 'web')
            ->get(route('internal.webhooks.index', ['q' => 'shopify']))
            ->assertOk()
            ->assertSeeText('I8A Shopify Store')
            ->assertDontSeeText('DHL Express inbound webhooks');

        $this->actingAs($user, 'web')
            ->get(route('internal.webhooks.index', ['type' => 'tracking']))
            ->assertOk()
            ->assertSeeText('DHL Express inbound webhooks')
            ->assertDontSeeText('I8A Shopify Store');

        $this->actingAs($user, 'web')
            ->get(route('internal.webhooks.index', ['state' => 'retryable']))
            ->assertOk()
            ->assertSeeText('I8A Shopify Store')
            ->assertDontSeeText('DHL Express inbound webhooks');
    }

    #[Test]
    public function super_admin_can_retry_a_failed_store_webhook_and_audit_is_recorded(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $reason = 'Replayed after confirming the stored Shopify payload is still safe to import.';

        $this->actingAs($actor, 'web')
            ->post(route('internal.webhooks.events.retry', [
                'endpoint' => 'store~' . (string) $this->shopifyStore->id,
                'event' => $this->failedStoreEvent,
            ]), [
                'reason' => $reason,
            ])
            ->assertRedirect(route('internal.webhooks.show', 'store~' . (string) $this->shopifyStore->id))
            ->assertSessionHas('success');

        $this->failedStoreEvent->refresh();

        $this->assertSame(WebhookEvent::STATUS_PROCESSED, (string) $this->failedStoreEvent->status);
        $this->assertNotNull($this->failedStoreEvent->processed_at);

        $importedOrder = Order::query()
            ->withoutGlobalScopes()
            ->where('store_id', (string) $this->shopifyStore->id)
            ->where('external_order_id', '1002')
            ->first();

        $this->assertNotNull($importedOrder);

        $auditEntry = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $this->shopifyStore->account_id)
            ->where('user_id', (string) $actor->id)
            ->where('action', 'webhook.event_retried')
            ->where('entity_id', (string) $this->failedStoreEvent->id)
            ->latest()
            ->first();

        $this->assertNotNull($auditEntry);
        $this->assertSame($reason, (string) data_get($auditEntry?->metadata, 'reason'));
        $this->assertSame(true, (bool) data_get($auditEntry?->metadata, 'used_stored_payload'));
        $this->assertSame(false, array_key_exists('external_event_id', (array) ($auditEntry?->metadata ?? [])));
        $this->assertSame(false, array_key_exists('external_resource_id', (array) ($auditEntry?->metadata ?? [])));
        $this->assertSame(false, array_key_exists('error_message', (array) ($auditEntry?->old_values ?? [])));
        $this->assertSame(false, array_key_exists('error_message', (array) ($auditEntry?->new_values ?? [])));
        $this->assertSame(true, (bool) data_get($auditEntry?->metadata, 'had_external_event_reference'));
        $this->assertSame(true, (bool) data_get($auditEntry?->metadata, 'had_external_resource_reference'));
    }

    #[Test]
    public function support_and_ops_readonly_cannot_retry_webhook_events_and_carrier_manager_is_denied(): void
    {
        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user, 'web')
                ->get(route('internal.webhooks.show', 'store~' . (string) $this->shopifyStore->id))
                ->assertOk()
                ->assertDontSee('data-testid="internal-webhook-retry-form"', false);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.webhooks.events.retry', [
                    'endpoint' => 'store~' . (string) $this->shopifyStore->id,
                    'event' => $this->failedStoreEvent,
                ]), [
                    'reason' => 'Not allowed',
                ])
            );
        }

        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.webhooks.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.webhooks.show', 'tracking~dhl'))
        );
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_webhook_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.webhooks.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.webhooks.show', 'tracking~dhl'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->post(route('internal.webhooks.events.retry', [
                'endpoint' => 'store~' . (string) $this->shopifyStore->id,
                'event' => $this->failedStoreEvent,
            ]), [
                'reason' => 'External attempt',
            ])
        );
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function assertHasNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertSee('href="' . route($routeName) . '"', false);
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
