<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Str;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class WebhookIsolationTest extends TestCase
{
    #[Test]
    public function test_public_webhook_routes_do_not_require_auth_or_permission_middleware(): void
    {
        $routes = app('router')->getRoutes();

        $targets = [
            'api.v1.webhooks.handle',
            'api.v1.webhooks.dhl-tracking',
            'api.v1.tracking.public-track',
        ];

        foreach ($targets as $name) {
            $route = $routes->getByName($name);

            $this->assertNotNull($route, "Route {$name} must exist.");

            $middlewares = $route->gatherMiddleware();

            $this->assertFalse(
                in_array('auth:sanctum', $middlewares, true),
                "Route {$name} must remain public (no sanctum auth middleware)."
            );

            $hasPermissionMiddleware = false;
            foreach ($middlewares as $middleware) {
                if (str_starts_with($middleware, 'permission:')) {
                    $hasPermissionMiddleware = true;
                    break;
                }
            }

            $this->assertFalse(
                $hasPermissionMiddleware,
                "Route {$name} must not use permission middleware."
            );
        }
    }

    #[Test]
    public function test_public_webhook_endpoints_are_accessible_without_sanctum_auth(): void
    {
        $storeResponse = $this->postJson('/api/v1/webhooks/shopify/' . (string) Str::uuid(), [
            'id' => 'evt-' . Str::random(8),
            'topic' => 'orders/create',
        ]);
        $storeResponse->assertStatus(404);

        $dhlResponse = $this->postJson('/api/v1/webhooks/dhl/tracking', [
            'events' => [],
        ]);
        $this->assertNotSame(401, $dhlResponse->status(), 'DHL webhook endpoint must not require sanctum auth.');

        $trackingResponse = $this->getJson('/api/v1/webhooks/track/NO-SUCH-TRACKING-NUMBER');
        $this->assertNotContains($trackingResponse->status(), [401, 403], 'Public tracking webhook endpoint must not require sanctum/permission auth.');
    }
}
