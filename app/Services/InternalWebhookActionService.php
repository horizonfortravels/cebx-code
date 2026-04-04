<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Platforms\PlatformAdapterFactory;

class InternalWebhookActionService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly OrderService $orderService,
        private readonly InternalWebhookReadService $readService,
    ) {}

    public function retryStoreEvent(string $endpointRouteKey, string $eventId, User $actor, string $reason): WebhookEvent
    {
        if (! $actor->hasPermission('webhooks.manage')) {
            throw BusinessException::permissionDenied();
        }

        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');

        if ($reason === '') {
            throw new BusinessException(
                'A clear operator reason is required before retrying a failed webhook delivery.',
                'ERR_WEBHOOK_REASON_REQUIRED',
                422
            );
        }

        $parsed = $this->readService->parseRouteKey($endpointRouteKey);

        if (! is_array($parsed) || $parsed['kind'] !== 'store') {
            throw new BusinessException(
                'This webhook endpoint does not support the internal retry action.',
                'ERR_WEBHOOK_RETRY_UNAVAILABLE',
                422
            );
        }

        $store = Store::query()
            ->withoutGlobalScopes()
            ->findOrFail($parsed['id']);

        if (! PlatformAdapterFactory::supports((string) $store->platform)) {
            throw new BusinessException(
                'This store integration does not have a safe stored-event retry contract.',
                'ERR_WEBHOOK_RETRY_UNSUPPORTED',
                422
            );
        }

        $event = WebhookEvent::query()
            ->withoutGlobalScopes()
            ->where('store_id', (string) $store->id)
            ->findOrFail($eventId);

        if ((string) $event->status !== WebhookEvent::STATUS_FAILED) {
            throw new BusinessException(
                'Only failed store webhook deliveries can be retried from this internal surface.',
                'ERR_WEBHOOK_RETRY_INVALID_STATE',
                422
            );
        }

        $oldValues = $this->auditSnapshot($event);

        $this->orderService->processWebhookEvent($event);
        $event->refresh();

        $newValues = $this->auditSnapshot($event);

        $metadata = [
            'reason' => $reason,
            'platform' => (string) $store->platform,
            'event_type' => (string) $event->event_type,
            'used_stored_payload' => true,
            'had_external_event_reference' => trim((string) ($event->external_event_id ?? '')) !== '',
            'had_external_resource_reference' => trim((string) ($event->external_resource_id ?? '')) !== '',
        ];

        if ((string) $event->status !== WebhookEvent::STATUS_PROCESSED) {
            $this->auditService->warning(
                (string) $event->account_id,
                (string) $actor->id,
                'webhook.retry_failed',
                AuditLog::CATEGORY_ACCOUNT,
                'WebhookEvent',
                (string) $event->id,
                $oldValues,
                $newValues,
                $metadata
            );

            throw new BusinessException(
                'The webhook delivery could not be replayed safely with its stored payload.',
                'ERR_WEBHOOK_RETRY_FAILED',
                422
            );
        }

        $this->auditService->info(
            (string) $event->account_id,
            (string) $actor->id,
            'webhook.event_retried',
            AuditLog::CATEGORY_ACCOUNT,
            'WebhookEvent',
            (string) $event->id,
            $oldValues,
            $newValues,
            $metadata
        );

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(WebhookEvent $event): array
    {
        return [
            'status' => (string) $event->status,
            'retry_count' => (int) $event->retry_count,
            'processed_at' => optional($event->processed_at)?->toIso8601String(),
            'has_error' => trim((string) ($event->error_message ?? '')) !== '',
        ];
    }
}
