<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * NotificationController — FR-NTF-001→009
 *
 * GET    /notifications                    — FR-NTF-008: Notification log
 * GET    /notifications/in-app             — FR-NTF-001: In-app notifications
 * GET    /notifications/unread-count       — FR-NTF-001: Unread count
 * POST   /notifications/{id}/read          — FR-NTF-001: Mark as read
 * POST   /notifications/read-all           — FR-NTF-001: Mark all read
 * GET    /notifications/preferences        — FR-NTF-003: Get preferences
 * PUT    /notifications/preferences        — FR-NTF-003: Update preferences
 * GET    /notifications/templates          — FR-NTF-004: List templates
 * POST   /notifications/templates          — FR-NTF-004: Create template
 * PUT    /notifications/templates/{id}     — FR-NTF-004: Update template
 * POST   /notifications/templates/{id}/preview — FR-NTF-004: Preview
 * GET    /notifications/channels           — FR-NTF-009: List channels
 * POST   /notifications/channels           — FR-NTF-009: Configure channel
 * POST   /notifications/test               — FR-NTF-002: Test send
 * POST   /notifications/schedules          — FR-NTF-007: Create schedule
 * GET    /notifications/schedules          — FR-NTF-007: List schedules
 */
class NotificationController extends Controller
{
    public function __construct(private NotificationService $service) {}

    /**
     * FR-NTF-008: Get notification log.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        $request->validate([
            'user_id'  => 'nullable|uuid',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Notification::query()
            ->where('account_id', $this->resolveCurrentAccountId($request));

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $data = $query
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * FR-NTF-001: Get in-app notifications for current user.
     */
    public function inApp(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        $data = Notification::query()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->when(
                $this->notificationHasColumn('channel'),
                static fn ($query) => $query->where('channel', 'in_app')
            )
            ->get();

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * FR-NTF-001: Get unread count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        $count = Notification::query()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->when(
                $this->notificationHasColumn('channel'),
                static fn ($query) => $query->where('channel', 'in_app')
            )
            ->count();

        return response()->json([
            'status' => 'success',
            'data'   => ['unread_count' => $count],
        ]);
    }

    /**
     * FR-NTF-001: Mark notification as read.
     */
    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = Notification::query()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->where('id', $notificationId)
            ->firstOrFail();

        $this->authorize('manage', $notification);

        $notification->update(['read_at' => now()]);

