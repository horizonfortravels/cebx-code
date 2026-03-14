<?php

namespace App\Services\Carriers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DhlApiService — DHL Express MyDHL API Integration
 *
 * Handles all HTTP communication with DHL Express API.
 * Used by CarrierService and CarrierRateAdapter.
 *
 * In production, this calls the real DHL API.
 * In testing, this is mocked/stubbed.
 */
class DhlApiService
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;
    private string $accountNumber;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl       = (string) (config('services.dhl.base_url') ?? 'https://express.api.dhl.com/mydhlapi');
        $this->apiKey        = (string) (config('services.dhl.api_key') ?? '');
        $this->apiSecret     = (string) (config('services.dhl.api_secret') ?? '');
        $this->accountNumber = (string) (config('services.dhl.account_number') ?? '');
        $this->timeout       = (int) (config('services.dhl.timeout') ?? 30);
    }

    /**
     * Create a shipment at DHL.
     *
     * @param array $payload  The shipment data
     * @param string $idempotencyKey  Idempotency key (FR-CR-003)
     * @return array  Response with shipmentId, trackingNumber, documents, etc.
     * @throws \Exception
     */
    public function createShipment(array $payload, string $idempotencyKey): array
    {
        $response = $this->request('POST', '/shipments', $payload, [
            'Message-Reference' => $idempotencyKey,
        ]);

        // Normalize DHL response to internal format
        $shipmentData = $response['shipmentTrackingNumber']
            ? $response
            : ($response['shipments'][0] ?? $response);

        return [
            'shipmentId'                  => $shipmentData['shipmentTrackingNumber'] ?? null,
            'trackingNumber'              => $shipmentData['shipmentTrackingNumber'] ?? null,
            'dispatchConfirmationNumber'  => $shipmentData['dispatchConfirmationNumber'] ?? null,
            'serviceCode'                 => $shipmentData['productCode'] ?? null,
            'serviceName'                 => $shipmentData['productName'] ?? null,
            'productCode'                 => $shipmentData['productCode'] ?? null,
            'cancellable'                 => true,
            'cancellationDeadline'        => null,
            'documents'                   => $this->extractDocuments($shipmentData),
        ];
    }

    /**
     * Fetch label for an existing shipment.
     *
     * @param string $shipmentId  DHL shipment ID
     * @param string $trackingNumber  Tracking number
     * @param string $format  Label format (pdf/zpl)
     * @return array  Response with content (base64) and/or url
     */
    public function fetchLabel(string $shipmentId, string $trackingNumber, string $format = 'pdf'): array
    {
        $response = $this->request('GET', "/shipments/{$shipmentId}/get-image", [
            'shipperAccountNumber' => $this->accountNumber,
            'typeCode'             => 'label',
            'encodingFormat'       => strtoupper($format),
        ]);

        $doc = $response['documents'][0] ?? null;

        return [
            'content' => $doc['content'] ?? null,
            'format'  => strtolower($doc['encodingFormat'] ?? $format),
            'url'     => $doc['url'] ?? null,
        ];
    }

    /**
     * Cancel a shipment at DHL.
     *
     * @param string $shipmentId  DHL shipment ID
     * @param string $trackingNumber  Tracking number
     * @return array  Response with cancellation confirmation
     */
    public function cancelShipment(string $shipmentId, string $trackingNumber): array
    {
        $response = $this->request('DELETE', "/shipments/{$shipmentId}", [
            'shipperAccountNumber' => $this->accountNumber,
        ]);

        return [
            'cancellationId' => $response['cancelledId'] ?? $shipmentId,
            'status'         => 'cancelled',
        ];
    }

    /**
     * Get rates from DHL (used by CarrierRateAdapter).
     *
     * @param array $params  Rate request parameters
     * @return array  Rate response
     */
    public function getRates(array $params): array
    {
        return $this->request('POST', '/rates', $params);
    }

    /**
     * Track a shipment at DHL.
     *
     * @param string $trackingNumber
     * @return array
     */
    public function trackShipment(string $trackingNumber): array
    {
        return $this->request('GET', "/tracking", [
            'shipmentTrackingNumber' => [$trackingNumber],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Private Helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * Make an HTTP request to DHL API.
     */
    private function request(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $defaultHeaders = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        try {
            $http = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withHeaders($allHeaders)
                ->timeout($this->timeout);

            $response = match (strtoupper($method)) {
                'GET'    => $http->get($url, $data),
                'POST'   => $http->post($url, $data),
                'PUT'    => $http->put($url, $data),
                'DELETE' => $http->delete($url, $data),
                'PATCH'  => $http->patch($url, $data),
                default  => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::error('DHL API Error', [
                'url'      => $url,
                'method'   => $method,
                'status'   => $response->status(),
                'body'     => \Illuminate\Support\Str::limit($response->body(), 500),
            ]);

            throw new \Exception(
                "DHL API error: {$response->status()} - {$response->body()}",
                $response->status()
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('DHL API Connection Error', [
                'url'     => $url,
                'method'  => $method,
                'message' => $e->getMessage(),
            ]);

            throw new \Exception('DHL API connection failed: ' . $e->getMessage(), 504);
        }
    }

    /**
     * Extract documents from DHL shipment response.
     */
    private function extractDocuments(array $shipmentData): array
    {
        $documents = [];

        // Check various DHL response formats for documents
        $docSources = [
            $shipmentData['documents'] ?? [],
            $shipmentData['labelImage'] ?? [],
            $shipmentData['packages'][0]['documents'] ?? [],
        ];

        foreach ($docSources as $source) {
            if (empty($source)) continue;

            // Normalize to array of documents
            $docs = isset($source[0]) ? $source : [$source];

            foreach ($docs as $doc) {
                $documents[] = [
                    'type'    => $doc['typeCode'] ?? $doc['type'] ?? 'label',
                    'format'  => strtolower($doc['encodingFormat'] ?? $doc['imageFormat'] ?? 'pdf'),
                    'content' => $doc['content'] ?? $doc['imageContent'] ?? null,
                    'url'     => $doc['url'] ?? null,
                ];
            }
        }

        return $documents;
    }
}
