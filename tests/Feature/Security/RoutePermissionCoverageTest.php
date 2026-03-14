<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class RoutePermissionCoverageTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_URIS = [
        'api/v1/internal/ping',
        'api/v1/internal/tenant-context/ping',
    ];

    #[Test]
    public function test_all_authenticated_external_api_routes_have_permission_middleware(): void
    {
        $routes = $this->loadRoutes();
        $violations = [];

        foreach ($routes as $route) {
            $uri = (string) ($route['uri'] ?? '');

            if (!str_starts_with($uri, 'api/v1')) {
                continue;
            }

            if (in_array($uri, self::ALLOWED_URIS, true) || str_starts_with($uri, 'api/v1/webhooks/')) {
                continue;
            }

            $middleware = array_map('strval', $route['middleware'] ?? []);

            if (!$this->hasExternalAuthStack($middleware)) {
                continue;
            }

            $hasPermissionMiddleware = collect($middleware)->contains(
                static fn (string $item): bool => str_starts_with($item, 'permission:')
                    || str_contains($item, 'CheckPermission:')
            );

            if ($hasPermissionMiddleware) {
                continue;
            }

            $methods = implode('|', array_values(array_diff(
                array_map('strval', explode('|', (string) ($route['method'] ?? ''))),
                ['HEAD']
            )));

            $violations[] = sprintf(
                '%s %s %s',
                $methods,
                $uri,
                (string) ($route['action'] ?? 'unknown')
            );
        }

        $this->assertSame(
            [],
            $violations,
            "Authenticated external API routes missing permission middleware:\n" . implode("\n", $violations)
        );
    }

    /**
     * @param array<int, string> $middleware
     */
    private function hasExternalAuthStack(array $middleware): bool
    {
        $hasAuth = in_array('auth:sanctum', $middleware, true)
            || in_array('Illuminate\Auth\Middleware\Authenticate:sanctum', $middleware, true);

        $hasExternalUserType = in_array('userType:external', $middleware, true)
            || in_array('App\Http\Middleware\EnsureUserTypeMiddleware:external', $middleware, true);

        return $hasAuth && $hasExternalUserType;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRoutes(): array
    {
        $process = new Process(
            [PHP_BINARY, 'artisan', 'route:list', '--path=api/v1', '--json'],
            dirname(__DIR__, 3)
        );

        $process->mustRun();

        /** @var array<int, array<string, mixed>> $routes */
        $routes = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        return $routes;
    }
}