        return response()->json(['status' => 'success']);
    }

    /**
     * FR-NTF-001: Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorize('manage', Notification::class);

        $count = Notification::query()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->when(
                $this->notificationHasColumn('channel'),
                static fn ($query) => $query->where('channel', 'in_app')
            )
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'success', 'data' => ['marked' => $count]]);
    }

    /**
     * FR-NTF-003: Get user preferences.
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        return response()->json([
            'status' => 'success',
            'data'   => NotificationPreference::query()
                ->where('account_id', $this->resolveCurrentAccountId($request))
                ->where('user_id', $request->user()->id)
                ->get(),
        ]);
    }

    /**
     * FR-NTF-003: Update user preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $this->authorize('manage', Notification::class);

        $data = $request->validate([
            'preferences'              => 'required|array|min:1',
            'preferences.*.event_type' => 'required|string',
            'preferences.*.channel'    => 'required|string',
            'preferences.*.enabled'    => 'required|boolean',
            'preferences.*.language'   => 'nullable|string|max:5',
            'preferences.*.destination' => 'nullable|string|max:500',
        ]);

        NotificationPreference::bulkUpdate(
            (string) $request->user()->id,
            $this->resolveCurrentAccountId($request),
            $data['preferences']
        );

        return response()->json(['status' => 'success', 'message' => 'Preferences updated']);
    }

    /**
     * FR-NTF-004: List templates.
     */
    public function listTemplates(Request $request): JsonResponse
    {
        $this->authorize('manageTemplates', NotificationTemplate::class);

        $templates = NotificationTemplate::query()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->when($request->filled('event_type'), function ($query) use ($request): void {
                $query->where('event_type', $request->input('event_type'));
            })
            ->get();

        return response()->json(['status' => 'success', 'data' => $templates]);
    }

    /**
     * FR-NTF-004: Create template.
     */
    public function createTemplate(Request $request): JsonResponse
    {
        $this->authorize('manageTemplates', NotificationTemplate::class);

        $data = $request->validate([
            'event_type'   => 'required|string|max:100',
            'channel'      => 'required|string|max:50',
            'language'     => 'required|string|max:5',
            'subject'      => 'nullable|string|max:500',
            'body'         => 'required|string',
            'body_html'    => 'nullable|string',
            'sender_name'  => 'nullable|string|max:200',
            'sender_email' => 'nullable|email|max:200',
            'variables'    => 'nullable|array',
        ]);

        $template = $this->service->createTemplate($data, $this->resolveCurrentAccountId($request));

        return response()->json(['status' => 'success', 'data' => $template], 201);
    }

    /**
     * FR-NTF-004: Update template.
     */
    public function updateTemplate(Request $request, string $templateId): JsonResponse
    {
        $template = NotificationTemplate::query()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $templateId)
            ->firstOrFail();

        $this->authorize('manageTemplates', $template);

        $data = $request->validate([
            'subject'   => 'nullable|string|max:500',
            'body'      => 'nullable|string',
            'body_html' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template = $this->service->updateTemplate((string) $template->id, $data);

        return response()->json(['status' => 'success', 'data' => $template]);
    }

    /**
     * FR-NTF-004: Preview template.
     */
    public function previewTemplate(Request $request, string $templateId): JsonResponse
    {
        $template = NotificationTemplate::query()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $templateId)
            ->firstOrFail();

        $this->authorize('manageTemplates', $template);

        $data = $request->validate(['sample_data' => 'required|array']);
        $rendered = $this->service->previewTemplate((string) $template->id, $data['sample_data']);

        return response()->json(['status' => 'success', 'data' => $rendered]);
    }

    /**
     * FR-NTF-009: List configured channels.
     */
    public function listChannels(Request $request): JsonResponse
    {
        $this->authorize('manageChannels', NotificationChannel::class);

        return response()->json([
            'status' => 'success',
            'data'   => NotificationChannel::query()
                ->where('account_id', $this->resolveCurrentAccountId($request))
                ->get(),
        ]);
    }

    /**
     * FR-NTF-009: Configure a notification channel.
     */
    public function configureChannel(Request $request): JsonResponse
    {
        $this->authorize('manageChannels', NotificationChannel::class);

        $data = $request->validate([
            'channel'        => 'required|string|max:50',
            'provider'       => 'required|string|max:100',
            'name'           => 'required|string|max:200',
            'config'         => 'nullable|array',
            'webhook_url'    => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:500',
            'is_active'      => 'nullable|boolean',
        ]);

        $account = Account::withoutGlobalScopes()
            ->where('id', $this->resolveCurrentAccountId($request))
            ->firstOrFail();

        $channel = $this->service->configureChannel($account, $data);

        return response()->json(['status' => 'success', 'data' => $channel], 201);
    }

    /**
     * FR-NTF-002: Test send notification.
     */
    public function testSend(Request $request): JsonResponse
    {
        $this->authorize('manage', Notification::class);

        $data = $request->validate([
            'event_type'  => 'required|string',
            'channel'     => 'required|string',
            'destination' => 'required|string',
        ]);

        $account = Account::withoutGlobalScopes()
            ->where('id', $this->resolveCurrentAccountId($request))
            ->firstOrFail();

        $results = $this->service->dispatch(
            $data['event_type'],
            $account,
            ['test' => true, 'timestamp' => now()->toIso8601String()],
            'test', 'test-001',
            [$request->user()->id]
        );

        return response()->json([
            'status' => 'success',
            'data'   => ['sent' => count($results)],
        ]);
    }

    /**
     * FR-NTF-007: Create notification schedule.
     */
    public function createSchedule(Request $request): JsonResponse
    {
        $this->authorize('manageSchedules', NotificationSchedule::class);

        $data = $request->validate([
            'frequency'   => 'required|in:immediate,hourly,daily,weekly',
            'time_of_day' => 'nullable|date_format:H:i',
            'day_of_week' => 'nullable|string',
            'timezone'    => 'nullable|string|max:50',
            'event_types' => 'nullable|array',
            'channel'     => 'required|string|max:50',
        ]);

        $schedule = NotificationSchedule::create(array_merge($data, [
            'account_id' => $this->resolveCurrentAccountId($request),
            'user_id'    => $request->user()->id,
            'is_active'  => true,
        ]));

        if ($schedule->frequency !== 'immediate') {
            $schedule->calculateNextSend();
        }

        return response()->json(['status' => 'success', 'data' => $schedule], 201);
    }

    /**
     * FR-NTF-007: List user schedules.
     */
    public function listSchedules(Request $request): JsonResponse
    {
        $this->authorize('manageSchedules', NotificationSchedule::class);

        $schedules = NotificationSchedule::query()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json(['status' => 'success', 'data' => $schedules]);
    }

    private function resolveCurrentAccountId(Request $request): string
    {
        $currentAccountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($currentAccountId !== '') {
            return $currentAccountId;
        }

        return trim((string) ($request->user()->account_id ?? ''));
    }

    private function notificationHasColumn(string $column): bool
    {
        static $cache = [];

        if (!array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasColumn('notifications', $column);
        }

        return $cache[$column];
    }
}
