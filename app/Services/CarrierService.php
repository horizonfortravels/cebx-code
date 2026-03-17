<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\CarrierDocument;
use App\Models\CarrierError;
use App\Models\CarrierShipment;
use App\Models\ContentDeclaration;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use App\Models\WalletHold;
use App\Services\Carriers\Contracts\CarrierShipmentProvider;
use App\Services\Carriers\DhlApiService;
use App\Services\Carriers\FedexShipmentProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarrierService
{
    public function __construct(
        private DhlApiService $dhlApi,
        private FedexShipmentProvider $fedexShipmentProvider,
        private AuditService $audit,
        private BillingWalletService $billing,
        private ShipmentTimelineService $shipmentTimeline,
    ) {}

    public function createAtCarrier(
        Shipment $shipment,
        User $user,
        ?string $labelFormat = null,
        ?string $labelSize = null,
        ?string $idempotencyKey = null,
        ?string $correlationId = null
    ): CarrierShipment {
        $account = $shipment->account;
        $shipment->loadMissing([
            'selectedRateOption',
            'rateQuote',
            'balanceReservation',
            'contentDeclaration',
            'parcels',
        ]);

        $idempotencyKey = $idempotencyKey ?: CarrierShipment::generateIdempotencyKey((string) $shipment->id);
        $existing = CarrierShipment::where('idempotency_key', $idempotencyKey)
            ->where('shipment_id', (string) $shipment->id)
            ->where('account_id', (string) $account->id)
            ->whereIn('status', [
                CarrierShipment::STATUS_CREATING,
                CarrierShipment::STATUS_CREATED,
                CarrierShipment::STATUS_LABEL_PENDING,
                CarrierShipment::STATUS_LABEL_READY,
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        $this->validateForCarrierCreation($shipment);
        $provider = $this->resolveShipmentProvider($shipment);

        $format = $labelFormat ?? ($account->settings['label_format'] ?? 'pdf');
        $size = $labelSize ?? ($account->settings['label_size'] ?? '4x6');
        $correlationId = $correlationId ?: Str::uuid()->toString();

        $carrierShipment = CarrierShipment::create([
            'shipment_id' => $shipment->id,
            'account_id' => $account->id,
            'carrier_code' => $provider->carrierCode(),
            'carrier_name' => ucfirst($provider->carrierCode()),
            'status' => CarrierShipment::STATUS_CREATING,
            'idempotency_key' => $idempotencyKey,
            'attempt_count' => 1,
            'last_attempt_at' => now(),
            'label_format' => $format,
            'label_size' => $size,
            'correlation_id' => $correlationId,
        ]);

        try {
            $response = $provider->createShipment($this->buildCarrierCreationContext(
                $shipment,
                $format,
                $size,
                $correlationId,
                $idempotencyKey
            ));

            DB::transaction(function () use ($shipment, $user, $provider, $carrierShipment, $response, $correlationId, $idempotencyKey) {
                $capturedReservation = $this->captureActiveReservationForIssuedShipment($shipment);

                $carrierShipment->update([
                    'carrier_code' => (string) ($response['carrier_code'] ?? $provider->carrierCode()),
                    'carrier_name' => (string) ($response['carrier_name'] ?? ucfirst($provider->carrierCode())),
                    'carrier_shipment_id' => $response['carrier_shipment_id'] ?? null,
                    'tracking_number' => $response['tracking_number'] ?? null,
                    'awb_number' => $response['awb_number'] ?? null,
                    'service_code' => $response['service_code'] ?? $shipment->service_code ?? $shipment->carrier_service_code,
                    'service_name' => $response['service_name'] ?? $shipment->service_name ?? null,
                    'status' => CarrierShipment::STATUS_CREATED,
                    'request_payload' => $response['request_payload'] ?? null,
                    'response_payload' => $response['response_payload'] ?? null,
                    'carrier_metadata' => array_merge(
                        is_array($response['carrier_metadata'] ?? null) ? $response['carrier_metadata'] : [],
                        [
                            'initial_carrier_status' => $response['initial_carrier_status'] ?? 'created',
                            'correlation_id' => $correlationId,
                            'idempotency_key' => $idempotencyKey,
                            'wallet_reservation' => $this->buildReservationMetadata(
                                $capturedReservation,
                                'captured_on_success'
                            ),
                        ]
                    ),
                    'is_cancellable' => false,
                    'cancellation_deadline' => null,
                ]);

                $oldStatus = (string) $shipment->status;
                $shipment->update($this->buildShipmentCarrierCreationUpdates(
                    $shipment,
                    $carrierShipment,
                    $response,
                    $capturedReservation
                ));

                $this->shipmentTimeline->record($shipment, [
                    'event_type' => 'shipment.purchased',
                    'status' => Shipment::STATUS_PURCHASED,
                    'normalized_status' => Shipment::STATUS_PURCHASED,
                    'description' => 'تم إصدار الشحنة لدى الناقل وربط رقم التتبع الأولي.',
                    'location' => strtoupper((string) ($response['carrier_code'] ?? $provider->carrierCode())),
                    'event_at' => now(),
                    'source' => ShipmentEvent::SOURCE_SYSTEM,
                    'correlation_id' => $correlationId,
                    'idempotency_key' => $idempotencyKey . ':shipment.purchased',
                    'payload' => [
                        'carrier_shipment_id' => (string) $carrierShipment->id,
                        'carrier_code' => (string) ($response['carrier_code'] ?? $provider->carrierCode()),
                        'tracking_number' => $response['tracking_number'] ?? null,
                        'awb_number' => $response['awb_number'] ?? null,
                        'initial_carrier_status' => $response['initial_carrier_status'] ?? 'created',
                    ],
                ]);
                $this->shipmentTimeline->syncCurrentStatus($shipment, Shipment::STATUS_PURCHASED, now());

                $storedDocuments = $this->persistCarrierArtifacts(
                    $shipment,
                    $carrierShipment,
                    $response,
                    $correlationId,
                    $idempotencyKey
                );

                if ($storedDocuments->isNotEmpty()) {
                    $carrierShipment->update([
                        'status' => CarrierShipment::STATUS_LABEL_READY,
                        'carrier_metadata' => array_merge(
                            is_array($carrierShipment->carrier_metadata ?? null) ? $carrierShipment->carrier_metadata : [],
                            ['document_count' => $storedDocuments->count()]
                        ),
                    ]);
                } else {
                    $carrierShipment->update(['status' => CarrierShipment::STATUS_LABEL_PENDING]);
                }

                $this->recordStatusHistory($shipment, $oldStatus, Shipment::STATUS_PURCHASED, 'system', $user->id, 'Carrier shipment created');

                $this->audit->info(
                    (string) $shipment->account_id,
                    (string) $user->id,
                    'carrier.shipment_created',
                    AuditLog::CATEGORY_ACCOUNT,
                    'CarrierShipment',
                    (string) $carrierShipment->id,
                    null,
                    [
                        'tracking_number' => $response['tracking_number'] ?? null,
                        'carrier' => $response['carrier_code'] ?? $provider->carrierCode(),
                        'correlation_id' => $correlationId,
                        'idempotency_key' => $idempotencyKey,
                    ]
                );
            });

            return $carrierShipment->fresh();
        } catch (\Throwable $e) {
            $linkedReservation = $this->resolveLinkedReservation($shipment);

            if ($e instanceof BusinessException) {
                $context = $e->getContext();
                $carrierShipment->update([
                    'request_payload' => $context['request_payload'] ?? $carrierShipment->request_payload,
                    'response_payload' => $context['response_body'] ?? $carrierShipment->response_payload,
                    'carrier_metadata' => array_merge(
                        is_array($carrierShipment->carrier_metadata ?? null) ? $carrierShipment->carrier_metadata : [],
                        array_filter([
                            'carrier_code' => $context['carrier_code'] ?? null,
                            'carrier_error_code' => $context['carrier_error_code'] ?? null,
                            'correlation_id' => $correlationId,
                            'idempotency_key' => $idempotencyKey,
                            'wallet_reservation' => $this->buildReservationMetadata(
                                $linkedReservation,
                                'kept_active_on_failure'
                            ),
                        ], static fn ($value) => $value !== null && $value !== '')
                    ),
                ]);
            }

            $carrierError = $this->logCarrierError(
                CarrierError::OP_CREATE_SHIPMENT,
                $e,
                $correlationId,
                (string) $shipment->id,
                (string) $carrierShipment->id
            );

            DB::transaction(function () use ($carrierShipment, $shipment, $carrierError) {
                $carrierShipment->update(['status' => CarrierShipment::STATUS_FAILED]);

                $oldStatus = (string) $shipment->status;
                $shipment->update(['status' => Shipment::STATUS_FAILED]);
                $this->recordStatusHistory($shipment, $oldStatus, Shipment::STATUS_FAILED, 'system', null, 'Carrier creation failed: ' . $carrierError->internal_message);
            });

            throw BusinessException::make(
                'ERR_CARRIER_CREATE_FAILED',
                'Carrier shipment creation failed: ' . $carrierError->internal_message,
                [
                    'carrier_error_id' => $carrierError->id,
                    'is_retriable' => $carrierError->is_retriable,
                ],
                502
            );
        }
    }

    public function refetchLabel(Shipment $shipment, User $user, ?string $format = null): CarrierDocument
    {
        $carrierShipment = $shipment->carrierShipment;

        if (! $carrierShipment || ! $carrierShipment->isCreated()) {
            throw BusinessException::carrierNotCreated();
        }

        $this->assertLegacyDhlCarrierShipment($carrierShipment, 'label re-fetch');

        $correlationId = Str::uuid()->toString();
        $format = $format ?? $carrierShipment->label_format;

        try {
            $response = $this->dhlApi->fetchLabel(
                $carrierShipment->carrier_shipment_id,
                $carrierShipment->tracking_number,
                $format
            );

            $document = CarrierDocument::create([
                'carrier_shipment_id' => $carrierShipment->id,
                'shipment_id' => $shipment->id,
                'type' => CarrierDocument::TYPE_LABEL,
                'format' => $format,
                'mime_type' => CarrierDocument::getMimeType($format),
                'original_filename' => $this->generateDocFilename($shipment, 'label', $format),
                'content_base64' => $response['content'] ?? null,
                'file_size' => isset($response['content']) ? strlen(base64_decode((string) $response['content'])) : null,
                'checksum' => isset($response['content']) ? hash('sha256', base64_decode((string) $response['content'])) : null,
                'download_url' => $response['url'] ?? null,
                'is_available' => true,
            ]);

            $carrierShipment->update(['status' => CarrierShipment::STATUS_LABEL_READY]);
            $this->updateShipmentLabelFromDocuments($shipment, collect([$document]));

            $this->audit->info(
                (string) $shipment->account_id,
                (string) $user->id,
                'carrier.label_refetched',
                AuditLog::CATEGORY_ACCOUNT,
                'CarrierDocument',
                (string) $document->id,
                null,
                ['format' => $format, 'correlation_id' => $correlationId]
            );

            return $document;
        } catch (\Throwable $e) {
            $this->logCarrierError(
                CarrierError::OP_RE_FETCH_LABEL,
                $e,
                $correlationId,
                (string) $shipment->id,
                (string) $carrierShipment->id
            );

            throw BusinessException::labelRefetchFailed();
        }
    }

    public function cancelAtCarrier(Shipment $shipment, User $user): CarrierShipment
    {
        $carrierShipment = $shipment->carrierShipment;

        if (! $carrierShipment || ! $carrierShipment->canCancel()) {
            throw BusinessException::carrierNotCancellable();
        }

        $this->assertLegacyDhlCarrierShipment($carrierShipment, 'carrier cancellation');
        $correlationId = Str::uuid()->toString();

        try {
            $this->dhlApi->cancelShipment(
                $carrierShipment->carrier_shipment_id,
                $carrierShipment->tracking_number
            );

            $carrierShipment->update(['status' => CarrierShipment::STATUS_CANCELLED]);

            $oldStatus = (string) $shipment->status;
            $shipment->update(['status' => Shipment::STATUS_CANCELLED]);
            $this->recordStatusHistory($shipment, $oldStatus, Shipment::STATUS_CANCELLED, 'user', $user->id, 'Cancelled at carrier');

            $this->audit->info(
                (string) $shipment->account_id,
                (string) $user->id,
                'carrier.shipment_cancelled',
                AuditLog::CATEGORY_ACCOUNT,
                'CarrierShipment',
                (string) $carrierShipment->id,
                null,
                ['correlation_id' => $correlationId]
            );

            return $carrierShipment;
        } catch (\Throwable $e) {
            $this->logCarrierError(
                CarrierError::OP_CANCEL,
                $e,
                $correlationId,
                (string) $shipment->id,
                (string) $carrierShipment->id
            );

            throw BusinessException::carrierCancelFailed();
        }
    }

    public function retryCreation(Shipment $shipment, User $user, int $maxRetries = 3): CarrierShipment
    {
        $carrierShipment = CarrierShipment::where('shipment_id', $shipment->id)
            ->where('status', CarrierShipment::STATUS_FAILED)
            ->latest('created_at')
            ->first();

        if (! $carrierShipment) {
            throw BusinessException::make('ERR_NO_FAILED_CARRIER', 'No failed carrier shipment found to retry');
        }

        if (! $carrierShipment->canRetry($maxRetries)) {
            throw BusinessException::maxRetriesExceeded();
        }

        $carrierShipment->incrementAttempt();

        CarrierError::where('carrier_shipment_id', $carrierShipment->id)
            ->where('was_resolved', false)
            ->update(['was_resolved' => true, 'resolved_at' => now()]);

        if ($shipment->status === Shipment::STATUS_FAILED) {
            $shipment->update(['status' => Shipment::STATUS_PAYMENT_PENDING]);
        }

        return $this->createAtCarrier(
            $shipment,
            $user,
            $carrierShipment->label_format,
            $carrierShipment->label_size,
            $carrierShipment->idempotency_key,
            $carrierShipment->correlation_id
        );
    }

    public function listDocuments(Shipment $shipment): array
    {
        return CarrierDocument::where('shipment_id', $shipment->id)
            ->where('is_available', true)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CarrierDocument $doc) => [
                'id' => (string) $doc->id,
                'carrier_code' => (string) ($doc->carrier_code ?? $shipment->carrier_code ?? ''),
                'type' => (string) $doc->type,
                'document_type' => (string) $doc->type,
                'format' => (string) $doc->format,
                'file_format' => (string) $doc->format,
                'mime_type' => (string) $doc->mime_type,
                'source' => (string) ($doc->source ?? CarrierDocument::SOURCE_CARRIER),
                'retrieval_mode' => (string) ($doc->retrieval_mode ?? $this->resolveRetrievalMode($doc->getDecodedContent(), $doc->download_url)),
                'filename' => (string) ($doc->original_filename ?? $this->generateDocFilename($shipment, (string) $doc->type, (string) $doc->format)),
                'size' => $doc->file_size,
                'available' => $doc->hasContent() || $doc->hasValidUrl(),
                'carrier_document_type' => (string) data_get($doc->carrier_metadata, 'carrier_document_type', ''),
                'tracking_number' => (string) data_get($doc->carrier_metadata, 'tracking_number', ''),
                'notes' => collect((array) data_get($doc->carrier_metadata, 'alerts', []))
                    ->pluck('message')
                    ->filter()
                    ->values()
                    ->all(),
                'created_at' => optional($doc->created_at)?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDocumentForDownload(string $documentId, Shipment $shipment, User $user): array
    {
        $document = CarrierDocument::query()
            ->where('shipment_id', (string) $shipment->id)
            ->where('id', $documentId)
            ->firstOrFail();

        if (! $document->is_available || (! $document->hasContent() && ! $document->isDownloadUrlValid())) {
            throw BusinessException::documentNotAvailable();
        }

        $document->recordDownload();

        $this->audit->info(
            (string) $shipment->account_id,
            (string) $user->id,
            'carrier.document_downloaded',
            AuditLog::CATEGORY_ACCOUNT,
            'CarrierDocument',
            (string) $document->id,
            null,
            null,
            [
                'shipment_id' => (string) $shipment->id,
                'document_type' => (string) $document->type,
            ]
        );

        return [
            'id' => (string) $document->id,
            'content' => $document->getDecodedContent(),
            'format' => (string) $document->format,
            'mime_type' => (string) $document->mime_type,
            'filename' => (string) ($document->original_filename ?? $this->generateDocFilename($shipment, (string) $document->type, (string) $document->format)),
            'file_size' => $document->file_size,
            'checksum' => $document->checksum,
            'download_url' => $document->isDownloadUrlValid() ? $document->download_url : null,
            'retrieval_mode' => (string) ($document->retrieval_mode ?? $this->resolveRetrievalMode($document->getDecodedContent(), $document->download_url)),
        ];
    }

    public function getErrors(Shipment $shipment): Collection
    {
        return CarrierError::where('shipment_id', $shipment->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CarrierError $error) => [
                'id' => $error->id,
                'operation' => $error->operation,
                'internal_code' => $error->internal_code,
                'internal_message' => $error->internal_message,
                'carrier_error_code' => $error->carrier_error_code,
                'carrier_error_message' => $error->carrier_error_message,
                'http_status' => $error->http_status,
                'is_retriable' => $error->is_retriable,
                'retry_attempt' => $error->retry_attempt,
                'was_resolved' => $error->was_resolved,
                'created_at' => $error->created_at,
            ]);
    }

    private function updateShipmentLabelFromDocuments(Shipment $shipment, Collection $storedDocs): void
    {
        $labelDoc = $storedDocs->firstWhere('type', CarrierDocument::TYPE_LABEL);

        if ($labelDoc) {
            $updates = [];

            if (Schema::hasColumn('shipments', 'label_url')) {
                $updates['label_url'] = $labelDoc->download_url ?: route('api.v1.carrier.document-download', [
                    'shipmentId' => $shipment->id,
                    'documentId' => $labelDoc->id,
                ]);
            }

            if (Schema::hasColumn('shipments', 'label_format')) {
                $updates['label_format'] = $labelDoc->format;
            }

            if (Schema::hasColumn('shipments', 'label_created_at')) {
                $updates['label_created_at'] = now();
            }

            if ($updates !== []) {
                $shipment->update($updates);
            }
        }
    }

    private function storeCarrierDocuments(CarrierShipment $carrierShipment, Shipment $shipment, array $documents): Collection
    {
        $stored = collect();

        foreach ($documents as $doc) {
            $type = $this->mapDocumentType((string) ($doc['type'] ?? 'label'));
            $format = $this->normalizeDocumentFormat((string) ($doc['format'] ?? $carrierShipment->label_format));
            $content = trim((string) ($doc['content'] ?? ''));
            $downloadUrl = trim((string) ($doc['url'] ?? ''));
            $binary = $content !== '' ? base64_decode($content, true) : false;

            if ($binary === false) {
                $binary = null;
            }

            if ($binary === null && $downloadUrl === '') {
                continue;
            }

            $fileName = (string) ($doc['filename'] ?? $this->generateDocFilename($shipment, $type, $format));
            $retrievalMode = $this->resolveRetrievalMode($binary, $downloadUrl);
            $storageDisk = null;
            $storagePath = null;
            $fileSize = $binary !== null ? strlen($binary) : null;
            $checksum = $binary !== null ? hash('sha256', $binary) : null;

            if ($binary !== null) {
                [$storageDisk, $storagePath] = $this->storeCarrierDocumentBinary($shipment, $carrierShipment, $fileName, $binary);
            }

            $existing = $this->findExistingCarrierDocument(
                $carrierShipment,
                $shipment,
                $type,
                $format,
                $checksum,
                $downloadUrl,
                $fileName
            );

            if ($existing) {
                $stored->push($existing);
                continue;
            }

            $document = CarrierDocument::create([
                'carrier_shipment_id' => $carrierShipment->id,
                'shipment_id' => $shipment->id,
                'carrier_code' => (string) $carrierShipment->carrier_code,
                'type' => $type,
                'format' => $format,
                'mime_type' => CarrierDocument::getMimeType($format),
                'source' => CarrierDocument::SOURCE_CARRIER,
                'retrieval_mode' => $retrievalMode,
                'storage_path' => $storagePath,
                'storage_disk' => $storageDisk ?? config('filesystems.default', 'local'),
                'original_filename' => $fileName,
                'content_base64' => null,
                'file_size' => $fileSize,
                'checksum' => $checksum,
                'download_url' => $downloadUrl !== '' ? $downloadUrl : null,
                'download_url_expires_at' => isset($doc['urlExpiry']) ? \Carbon\Carbon::parse($doc['urlExpiry']) : null,
                'is_available' => true,
                'carrier_metadata' => $doc['metadata'] ?? null,
            ]);

            $stored->push($document);
        }

        return $stored;
    }

    private function persistCarrierArtifacts(
        Shipment $shipment,
        CarrierShipment $carrierShipment,
        array $response,
        string $correlationId,
        string $idempotencyKey
    ): Collection {
        $documents = $this->extractCarrierDocuments($response, $carrierShipment);
        $storedDocuments = $this->storeCarrierDocuments($carrierShipment, $shipment, $documents);

        if ($storedDocuments->isEmpty()) {
            return $storedDocuments;
        }

        $this->updateShipmentLabelFromDocuments($shipment, $storedDocuments);
        $this->recordDocumentAvailabilityEvent($shipment, $carrierShipment, $storedDocuments, $correlationId, $idempotencyKey);

        return $storedDocuments;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, array<string, mixed>>
     */
    private function extractCarrierDocuments(array $response, CarrierShipment $carrierShipment): array
    {
        $documents = [];

        foreach ((array) ($response['documents'] ?? []) as $document) {
            $documents[] = [
                'type' => (string) ($document['type'] ?? 'label'),
                'format' => (string) ($document['format'] ?? $carrierShipment->label_format),
                'content' => $document['content'] ?? null,
                'url' => $document['url'] ?? null,
                'urlExpiry' => $document['urlExpiry'] ?? null,
                'filename' => $document['filename'] ?? null,
                'metadata' => is_array($document['metadata'] ?? null) ? $document['metadata'] : null,
            ];
        }

        foreach ((array) data_get($response, 'carrier_metadata.shipment_documents', []) as $document) {
            $type = (string) ($document['docType'] ?? $document['contentKey'] ?? 'label');
            $documents[] = [
                'type' => $type,
                'format' => $this->normalizeDocumentFormat((string) ($document['fileType'] ?? $document['fileFormat'] ?? $carrierShipment->label_format)),
                'content' => $document['encodedLabel'] ?? null,
                'url' => $document['url'] ?? $document['documentUrl'] ?? null,
                'urlExpiry' => $document['urlExpiry'] ?? $document['expirationTime'] ?? null,
                'filename' => $document['fileName'] ?? null,
                'metadata' => [
                    'carrier_document_type' => $type,
                    'content_key' => $document['contentKey'] ?? null,
                    'tracking_number' => $document['trackingNumber'] ?? data_get($response, 'tracking_number'),
                    'alerts' => $document['alerts'] ?? [],
                    'raw' => $document,
                ],
            ];
        }

        foreach ((array) data_get($response, 'carrier_metadata.package_documents', []) as $document) {
            $type = (string) ($document['contentType'] ?? $document['docType'] ?? $document['contentKey'] ?? 'label');
            $documents[] = [
                'type' => $type,
                'format' => $this->normalizeDocumentFormat((string) ($document['docType'] ?? $document['fileType'] ?? $document['fileFormat'] ?? $document['contentType'] ?? $carrierShipment->label_format)),
                'content' => $document['encodedLabel'] ?? $document['content'] ?? null,
                'url' => $document['url'] ?? $document['documentUrl'] ?? null,
                'urlExpiry' => $document['urlExpiry'] ?? $document['expirationTime'] ?? null,
                'filename' => $document['fileName'] ?? null,
                'metadata' => [
                    'carrier_document_type' => $type,
                    'content_key' => $document['contentKey'] ?? null,
                    'tracking_number' => $document['trackingNumber'] ?? data_get($response, 'tracking_number'),
                    'copies_to_print' => $document['copiesToPrint'] ?? null,
                    'raw' => $document,
                ],
            ];
        }

        return $documents;
    }

    private function normalizeDocumentFormat(string $format): string
    {
        $normalized = strtolower(trim($format));

        return match ($normalized) {
            'pdf', 'zpl', 'png', 'epl', 'html' => $normalized,
            'zplii' => 'zpl',
            default => 'pdf',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function storeCarrierDocumentBinary(
        Shipment $shipment,
        CarrierShipment $carrierShipment,
        string $filename,
        string $binary
    ): array {
        $disk = (string) config('filesystems.default', 'local');
        $path = sprintf(
            'carrier-documents/%s/%s/%s',
            (string) $shipment->id,
            (string) $carrierShipment->id,
            ltrim($filename, '/')
        );

        Storage::disk($disk)->put($path, $binary);

        return [$disk, $path];
    }

    private function findExistingCarrierDocument(
        CarrierShipment $carrierShipment,
        Shipment $shipment,
        string $type,
        string $format,
        ?string $checksum,
        string $downloadUrl,
        string $filename
    ): ?CarrierDocument {
        $query = CarrierDocument::query()
            ->where('carrier_shipment_id', (string) $carrierShipment->id)
            ->where('shipment_id', (string) $shipment->id)
            ->where('type', $type)
            ->where('format', $format);

        if ($checksum) {
            $query->where('checksum', $checksum);
        } elseif ($downloadUrl !== '') {
            $query->where('download_url', $downloadUrl);
        } else {
            $query->where('original_filename', $filename);
        }

        return $query->first();
    }

    private function resolveRetrievalMode(?string $binary, ?string $downloadUrl): string
    {
        if ($binary !== null && $binary !== '') {
            return CarrierDocument::RETRIEVAL_STORED_OBJECT;
        }

        if (filled($downloadUrl)) {
            return CarrierDocument::RETRIEVAL_URL;
        }

        return CarrierDocument::RETRIEVAL_INLINE;
    }

    private function recordDocumentAvailabilityEvent(
        Shipment $shipment,
        CarrierShipment $carrierShipment,
        Collection $storedDocuments,
        string $correlationId,
        string $idempotencyKey
    ): void {
        $this->shipmentTimeline->record($shipment, [
            'event_type' => 'carrier.documents_available',
            'status' => CarrierShipment::STATUS_LABEL_READY,
            'normalized_status' => CarrierShipment::STATUS_LABEL_READY,
            'description' => 'أصبحت مستندات الشحنة متاحة للتنزيل والطباعة.',
            'location' => strtoupper((string) $carrierShipment->carrier_code),
            'event_at' => now(),
            'source' => ShipmentEvent::SOURCE_CARRIER,
            'payload' => [
                'carrier_shipment_id' => (string) $carrierShipment->id,
                'carrier_code' => (string) $carrierShipment->carrier_code,
                'documents' => $storedDocuments->map(static fn (CarrierDocument $document): array => [
                    'id' => (string) $document->id,
                    'type' => (string) $document->type,
                    'format' => (string) $document->format,
                    'retrieval_mode' => (string) ($document->retrieval_mode ?? ''),
                ])->values()->all(),
            ],
            'correlation_id' => $correlationId,
            'idempotency_key' => $idempotencyKey . ':carrier.documents_available',
        ]);
        $this->shipmentTimeline->syncCurrentStatus($shipment, CarrierShipment::STATUS_LABEL_READY, now());
    }

    private function validateForCarrierCreation(Shipment $shipment): void
    {
        $shipment->loadMissing(['selectedRateOption', 'balanceReservation', 'contentDeclaration', 'parcels']);

        if (! $shipment->selectedRateOption) {
            throw BusinessException::make(
                'ERR_SELECTED_OFFER_REQUIRED',
                'A selected shipment offer is required before carrier creation can begin.',
                ['shipment_id' => (string) $shipment->id],
                422
            );
        }

        if ($shipment->status === Shipment::STATUS_REQUIRES_ACTION) {
            throw BusinessException::make(
                'ERR_DG_HOLD_REQUIRED',
                'This shipment is on hold and cannot continue to carrier creation.',
                [
                    'shipment_id' => (string) $shipment->id,
                    'current_status' => (string) $shipment->status,
                    'next_action' => 'Resolve the dangerous goods hold before continuing.',
                ],
                422
            );
        }

        if ($shipment->status !== Shipment::STATUS_PAYMENT_PENDING) {
            throw BusinessException::make(
                'ERR_INVALID_STATE_FOR_CARRIER',
                'Shipment must complete declaration and wallet pre-flight before carrier creation.',
                [
                    'shipment_id' => (string) $shipment->id,
                    'current_status' => (string) $shipment->status,
                    'allowed_statuses' => [Shipment::STATUS_PAYMENT_PENDING],
                ],
                422
            );
        }

        $declaration = $shipment->contentDeclaration;
        if (! $declaration || $declaration->status !== ContentDeclaration::STATUS_COMPLETED) {
            throw BusinessException::make(
                'ERR_DG_DECLARATION_INCOMPLETE',
                'Dangerous goods declaration must be completed before carrier creation.',
                ['shipment_id' => (string) $shipment->id],
                422
            );
        }

        if ((bool) ($declaration->contains_dangerous_goods ?? false)) {
            throw BusinessException::make(
                'ERR_DG_HOLD_REQUIRED',
                'Dangerous goods shipments require manual handling and cannot continue through normal carrier creation.',
                ['shipment_id' => (string) $shipment->id],
                422
            );
        }

        $activeReservation = $this->resolveActiveReservation($shipment);
        if (! $activeReservation) {
            throw BusinessException::make(
                'ERR_WALLET_RESERVATION_REQUIRED',
                'An active wallet reservation is required before carrier creation.',
                ['shipment_id' => (string) $shipment->id],
                422
            );
        }

        $expectedAmount = $this->resolveExpectedReservationAmount($shipment);
        if (round((float) $activeReservation->amount, 2) !== round($expectedAmount, 2)) {
            throw BusinessException::make(
                'ERR_PREFLIGHT_AMOUNT_MISMATCH',
                'The active wallet reservation does not match the selected shipment offer total.',
                [
                    'shipment_id' => (string) $shipment->id,
                    'reservation_id' => (string) $activeReservation->id,
                    'reserved_amount' => (float) $activeReservation->amount,
                    'expected_amount' => $expectedAmount,
                ],
                422
            );
        }

        if ($shipment->parcels()->count() === 0) {
            throw BusinessException::make(
                'ERR_NO_PARCELS',
                'Shipment must have at least one parcel before carrier creation.',
                ['shipment_id' => (string) $shipment->id],
                422
            );
        }

        if (empty($shipment->sender_name) || empty($shipment->recipient_name)) {
            throw BusinessException::make(
                'ERR_MISSING_ADDRESS',
                'Shipment must have complete sender and recipient information before carrier creation.',
                ['shipment_id' => (string) $shipment->id],
                422
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCarrierCreationContext(
        Shipment $shipment,
        string $labelFormat,
        string $labelSize,
        string $correlationId,
        string $idempotencyKey
    ): array {
        $selectedOption = $shipment->selectedRateOption;

        return [
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $shipment->account_id,
            'rate_quote_id' => (string) ($shipment->rate_quote_id ?? ''),
            'selected_rate_option_id' => (string) ($shipment->selected_rate_option_id ?? ''),
            'carrier_code' => (string) ($selectedOption?->carrier_code ?? $shipment->carrier_code ?? 'fedex'),
            'carrier_name' => (string) ($selectedOption?->carrier_name ?? $shipment->carrier_name ?? 'FedEx'),
            'service_code' => (string) ($selectedOption?->service_code ?? $shipment->service_code ?? ''),
            'service_name' => (string) ($selectedOption?->service_name ?? $shipment->service_name ?? ''),
            'currency' => (string) ($shipment->currency ?? $selectedOption?->currency ?? 'SAR'),
            'label_format' => $labelFormat,
            'label_size' => $labelSize,
            'correlation_id' => $correlationId,
            'idempotency_key' => $idempotencyKey,
            'sender_name' => (string) ($shipment->sender_name ?? ''),
            'sender_company' => (string) ($shipment->sender_company ?? ''),
            'sender_phone' => (string) ($shipment->sender_phone ?? ''),
            'sender_email' => (string) ($shipment->sender_email ?? ''),
            'sender_address_1' => (string) ($shipment->sender_address_1 ?? ''),
            'sender_address_2' => (string) ($shipment->sender_address_2 ?? ''),
            'sender_city' => (string) ($shipment->sender_city ?? ''),
            'sender_state' => (string) ($shipment->sender_state ?? ''),
            'sender_postal_code' => (string) ($shipment->sender_postal_code ?? ''),
            'sender_country' => (string) ($shipment->sender_country ?? ''),
            'recipient_name' => (string) ($shipment->recipient_name ?? ''),
            'recipient_company' => (string) ($shipment->recipient_company ?? ''),
            'recipient_phone' => (string) ($shipment->recipient_phone ?? ''),
            'recipient_email' => (string) ($shipment->recipient_email ?? ''),
            'recipient_address_1' => (string) ($shipment->recipient_address_1 ?? ''),
            'recipient_address_2' => (string) ($shipment->recipient_address_2 ?? ''),
            'recipient_city' => (string) ($shipment->recipient_city ?? ''),
            'recipient_state' => (string) ($shipment->recipient_state ?? ''),
            'recipient_postal_code' => (string) ($shipment->recipient_postal_code ?? ''),
            'recipient_country' => (string) ($shipment->recipient_country ?? ''),
            'total_weight' => (float) ($shipment->total_weight ?? $shipment->chargeable_weight ?? 0),
            'chargeable_weight' => (float) ($shipment->chargeable_weight ?? $shipment->total_weight ?? 0),
            'parcels' => $shipment->parcels->map(static fn ($parcel) => [
                'weight' => (float) ($parcel->weight ?? 0),
                'length' => $parcel->length,
                'width' => $parcel->width,
                'height' => $parcel->height,
                'packaging_type' => 'YOUR_PACKAGING',
            ])->values()->all(),
        ];
    }

    private function resolveShipmentProvider(Shipment $shipment): CarrierShipmentProvider
    {
        $carrierCode = $this->resolveCarrierCodeForCreation($shipment);

        if ($carrierCode !== CarrierShipment::CARRIER_FEDEX) {
            throw BusinessException::make(
                'ERR_CARRIER_PROVIDER_NOT_SUPPORTED',
                'The selected carrier is not yet supported for real shipment creation.',
                [
                    'shipment_id' => (string) $shipment->id,
                    'carrier_code' => $carrierCode,
                ],
                422
            );
        }

        if (! $this->fedexShipmentProvider->isEnabled()) {
            throw BusinessException::make(
                'ERR_FEDEX_NOT_ENABLED',
                'FedEx shipment creation is not enabled for this environment.',
                [
                    'shipment_id' => (string) $shipment->id,
                    'carrier_code' => $carrierCode,
                ],
                503
            );
        }

        return $this->fedexShipmentProvider;
    }

    private function resolveCarrierCodeForCreation(Shipment $shipment): string
    {
        $selectedOptionCarrier = strtolower(trim((string) ($shipment->selectedRateOption?->carrier_code ?? '')));
        if ($selectedOptionCarrier === CarrierShipment::CARRIER_FEDEX) {
            return CarrierShipment::CARRIER_FEDEX;
        }

        $shipmentCarrier = strtolower(trim((string) ($shipment->carrier_code ?? '')));
        if ($shipmentCarrier === CarrierShipment::CARRIER_FEDEX) {
            return CarrierShipment::CARRIER_FEDEX;
        }

        if ($selectedOptionCarrier !== '') {
            return $selectedOptionCarrier;
        }

        if ($shipmentCarrier !== '') {
            return $shipmentCarrier;
        }

        return CarrierShipment::CARRIER_FEDEX;
    }

    private function resolveActiveReservation(Shipment $shipment): ?WalletHold
    {
        $hold = $shipment->balanceReservation;
        if ($hold && $hold->isActive()) {
            return $hold;
        }

        return WalletHold::query()
            ->where('shipment_id', (string) $shipment->id)
            ->active()
            ->latest('created_at')
            ->first();
    }

    private function resolveLinkedReservation(Shipment $shipment): ?WalletHold
    {
        $reservationId = trim((string) ($shipment->balance_reservation_id ?? ''));
        if ($reservationId !== '') {
            return WalletHold::query()->find($reservationId);
        }

        return WalletHold::query()
            ->where('shipment_id', (string) $shipment->id)
            ->latest('created_at')
            ->first();
    }

    private function resolveExpectedReservationAmount(Shipment $shipment): float
    {
        $selectedOption = $shipment->selectedRateOption;
        $amount = $shipment->total_charge !== null
            ? (float) $shipment->total_charge
            : (float) ($selectedOption?->retail_rate ?? 0);

        if ($amount <= 0) {
            throw BusinessException::make(
                'ERR_INVALID_SELECTED_OFFER_TOTAL',
                'The selected shipment offer does not have a valid billable total for carrier creation.',
                [
                    'shipment_id' => (string) $shipment->id,
                    'selected_rate_option_id' => (string) ($shipment->selected_rate_option_id ?? ''),
                ],
                422
            );
        }

        return round($amount, 2);
    }

    private function captureActiveReservationForIssuedShipment(Shipment $shipment): WalletHold
    {
        $reservation = $this->resolveActiveReservation($shipment);

        if (! $reservation) {
            throw BusinessException::make(
                'ERR_WALLET_RESERVATION_REQUIRED',
                'An active wallet reservation is required before carrier creation.',
                ['shipment_id' => (string) $shipment->id],
                422
            );
        }

        return $this->billing->captureHold((string) $reservation->id);
    }

    /**
     * @param array<string, mixed> $response
     * @param WalletHold|null $reservation
     * @return array<string, mixed>
     */
    private function buildShipmentCarrierCreationUpdates(
        Shipment $shipment,
        CarrierShipment $carrierShipment,
        array $response,
        ?WalletHold $reservation = null
    ): array {
        $updates = [
            'status' => Shipment::STATUS_PURCHASED,
        ];

        $trackingNumber = $response['tracking_number'] ?? null;
        $carrierShipmentId = $response['carrier_shipment_id'] ?? (string) $carrierShipment->id;
        $serviceCode = $response['service_code'] ?? $carrierShipment->service_code;
        $serviceName = $response['service_name'] ?? $carrierShipment->service_name;

        if (Schema::hasColumn('shipments', 'tracking_number')) {
            $updates['tracking_number'] = $trackingNumber;
        } elseif (Schema::hasColumn('shipments', 'carrier_tracking_number')) {
            $updates['carrier_tracking_number'] = $trackingNumber;
        }

        if (Schema::hasColumn('shipments', 'carrier_shipment_id')) {
            $updates['carrier_shipment_id'] = $carrierShipmentId;
        }

        if (Schema::hasColumn('shipments', 'service_code')) {
            $updates['service_code'] = $serviceCode;
        }

        if (Schema::hasColumn('shipments', 'service_name')) {
            $updates['service_name'] = $serviceName;
        }

        if (Schema::hasColumn('shipments', 'carrier_code')) {
            $updates['carrier_code'] = $response['carrier_code'] ?? $carrierShipment->carrier_code;
        }

        if (Schema::hasColumn('shipments', 'carrier_name')) {
            $updates['carrier_name'] = $response['carrier_name'] ?? $carrierShipment->carrier_name;
        }

        if ($reservation) {
            if (Schema::hasColumn('shipments', 'balance_reservation_id')) {
                $updates['balance_reservation_id'] = (string) $reservation->id;
            }

            if (Schema::hasColumn('shipments', 'reserved_amount')) {
                $updates['reserved_amount'] = (float) $reservation->amount;
            }
        }

        return $updates;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildReservationMetadata(?WalletHold $reservation, string $lifecycle): ?array
    {
        if (! $reservation) {
            return null;
        }

        return [
            'id' => (string) $reservation->id,
            'status' => (string) $reservation->status,
            'amount' => (float) $reservation->amount,
            'currency' => (string) ($reservation->currency ?? ''),
            'lifecycle' => $lifecycle,
            'captured_at' => optional($reservation->captured_at)?->toIso8601String(),
            'released_at' => optional($reservation->released_at)?->toIso8601String(),
        ];
    }

    private function recordStatusHistory(
        Shipment $shipment,
        string $fromStatus,
        string $toStatus,
        string $source = 'system',
        ?string $changedBy = null,
        ?string $reason = null
    ): void {
        ShipmentStatusHistory::create([
            'shipment_id' => $shipment->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'source' => $source,
            'reason' => $reason,
            'changed_by' => $changedBy,
            'created_at' => now(),
        ]);
    }

    private function mapDocumentType(string $type): string
    {
        return match (strtolower(str_replace([' ', '-'], '_', $type))) {
            'label', 'waybill_doc', 'shipping_label' => CarrierDocument::TYPE_LABEL,
            'invoice', 'commercial', 'commercial_invoice', 'proforma_invoice' => CarrierDocument::TYPE_COMMERCIAL_INVOICE,
            'customs', 'customs_declaration', 'cn23', 'cn22', 'certificate_of_origin' => CarrierDocument::TYPE_CUSTOMS_DECLARATION,
            'waybill', 'awb' => CarrierDocument::TYPE_WAYBILL,
            'receipt' => CarrierDocument::TYPE_RECEIPT,
            'return_label', 'return_instructions' => CarrierDocument::TYPE_RETURN_LABEL,
            default => CarrierDocument::TYPE_OTHER,
        };
    }

    private function generateDocFilename(Shipment $shipment, string $type, string $format): string
    {
        $ref = $shipment->reference_number ?? $shipment->id;

        return "{$type}_{$ref}.{$format}";
    }

    private function assertLegacyDhlCarrierShipment(CarrierShipment $carrierShipment, string $operation): void
    {
        if ($carrierShipment->carrier_code === CarrierShipment::CARRIER_DHL) {
            return;
        }

        throw BusinessException::make(
            'ERR_CARRIER_OPERATION_NOT_SUPPORTED',
            'This carrier operation is not implemented for the selected provider yet: ' . $operation,
            [
                'carrier_code' => (string) $carrierShipment->carrier_code,
                'carrier_shipment_id' => (string) $carrierShipment->id,
                'operation' => $operation,
            ],
            422
        );
    }

    private function logCarrierError(
        string $operation,
        \Throwable $e,
        string $correlationId,
        ?string $shipmentId = null,
        ?string $carrierShipmentId = null
    ): CarrierError {
        if ($e instanceof BusinessException) {
            $context = $e->getContext();
            $carrierCode = strtolower(trim((string) ($context['carrier_code'] ?? 'dhl')));
            $httpStatus = (int) ($context['http_status'] ?? $e->getHttpStatus() ?? 500);
            $carrierErrorCode = trim((string) ($context['carrier_error_code'] ?? ''));
            $carrierErrorMessage = trim((string) ($context['carrier_error_message'] ?? $e->getMessage()));
            $requestPayload = $context['request_payload'] ?? null;
            $responseBody = $context['response_body'] ?? ['message' => $e->getMessage()];
            $internalCode = $this->resolveInternalCarrierErrorCode($carrierCode, $httpStatus, $carrierErrorCode, $carrierErrorMessage);

            return CarrierError::create([
                'shipment_id' => $shipmentId,
                'carrier_shipment_id' => $carrierShipmentId,
                'carrier_code' => $carrierCode,
                'correlation_id' => $correlationId,
                'operation' => $operation,
                'internal_code' => $internalCode,
                'carrier_error_code' => $carrierErrorCode !== '' ? $carrierErrorCode : null,
                'carrier_error_message' => $carrierErrorMessage !== '' ? $carrierErrorMessage : $e->getMessage(),
                'internal_message' => CarrierError::getInternalMessage($internalCode),
                'http_status' => $httpStatus,
                'http_method' => (string) ($context['method'] ?? 'POST'),
                'endpoint_url' => (string) ($context['endpoint_url'] ?? ''),
                'is_retriable' => in_array($internalCode, CarrierError::RETRIABLE_CODES, true),
                'max_retries' => 3,
                'request_context' => [
                    'request_payload' => $requestPayload,
                    'exception_class' => get_class($e),
                ],
                'response_body' => is_array($responseBody) ? $responseBody : ['message' => (string) $responseBody],
            ]);
        }

        $httpStatus = method_exists($e, 'getCode') ? (int) $e->getCode() : 0;
        $responseBody = null;

        if (method_exists($e, 'getResponse')) {
            try {
                $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            } catch (\Throwable $_) {
                $responseBody = null;
            }
        }

        return CarrierError::fromDhlResponse(
            $operation,
            $httpStatus ?: 500,
            $responseBody ?? ['message' => $e->getMessage()],
            $correlationId,
            $shipmentId,
            $carrierShipmentId,
            ['exception_class' => get_class($e)]
        );
    }

    private function resolveInternalCarrierErrorCode(
        string $carrierCode,
        int $httpStatus,
        ?string $carrierErrorCode = null,
        ?string $carrierErrorMessage = null
    ): string {
        $needle = strtolower(trim((string) ($carrierErrorCode ?? '')) . ' ' . trim((string) ($carrierErrorMessage ?? '')));

        if ($carrierCode === CarrierShipment::CARRIER_FEDEX) {
            if ($needle !== '' && str_contains($needle, 'duplicate')) {
                return CarrierError::ERR_DUPLICATE;
            }

            return match ($httpStatus) {
                400 => CarrierError::ERR_VALIDATION,
                401, 403 => CarrierError::ERR_AUTH_FAILED,
                404 => CarrierError::ERR_SHIPMENT_NOT_FOUND,
                429 => CarrierError::ERR_RATE_LIMITED,
                500 => CarrierError::ERR_CARRIER_INTERNAL,
                502, 503 => CarrierError::ERR_SERVICE_UNAVAILABLE,
                504 => CarrierError::ERR_NETWORK_TIMEOUT,
                default => CarrierError::ERR_UNKNOWN,
            };
        }

        return CarrierError::mapDhlError($httpStatus, $carrierErrorCode);
    }
}
