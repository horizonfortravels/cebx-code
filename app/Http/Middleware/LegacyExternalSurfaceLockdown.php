<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyExternalSurfaceLockdown
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) optional($request->route())->getName();

        if ($this->isAllowedNotificationRoute($routeName)) {
            return $next($request);
        }

        $redirect = $this->redirectFor($request, $routeName);
        if ($redirect !== null) {
            return $redirect;
        }

        abort(404);
    }

    private function isAllowedNotificationRoute(string $routeName): bool
    {
        return in_array($routeName, [
            'notifications.index',
            'notifications.read',
            'notifications.readAll',
        ], true);
    }

    private function redirectFor(Request $request, string $routeName): ?RedirectResponse
    {
        return match ($routeName) {
            'dashboard' => $this->redirectToPortalRoute($request, 'b2c.dashboard', 'b2b.dashboard'),
            'shipments.index' => $this->redirectToPortalRoute($request, 'b2c.shipments.index', 'b2b.shipments.index'),
            'shipments.export' => $this->redirectToPortalRoute($request, 'b2c.shipments.export', 'b2b.shipments.export'),
            'shipments.show' => $this->redirectToPortalRoute(
                $request,
                'b2c.shipments.show',
                'b2b.shipments.show',
                ['id' => $this->routeParameter($request, 'shipment')]
            ),
            'wallet.index' => $this->redirectToPortalRoute($request, 'b2c.wallet.index', 'b2b.wallet.index'),
            'addresses.index' => $this->redirectToPortalRoute($request, 'b2c.addresses.index', 'b2b.addresses.index'),
            'settings.index' => $this->redirectToPortalRoute($request, 'b2c.settings.index', 'b2b.settings.index'),
            'support.index' => $this->redirectToPortalRoute($request, 'b2c.support.index', null),
            'tracking.index' => $this->redirectToPortalRoute($request, 'b2c.tracking.index', null),
            'orders.index' => $this->redirectToOrganizationRoute($request, 'b2b.orders.index'),
            'stores.index' => $this->redirectToOrganizationRoute($request, 'b2b.stores.index'),
            'users.index' => $this->redirectToOrganizationRoute($request, 'b2b.users.index'),
            'roles.index' => $this->redirectToOrganizationRoute($request, 'b2b.roles.index'),
            'invitations.index' => $this->redirectToOrganizationRoute($request, 'b2b.invitations.index'),
            'reports.index' => $this->redirectToOrganizationRoute($request, 'b2b.reports.index'),
            default => null,
        };
    }

    private function redirectToPortalRoute(
        Request $request,
        ?string $individualRoute,
        ?string $organizationRoute,
        array $parameters = []
    ): ?RedirectResponse {
        $route = match ($this->accountType($request)) {
            'individual' => $individualRoute,
            'organization' => $organizationRoute,
            default => null,
        };

        if ($route === null) {
            return null;
        }

        return redirect()->route($route, $this->routeParameters($request, $parameters));
    }

    private function redirectToOrganizationRoute(
        Request $request,
        string $route,
        array $parameters = []
    ): ?RedirectResponse {
        if ($this->accountType($request) !== 'organization') {
            return null;
        }

        return redirect()->route($route, $this->routeParameters($request, $parameters));
    }

    private function routeParameters(Request $request, array $parameters = []): array
    {
        $cleanParameters = array_filter(
            $parameters,
            static fn ($value): bool => $value !== null && $value !== ''
        );

        return array_merge($cleanParameters, $request->query());
    }

    private function routeParameter(Request $request, string $key): mixed
    {
        return $request->route($key);
    }

    private function accountType(Request $request): string
    {
        return strtolower((string) optional($request->user()?->account)->type);
    }
}
