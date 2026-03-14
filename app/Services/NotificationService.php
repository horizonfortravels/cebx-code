<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NotificationService — FR-NTF-001→009 (9 requirements)
 *
 * FR-NTF-001: Send notifications for core events
 * FR-NTF-002: Multi-channel support (email/SMS/in_app/webhook/Slack)
 * FR-NTF-003: User notification preferences
 * FR-NTF-004: Customizable templates (per account)
 * FR-NTF-005: Rate limiting & deduplication
 * FR-NTF-006: Multi-language (AR/EN)
 * FR-NTF-007: Scheduled/digest notifications
 * FR-NTF-008: Complete notification log
 * FR-NTF-009: Third-party integration (CRM/Webhooks)
 */
class NotificationService
{
    // ── FR-NTF-005: Rate limit defaults ──────────────────────
    private int $maxPerHour = 50;
    private int $maxPerDay = 200;

    public function __construct(
        private AuditService $audit,
    ) {}

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-001: Send Notification for Event
    // ═══════════════════════════════════════════════════════════

    /**
     * Dispatch notification(s) for a business event.
     * Resolves recipients, channels, templates, preferences, rate limits.
     */
    public function dispatch(
        string $eventType,
        Account $account,
        array $eventData = [],
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $recipientUserIds = null,
    ): array {
        $results = [];

        // Determine recipients
        $users = $recipientUserIds
            ? User::whereIn('id', $recipientUserIds)->where('account_id', $account->id)->get()
            : $this->resolveRecipients($account, $eventType);

        foreach ($users as $user) {
            $channels = $this->resolveChannels($user, $account, $eventType);

            foreach ($channels as $channelName) {
                $result = $this->sendToChannel(
                    $user, $account, $eventType, $channelName,
                    $eventData, $entityType, $entityId
                );
                if ($result) $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Send to a specific channel for a user.
     */
    private function sendToChannel(
        User $user,
        Account $account,
        string $eventType,
        string $channel,
        array $eventData,
        ?string $entityType,
        ?string $entityId
    ): ?Notification {
        // ── FR-NTF-003: Check user preference ────────────────
        $enabled = NotificationPreference::isEnabled($user->id, $eventType, $channel);
        if ($enabled === false) return null; // Explicitly disabled

        // ── FR-NTF-005: Rate limiting ────────────────────────
        if ($this->isRateLimited($user->id, $channel)) {
            return $this->logThrottled($user, $account, $eventType, $channel, $eventData, $entityType, $entityId);
        }

        // ── FR-NTF-007: Check if scheduled (digest) ─────────
        $schedule = $this->getSchedule($user, $channel, $eventType);
        if ($schedule && $schedule->frequency !== NotificationSchedule::FREQ_IMMEDIATE) {
            return $this->queueForDigest($user, $account, $eventType, $channel, $eventData, $entityType, $entityId, $schedule);
        }

        // ── FR-NTF-006: Resolve language ─────────────────────
        $language = $this->resolveLanguage($user, $account, $eventType, $channel);

        // ── FR-NTF-004: Resolve template ─────────────────────
        $template = NotificationTemplate::resolve($eventType, $channel, $language, $account->id);

        $destination = $this->resolveDestination($user, $channel, $eventType);
        if (!$destination) return null;

        // Render content
        $content = $template
            ? $template->render(array_merge($eventData, ['user_name' => $user->name, 'account_name' => $account->name]))
            : ['subject' => $eventType, 'body' => json_encode($eventData), 'body_html' => null];

        // ── FR-NTF-008: Create notification log ──────────────
        $notification = Notification::create([
            'account_id'  => $account->id,
            'user_id'     => $user->id,
            'event_type'  => $eventType,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'event_data'  => $eventData,
            'channel'     => $channel,
            'destination' => $destination,
            'language'    => $language,
            'subject'     => $content['subject'],
            'body'        => $content['body'],
            'template_id' => $template?->id,
            'status'      => Notification::STATUS_QUEUED,
            'provider'    => $this->resolveProvider($account, $channel),
        ]);

        // ── Actual sending (would be async in production) ────
        $this->send($notification);

        return $notification;
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-002: Multi-Channel Send
    // ═══════════════════════════════════════════════════════════

    /**
     * Send notification via the appropriate channel.
     */
    private function send(Notification $notification): void
    {
        try {
            $notification->update(['status' => Notification::STATUS_SENDING]);

            $externalId = match ($notification->channel) {
                Notification::CHANNEL_EMAIL   => $this->sendEmail($notification),
                Notification::CHANNEL_SMS     => $this->sendSms($notification),
                Notification::CHANNEL_IN_APP  => $this->sendInApp($notification),
                Notification::CHANNEL_WEBHOOK => $this->sendWebhook($notification),
                Notification::CHANNEL_SLACK   => $this->sendSlack($notification),
                default => throw new \RuntimeException("Unknown channel: {$notification->channel}"),
            };

            $notification->markSent($externalId);
        } catch (\Exception $e) {
            $notification->markFailed($e->getMessage());
        }
    }

    private function sendEmail(Notification $n): ?string
    {
        // In production: Mail::to($n->destination)->send(new NotificationMail($n));
        return 'email_' . Str::random(16);
    }

    private function sendSms(Notification $n): ?string
    {
        // In production: Twilio/provider integration
        return 'sms_' . Str::random(16);
    }

    private function sendInApp(Notification $n): ?string
    {
        // In-app: already stored in DB, no external send needed
        return null;
    }

    private function sendWebhook(Notification $n): ?string
    {
        // In production: HTTP POST to webhook URL
        return 'wh_' . Str::random(16);
    }

    private function sendSlack(Notification $n): ?string
    {
        // In production: Slack API
        return 'slack_' . Str::random(16);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-003: Retry Failed Notifications
    // ═══════════════════════════════════════════════════════════

    /**
     * Retry all failed notifications that are due for retry.
     */
    public function processRetryQueue(): array
    {
        $retryable = Notification::retryable()->get();
        $results = ['retried' => 0, 'succeeded' => 0, 'failed' => 0, 'dlq' => 0];

        foreach ($retryable as $notification) {
            $results['retried']++;
            $this->send($notification);

            if ($notification->fresh()->status === Notification::STATUS_SENT) {
                $results['succeeded']++;
            } elseif ($notification->fresh()->status === Notification::STATUS_DLQ) {
                $results['dlq']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-004: Template Management
    // ═══════════════════════════════════════════════════════════

    public function createTemplate(array $data, ?string $accountId = null): NotificationTemplate
    {
        return NotificationTemplate::create(array_merge($data, ['account_id' => $accountId]));
    }

    public function updateTemplate(string $templateId, array $data): NotificationTemplate
    {
        $template = NotificationTemplate::findOrFail($templateId);
        if ($template->is_system) {
            throw new BusinessException('Cannot modify system template', 'ERR_NTF_SYSTEM_TEMPLATE');
        }
        $template->update(array_merge($data, ['version' => $template->version + 1]));
        return $template;
    }

    public function previewTemplate(string $templateId, array $sampleData): array
    {
        $template = NotificationTemplate::findOrFail($templateId);
        return $template->render($sampleData);
    }

    public function listTemplates(?string $accountId, ?string $eventType = null): Collection
    {
        $query = NotificationTemplate::forAccount($accountId)->active();
        if ($eventType) $query->where('event_type', $eventType);
        return $query->get();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-005: Rate Limiting
    // ═══════════════════════════════════════════════════════════

    private function isRateLimited(string $userId, string $channel): bool
    {
        $hourCount = Notification::where('user_id', $userId)
            ->where('channel', $channel)
            ->where('created_at', '>=', now()->subHour())
            ->where('is_throttled', false)
            ->count();

        if ($hourCount >= $this->maxPerHour) return true;

        $dayCount = Notification::where('user_id', $userId)
            ->where('channel', $channel)
            ->where('created_at', '>=', now()->subDay())
            ->where('is_throttled', false)
            ->count();

        return $dayCount >= $this->maxPerDay;
    }

    private function logThrottled(User $user, Account $account, string $eventType, string $channel, array $eventData, ?string $entityType, ?string $entityId): Notification
    {
        return Notification::create([
            'account_id'  => $account->id,
            'user_id'     => $user->id,
            'event_type'  => $eventType,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'event_data'  => $eventData,
            'channel'     => $channel,
            'destination' => '-',
            'status'      => Notification::STATUS_PENDING,
            'is_throttled' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-007: Scheduled/Digest
    // ═══════════════════════════════════════════════════════════

    private function getSchedule(User $user, string $channel, string $eventType): ?NotificationSchedule
    {
        return NotificationSchedule::where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->where(function ($q) use ($eventType) {
                $q->whereNull('event_types')
                    ->orWhereJsonContains('event_types', $eventType);
            })
            ->first();
    }

    private function queueForDigest(User $user, Account $account, string $eventType, string $channel, array $eventData, ?string $entityType, ?string $entityId, NotificationSchedule $schedule): Notification
    {
        return Notification::create([
            'account_id'  => $account->id,
            'user_id'     => $user->id,
            'event_type'  => $eventType,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'event_data'  => $eventData,
            'channel'     => $channel,
            'destination' => $this->resolveDestination($user, $channel, $eventType) ?? '-',
            'status'      => Notification::STATUS_PENDING,
            'is_batched'  => true,
            'batch_id'    => "digest_{$user->id}_{$schedule->frequency}_" . now()->format('Ymd'),
            'scheduled_at' => $schedule->next_send_at,
        ]);
    }

    /**
     * Process all due digest schedules.
     */
    public function processDigests(): array
    {
        $dueSchedules = NotificationSchedule::due()->get();
        $results = ['processed' => 0, 'notifications_sent' => 0];

        foreach ($dueSchedules as $schedule) {
            $pending = Notification::where('user_id', $schedule->user_id)
                ->where('is_batched', true)
                ->where('status', Notification::STATUS_PENDING)
                ->get();

            if ($pending->isNotEmpty()) {
                // Aggregate into a digest notification
                $digest = $this->createDigest($schedule, $pending);
                $this->send($digest);
                $pending->each(fn($n) => $n->update(['status' => Notification::STATUS_SENT]));
                $results['notifications_sent']++;
            }

            $schedule->calculateNextSend();
            $results['processed']++;
        }

        return $results;
    }

    private function createDigest(NotificationSchedule $schedule, Collection $pending): Notification
    {
        $user = $schedule->user;
        $account = Account::find($schedule->account_id);

        $body = $pending->map(fn($n) => "• [{$n->event_type}] " . ($n->subject ?? json_encode($n->event_data)))->implode("\n");

        return Notification::create([
            'account_id'  => $schedule->account_id,
            'user_id'     => $schedule->user_id,
            'event_type'  => 'digest.' . $schedule->frequency,
            'channel'     => $schedule->channel,
            'destination' => $this->resolveDestination($user, $schedule->channel, '') ?? $user->email,
            'subject'     => "ملخص الإشعارات - {$pending->count()} تحديث",
            'body'        => $body,
            'status'      => Notification::STATUS_QUEUED,
            'is_batched'  => true,
            'batch_id'    => "digest_final_{$schedule->id}_" . now()->format('YmdHi'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-008: Notification Log
    // ═══════════════════════════════════════════════════════════

    public function getLog(Account $account, ?string $userId = null, int $perPage = 20)
    {
        $query = Notification::where('account_id', $account->id);
        if ($userId) $query->where('user_id', $userId);
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getInAppNotifications(User $user, int $limit = 50): Collection
    {
        return Notification::forUser($user->id)
            ->where('channel', Notification::CHANNEL_IN_APP)
            ->limit($limit)
            ->get();
    }

    public function markAsRead(string $notificationId, User $user): void
    {
        Notification::where('id', $notificationId)
            ->where('user_id', $user->id)
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('channel', Notification::CHANNEL_IN_APP)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function getUnreadCount(User $user): int
    {
        return Notification::forUser($user->id)->unread()->count();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-009: Third-Party Channel Config
    // ═══════════════════════════════════════════════════════════

    public function configureChannel(Account $account, array $data): NotificationChannel
    {
        return NotificationChannel::updateOrCreate(
            [
                'account_id' => $account->id,
                'channel'    => $data['channel'],
                'provider'   => $data['provider'],
            ],
            [
                'name'           => $data['name'],
                'config'         => $data['config'] ?? null,
                'webhook_url'    => $data['webhook_url'] ?? null,
                'webhook_secret' => $data['webhook_secret'] ?? null,
                'is_active'      => $data['is_active'] ?? true,
            ]
        );
    }

    public function listChannels(Account $account): Collection
    {
        return NotificationChannel::where('account_id', $account->id)->get();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-003: Preferences Management
    // ═══════════════════════════════════════════════════════════

    public function updatePreferences(User $user, array $preferences): void
    {
        NotificationPreference::bulkUpdate($user->id, $user->account_id, $preferences);
    }

    public function getPreferences(User $user): Collection
    {
        return NotificationPreference::where('user_id', $user->id)->get();
    }

    // ═══════════════════════════════════════════════════════════
    // Internal Helpers
    // ═══════════════════════════════════════════════════════════

    private function resolveRecipients(Account $account, string $eventType): Collection
    {
        return User::where('account_id', $account->id)
            ->where('is_active', true)
            ->get();
    }

    private function resolveChannels(User $user, Account $account, string $eventType): array
    {
        // Get user-enabled channels, default to email + in_app
        $prefs = NotificationPreference::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->where('enabled', true)
            ->pluck('channel')
            ->toArray();

        if (empty($prefs)) {
            return [Notification::CHANNEL_EMAIL, Notification::CHANNEL_IN_APP];
        }

        return $prefs;
    }

    private function resolveLanguage(User $user, Account $account, string $eventType, string $channel): string
    {
        // User pref → account default → ar
        $pref = NotificationPreference::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->first();

        return $pref?->language ?? $user->language ?? $account->language ?? 'ar';
    }

    private function resolveDestination(User $user, string $channel, string $eventType): ?string
    {
        $pref = NotificationPreference::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->first();

        if ($pref?->destination) return $pref->destination;

        return match ($channel) {
            Notification::CHANNEL_EMAIL  => $user->email,
            Notification::CHANNEL_SMS    => $user->phone ?? null,
            Notification::CHANNEL_IN_APP => $user->id,
            default => null,
        };
    }

    private function resolveProvider(Account $account, string $channel): ?string
    {
        $config = NotificationChannel::where('account_id', $account->id)
            ->forChannel($channel)
            ->first();

        return $config?->provider ?? match ($channel) {
            Notification::CHANNEL_EMAIL => 'mailgun',
            Notification::CHANNEL_SMS   => 'twilio',
            default => null,
        };
    }
}
