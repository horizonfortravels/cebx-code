<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\TicketReply;
use App\Models\User;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupportTicketConversationService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly InternalControlPlane $controlPlane,
    ) {}

    /**
     * @param Collection<int, SupportTicket> $tickets
     * @return Collection<string, array{summary: string, latest_at_label: string, count: int, items: Collection<int, array<string, mixed>>}>
     */
    public function summarizedCustomerVisibleThreadForTickets(Collection $tickets): Collection
    {
        $ticketIds = $tickets->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->filter(static fn (string $id): bool => $id !== '')
            ->values()
            ->all();

        if ($ticketIds === []) {
            return collect();
        }

        return $this->customerVisibleItemsByTicketIds($ticketIds)
            ->groupBy('ticket_id')
            ->map(function (Collection $rows): array {
                $latest = $rows->sortByDesc('created_at')->values()->first();
                $thread = $rows->sortBy('created_at')->values();

                return [
                    'summary' => is_array($latest)
                        ? sprintf('%s - %s', (string) $latest['actor_label'], (string) $latest['created_at_label'])
                        : 'لا توجد أي ردود مسجلة بعد',
                    'latest_at_label' => is_array($latest) ? (string) $latest['created_at_label'] : 'غير متاح',
                    'count' => $thread->count(),
                    'items' => $thread,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function customerVisibleThreadForTicket(SupportTicket $ticket): Collection
    {
        $summary = $this->summarizedCustomerVisibleThreadForTickets(collect([$ticket]))
            ->get((string) $ticket->id);

        if (! is_array($summary)) {
            return collect();
        }

        /** @var Collection<int, array<string, mixed>> $items */
        $items = $summary['items'];

        return $items;
    }

    /**
     * @throws BusinessException
     */
    public function addStaffReply(SupportTicket $ticket, string $body, User $actor): SupportTicketReply
    {
        if (! $this->isInternalUser($actor)) {
            throw BusinessException::make(
                'ERR_TICKET_REPLY_FORBIDDEN',
                'يمكن للموظفين الداخليين فقط إضافة ردود الدعم من مسار التذاكر الداخلي.',
                ['ticket_id' => (string) $ticket->id],
                403,
            );
        }

        return $this->createCustomerVisibleReply(
            $ticket,
            $body,
            $actor,
            'support.ticket_reply_added',
            'internal_tickets_thread',
        );
    }

    /**
     * @throws BusinessException
     */
    public function addCustomerReply(SupportTicket $ticket, string $body, User $actor): SupportTicketReply
    {
        return $this->createCustomerVisibleReply(
            $ticket,
            $body,
            $actor,
            'support.ticket_customer_reply_added',
            'external_support_thread',
        );
    }

    /**
     * @throws BusinessException
     */
    private function createCustomerVisibleReply(
        SupportTicket $ticket,
        string $body,
        User $actor,
        string $auditAction,
        string $surface,
    ): SupportTicketReply {
        $this->assertModernThreadSchema();
        $body = $this->normalizeRequiredBody($body);

        return DB::transaction(function () use ($ticket, $body, $actor, $auditAction, $surface): SupportTicketReply {
            $reply = SupportTicketReply::query()
                ->withoutGlobalScopes()
                ->create([
                    'ticket_id' => (string) $ticket->id,
                    'user_id' => (string) $actor->id,
                    'body' => $body,
                    'is_internal_note' => false,
                    'attachments' => null,
                ]);

            $ticket->touch();

            $this->auditService->info(
                (string) $ticket->account_id,
                (string) $actor->id,
                $auditAction,
                AuditLog::CATEGORY_SYSTEM,
                'SupportTicket',
                (string) $ticket->id,
                null,
                [
                    'reply_id' => (string) $reply->id,
                    'reply_length' => mb_strlen($body),
                    'visibility' => 'customer',
                    'is_internal_note' => false,
                ],
                [
                    'surface' => $surface,
                    'ticket_number' => $this->ticketNumber($ticket),
                    'actor_type' => $this->isInternalUser($actor) ? 'internal' : 'external',
                    'actor_role' => $this->isInternalUser($actor)
                        ? (string) ($this->controlPlane->primaryCanonicalRole($actor) ?? 'internal')
                        : 'external',
                ],
            );

            return $reply->fresh('user');
        });
    }

    private function assertModernThreadSchema(): void
    {
        if (! Schema::hasTable('support_ticket_replies')) {
            throw BusinessException::make(
                'ERR_TICKET_THREAD_UNAVAILABLE',
                'البنية الحالية لنظام الدعم لا تدعم بعد الردود المرئية للعميل.',
                [],
                422,
            );
        }
    }

    /**
     * @throws BusinessException
     */
    private function normalizeRequiredBody(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($normalized === '') {
            throw BusinessException::make(
                'ERR_TICKET_REPLY_REQUIRED',
                'يجب إدخال نص الرد قبل تحديث مسار الدعم.',
                [],
                422,
            );
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $ticketIds
     * @return Collection<int, array<string, mixed>>
     */
    private function customerVisibleItemsByTicketIds(array $ticketIds): Collection
    {
        $items = collect();

        if (Schema::hasTable('ticket_replies')) {
            $legacyReplies = TicketReply::query()
                ->withoutGlobalScopes()
                ->with('user')
                ->whereIn('support_ticket_id', $ticketIds)
                ->get()
                ->map(function (TicketReply $reply): array {
                    return [
                        'ticket_id' => (string) $reply->support_ticket_id,
                        'actor_label' => (bool) $reply->is_agent ? 'رد الدعم' : 'رد مقدم الطلب',
                        'actor_name' => $reply->user?->name ? (string) $reply->user->name : 'مستخدم غير معروف',
                        'body' => $this->safeText((string) ($reply->body ?? '')),
                        'created_at' => $reply->created_at,
                        'created_at_label' => $this->displayDateTime($reply->created_at) ?? 'غير متاح',
                        'is_support_reply' => (bool) $reply->is_agent,
                    ];
                });

            $items = $items->concat($legacyReplies);
        }

        if (Schema::hasTable('support_ticket_replies')) {
            $modernReplies = SupportTicketReply::query()
                ->withoutGlobalScopes()
                ->with('user')
                ->whereIn('ticket_id', $ticketIds)
                ->where('is_internal_note', false)
                ->get()
                ->map(function (SupportTicketReply $reply): array {
                    $isSupportReply = $this->isInternalUser($reply->user);

                    return [
                        'ticket_id' => (string) $reply->ticket_id,
                        'actor_label' => $isSupportReply ? 'رد الدعم' : 'رد مقدم الطلب',
                        'actor_name' => $reply->user?->name ? (string) $reply->user->name : 'مستخدم غير معروف',
                        'body' => $this->safeText((string) ($reply->body ?? '')),
                        'created_at' => $reply->created_at,
                        'created_at_label' => $this->displayDateTime($reply->created_at) ?? 'غير متاح',
                        'is_support_reply' => $isSupportReply,
                    ];
                });

            $items = $items->concat($modernReplies);
        }

        return $items;
    }

    private function ticketNumber(SupportTicket $ticket): string
    {
        $number = trim((string) ($ticket->getAttribute('ticket_number') ?: $ticket->getAttribute('reference_number')));

        return $number !== '' ? $number : (string) $ticket->id;
    }

    private function isInternalUser(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! Schema::hasColumn('users', 'user_type')) {
            return empty($user->account_id);
        }

        return strtolower(trim((string) ($user->user_type ?? ''))) === 'internal';
    }

    private function safeText(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value !== '' ? $value : 'غير متاح';
    }

    private function displayDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
    }
}
