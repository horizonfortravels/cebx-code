<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\InternalReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalReportExportController extends Controller
{
    public function __construct(
        private readonly InternalReportExportService $reportExportService,
    ) {}

    public function shipments(Request $request): Response
    {
        return $this->download(self::DOMAIN_SHIPMENTS, $request);
    }

    public function kyc(Request $request): Response
    {
        return $this->download(self::DOMAIN_KYC, $request);
    }

    public function billing(Request $request): Response
    {
        return $this->download(self::DOMAIN_BILLING, $request);
    }

    public function compliance(Request $request): Response
    {
        return $this->download(self::DOMAIN_COMPLIANCE, $request);
    }

    public function carriers(Request $request): Response
    {
        return $this->download(self::DOMAIN_CARRIERS, $request);
    }

    public function tickets(Request $request): Response
    {
        return $this->download(self::DOMAIN_TICKETS, $request);
    }

    private function download(string $domain, Request $request): Response
    {
        $export = $this->reportExportService->export($domain, $request->user(), $request->query());

        return response($export['csv'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private const DOMAIN_SHIPMENTS = InternalReportExportService::DOMAIN_SHIPMENTS;

    private const DOMAIN_KYC = InternalReportExportService::DOMAIN_KYC;

    private const DOMAIN_BILLING = InternalReportExportService::DOMAIN_BILLING;

    private const DOMAIN_COMPLIANCE = InternalReportExportService::DOMAIN_COMPLIANCE;

    private const DOMAIN_CARRIERS = InternalReportExportService::DOMAIN_CARRIERS;

    private const DOMAIN_TICKETS = InternalReportExportService::DOMAIN_TICKETS;
}
