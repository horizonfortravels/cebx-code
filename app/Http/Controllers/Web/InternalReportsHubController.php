<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InternalReportDashboardService;
use App\Services\InternalReportsHubService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InternalReportsHubController extends Controller
{
    public function __construct(
        private readonly InternalReportsHubService $reportsHubService,
        private readonly InternalReportDashboardService $reportDashboardService,
        private readonly InternalControlPlane $controlPlane,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $domainOptions = collect($this->reportsHubService->domainOptions())
            ->filter(fn (string $label, string $domain): bool => $this->canViewCard($user, $domain))
            ->all();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'domain' => $this->normalizedFilter((string) $request->query('domain', ''), array_keys($domainOptions)),
        ];

        $cards = $this->reportsHubService->cards($user)
            ->map(fn (array $card): array => array_merge($card, [
                'can_open' => $this->canOpenLinkedCenter($user, (string) ($card['key'] ?? '')),
                'can_open_dashboard' => $this->canOpenDashboard($user, (string) ($card['key'] ?? '')),
            ]))
            ->filter(fn (array $card): bool => $this->canViewCard($user, (string) ($card['key'] ?? '')))
            ->filter(fn (array $card): bool => $this->matchesFilters($card, $filters))
            ->values();

        return view('pages.admin.reports-index', [
            'cards' => $cards,
            'filters' => $filters,
            'domainOptions' => $domainOptions,
        ]);
    }

    public function shipments(Request $request): View
    {
        return $this->renderDashboard($request, 'shipments');
    }

    public function kyc(Request $request): View
    {
        return $this->renderDashboard($request, 'kyc');
    }

    public function billing(Request $request): View
    {
        return $this->renderDashboard($request, 'billing');
    }

    public function compliance(Request $request): View
    {
        return $this->renderDashboard($request, 'compliance');
    }

    public function tickets(Request $request): View
    {
        return $this->renderDashboard($request, 'tickets');
    }

    public function executive(Request $request): View
    {
        return $this->renderDashboard($request, 'executive');
    }

    /**
     * @param array{q: string, domain: string} $filters
     * @param array<string, mixed> $card
     */
    private function matchesFilters(array $card, array $filters): bool
    {
        if ($filters['domain'] !== '' && (string) ($card['key'] ?? '') !== $filters['domain']) {
            return false;
        }

        if ($filters['q'] === '') {
            return true;
        }

        $haystack = Str::lower(implode(' ', array_filter([
            (string) ($card['title'] ?? ''),
            (string) ($card['eyebrow'] ?? ''),
            (string) ($card['description'] ?? ''),
            (string) ($card['summary'] ?? ''),
            implode(' ', collect($card['metrics'] ?? [])->pluck('label')->all()),
        ])));

        return str_contains($haystack, Str::lower($filters['q']));
    }

    private function renderDashboard(Request $request, string $domain): View
    {
        $user = $request->user();
        $dashboard = $this->reportDashboardService->dashboard($domain, $user);

        abort_if(! is_array($dashboard), 404);

        return view('pages.admin.reports-dashboard', [
            'dashboard' => $dashboard,
            'drilldowns' => $this->drilldownsFor($user, $domain),
            'canExport' => $this->canExportDashboard($user, $domain),
        ]);
    }

    private function canOpenLinkedCenter(?User $user, string $domain): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return match ($domain) {
            'shipments' => $user->hasPermission('shipments.read')
                && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_INDEX),
            'kyc' => $user->hasPermission('kyc.read')
                && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_KYC_INDEX),
            'billing' => $user->hasPermission('wallet.balance')
                && $user->hasPermission('wallet.ledger')
                && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_BILLING_INDEX),
            'compliance' => $user->hasPermission('compliance.read')
                && $user->hasPermission('dg.read')
                && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_COMPLIANCE_INDEX),
            'tickets' => $user->hasPermission('tickets.read')
                && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_TICKETS_INDEX),
            'executive' => false,
            default => false,
        };
    }

    private function canOpenDashboard(?User $user, string $domain): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return match ($domain) {
            'shipments' => $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_REPORTS_SHIPMENTS_DASHBOARD),
            'kyc' => $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_REPORTS_KYC_DASHBOARD),
            'billing' => $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_REPORTS_BILLING_DASHBOARD),
            'compliance' => $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_REPORTS_COMPLIANCE_DASHBOARD),
            'tickets' => $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_REPORTS_TICKETS_DASHBOARD),
            'executive' => $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_REPORTS_EXECUTIVE_DASHBOARD),
            default => false,
        };
    }

    private function canExportDashboard(?User $user, string $domain): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! in_array($domain, ['shipments', 'kyc', 'billing', 'compliance', 'tickets'], true)) {
            return false;
        }

        return $user->hasPermission('reports.export')
            && $user->hasPermission('analytics.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_REPORTS_EXPORTS);
    }

    /**
     * @return Collection<int, array{label: string, route_name: string}>
     */
    private function drilldownsFor(?User $user, string $domain): Collection
    {
        if (! $user instanceof User) {
            return collect();
        }

        $links = collect(match ($domain) {
            'shipments' => [
                $this->linkIfAllowed($user, 'مركز الشحنات', 'internal.shipments.index', 'shipments.read', InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_INDEX),
                $this->linkIfAllowed($user, 'مركز KYC', 'internal.kyc.index', 'kyc.read', InternalControlPlane::SURFACE_INTERNAL_KYC_INDEX),
                $this->linkIfAllowed($user, 'مركز التذاكر', 'internal.tickets.index', 'tickets.read', InternalControlPlane::SURFACE_INTERNAL_TICKETS_INDEX),
            ],
            'kyc' => [
                $this->linkIfAllowed($user, 'مركز KYC', 'internal.kyc.index', 'kyc.read', InternalControlPlane::SURFACE_INTERNAL_KYC_INDEX),
                $this->linkIfAllowed($user, 'مركز الامتثال', 'internal.compliance.index', 'compliance.read', InternalControlPlane::SURFACE_INTERNAL_COMPLIANCE_INDEX, 'dg.read'),
                $this->linkIfAllowed($user, 'مركز الفوترة', 'internal.billing.index', 'wallet.balance', InternalControlPlane::SURFACE_INTERNAL_BILLING_INDEX, 'wallet.ledger'),
            ],
            'billing' => [
                $this->linkIfAllowed($user, 'مركز الفوترة', 'internal.billing.index', 'wallet.balance', InternalControlPlane::SURFACE_INTERNAL_BILLING_INDEX, 'wallet.ledger'),
                $this->linkIfAllowed($user, 'مركز الشحنات', 'internal.shipments.index', 'shipments.read', InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_INDEX),
                $this->linkIfAllowed($user, 'مركز KYC', 'internal.kyc.index', 'kyc.read', InternalControlPlane::SURFACE_INTERNAL_KYC_INDEX),
            ],
            'compliance' => [
                $this->linkIfAllowed($user, 'مركز الامتثال', 'internal.compliance.index', 'compliance.read', InternalControlPlane::SURFACE_INTERNAL_COMPLIANCE_INDEX, 'dg.read'),
                $this->linkIfAllowed($user, 'مركز KYC', 'internal.kyc.index', 'kyc.read', InternalControlPlane::SURFACE_INTERNAL_KYC_INDEX),
                $this->linkIfAllowed($user, 'مركز الفوترة', 'internal.billing.index', 'wallet.balance', InternalControlPlane::SURFACE_INTERNAL_BILLING_INDEX, 'wallet.ledger'),
            ],
            'tickets' => [
                $this->linkIfAllowed($user, 'مركز التذاكر', 'internal.tickets.index', 'tickets.read', InternalControlPlane::SURFACE_INTERNAL_TICKETS_INDEX),
                $this->linkIfAllowed($user, 'مركز الشحنات', 'internal.shipments.index', 'shipments.read', InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_INDEX),
            ],
            'executive' => [
                $this->linkIfAllowed($user, 'مركز الشحنات', 'internal.shipments.index', 'shipments.read', InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_INDEX),
                $this->linkIfAllowed($user, 'مركز الفوترة', 'internal.billing.index', 'wallet.balance', InternalControlPlane::SURFACE_INTERNAL_BILLING_INDEX, 'wallet.ledger'),
                $this->linkIfAllowed($user, 'مركز الامتثال', 'internal.compliance.index', 'compliance.read', InternalControlPlane::SURFACE_INTERNAL_COMPLIANCE_INDEX, 'dg.read'),
                $this->linkIfAllowed($user, 'مركز التذاكر', 'internal.tickets.index', 'tickets.read', InternalControlPlane::SURFACE_INTERNAL_TICKETS_INDEX),
            ],
            default => [],
        });

        return $links->filter()->values();
    }

    private function linkIfAllowed(
        User $user,
        string $label,
        string $routeName,
        string $permission,
        string $surface,
        ?string $secondaryPermission = null,
    ): ?array {
        if (! $user->hasPermission($permission)) {
            return null;
        }

        if ($secondaryPermission !== null && ! $user->hasPermission($secondaryPermission)) {
            return null;
        }

        if (! $this->controlPlane->canSeeSurface($user, $surface)) {
            return null;
        }

        return [
            'label' => $label,
            'route_name' => $routeName,
        ];
    }

    private function canViewCard(?User $user, string $domain): bool
    {
        return match ($domain) {
            'executive' => $this->canOpenDashboard($user, 'executive'),
            default => true,
        };
    }

    /**
     * @param array<int, string> $allowed
     */
    private function normalizedFilter(string $value, array $allowed): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : '';
    }
}
