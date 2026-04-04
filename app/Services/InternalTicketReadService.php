<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalTicketReadService
{
    public const ASSIGNEE_FILTER_UNASSIGNED = '__unassigned__';

    /**
     * @var array<string, string>
     */
    private const CATEGORY_LABELS = [
        'shipping' => 'Shipping',
        'shipment' => 'Shipping',
        'billing' => 'Billing',
        'technical' => 'Technical',
        'account' => 'Account',
        'carrier' => 'Carrier',
        'general' => 'General',
    ];

    /**
     * @var array<string, string>
     */
    private const PRIORITY_LABELS = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    /**
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'waiting_customer' => 'Waiting on customer',
        'waiting_agent' => 'Waiting agent',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    /**
     * @param array{q: string, status: string, priority: string, category: string, account_id: string, shipment_scope: string, assignee_id: string} $filters
     */
    public function paginate(?User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $rows = $this->filteredRows($user, $filters);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * @param array{q: string, status: string, priority: string, category: string, account_id: string, shipment_scope: string, assignee_id: string} $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filteredRows(?User $user, array $filters): Collection
    {
        return $this->visibleRows($user)
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['q'] !== '') {
                    $haystack = Str::lower(implode(' ', array_filter([
                        (string) ($row['ticket_number'] ?? ''),
                        (string) ($row['subject'] ?? ''),
                        (string) data_get($row, 'account_summary.name', ''),
                        (string) data_get($row, 'account_summary.slug', ''),
                        (string) data_get($row, 'requester.name', ''),
                        (string) data_get($row, 'requester.email', ''),
                        (string) data_get($row, 'assignee.name', ''),
                        (string) data_get($row, 'assignee.email', ''),
                        (string) data_get($row, 'shipment_summary.reference', ''),
                    ])));

                    if (! str_contains($haystack, Str::lower($filters['q']))) {
                        return false;
                    }
                }

                if ($filters['status'] !== '' && (string) ($row['status_key'] ?? '') !== $filters['status']) {
                    return false;
                }

                if ($filters['priority'] !== '' && (string) ($row['priority_key'] ?? '') !== $filters['priority']) {
                    return false;
                }

                if ($filters['category'] !== '' && (string) ($row['category_key'] ?? '') !== $filters['category']) {
                    return false;
                }

                if ($filters['account_id'] !== '' && (string) data_get($row, 'account_summary.account.id', '') !== $filters['account_id']) {
                    return false;
                }

                if ($filters['shipment_scope'] === 'linked' && ! is_array($row['shipment_summary'] ?? null)) {
                    return false;
                }

                if ($filters['shipment_scope'] === 'unlinked' && is_array($row['shipment_summary'] ?? null)) {
                    return false;
                }

                if ($filters['assignee_id'] === self::ASSIGNEE_FILTER_UNASSIGNED && is_array($row['assignee'] ?? null)) {
                    return false;
                }

                if (
                    $filters['assignee_id'] !== ''
                    && $filters['assignee_id'] !== self::ASSIGNEE_FILTER_UNASSIGNED
                    && (string) data_get($row, 'assignee.id', '') !== $filters['assignee_id']
                ) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @return array{total: int, open: int, urgent: int, linked_shipments: int}
     */
    public function stats(?User $user): array
    {
        $rows = $this->visibleRows($user);

        return [
            'total' => $rows->count(),
            'open' => $rows->whereIn('status_key', ['open', 'in_progress', 'waiting_customer', 'waiting_agent'])->count(),
            'urgent' => $rows->where('priority_key', 'urgent')->count(),
            'linked_shipments' => $rows->filter(fn (array $row): bool => is_array($row['shipment_summary'] ?? null))->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findVisibleDetail(?User $user, string $ticketId): ?array
    {
        $ticket = $this->ticketQuery()
            ->whereKey($ticketId)
            ->first();

        if (! $ticket instanceof SupportTicket) {
            return null;
        }

        return $this->buildRows(collect([$ticket]))->first();
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return self::STATUS_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public function priorityOptions(): array
    {
        return self::PRIORITY_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public function categoryOptions(): array
    {
        return [
            'shipping' => 'Shipping',
            'billing' => 'Billing',
            'technical' => 'Technical',
            'account' => 'Account',
            'carrier' => 'Carrier',
            'general' => 'General',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function shipmentScopeOptions(): array
    {
        return [
            'linked' => 'Linked shipment',
            'unlinked' => 'No linked shipment',
        ];
    }

    /**
     * @return Collection<int, array{id: string, label: string}>
     */
    public function accountFilterOptions(?User $user): Collection
    {
        return $this->visibleRows($user)
            ->filter(static fn (array $row): bool => is_array($row['account_summary'] ?? null))
            ->map(function (array $row): array {
                $id = (string) data_get($row, 'account_summary.account.id', '');
                $name = (string) data_get($row, 'account_summary.name', 'Unknown account');
                $type = (string) data_get($row, 'account_summary.type_label', 'Unknown');
                $slug = (string) data_get($row, 'account_summary.slug', 'N/A');

                return [
                    'id' => $id,
                    'label' => sprintf('%s - %s - %s', $name, $type, $slug),
                ];
            })
            ->filter(static fn (array $option): bool => $option['id'] !== '')
            ->unique('id')
            ->sortBy('label')
            ->values();
    }

    /**
     * @return Collection<int, array{id: string, label: string}>
     */
    public function assigneeFilterOptions(?User $user): Collection
    {
        return $this->visibleRows($user)
            ->filter(static fn (array $row): bool => is_array($row['assignee'] ?? null))
            ->map(function (array $row): array {
                $id = (string) data_get($row, 'assignee.id', '');
                $name = (string) data_get($row, 'assignee.name', 'Unknown user');
                $email = (string) data_get($row, 'assignee.email', '');
                $team = (string) ($row['assigned_team'] ?? 'Assigned');

                return [
                    'id' => $id,
                    'label' => trim(sprintf('%s - %s - %s', $name, $email, $team)),
                ];
            })
            ->filter(static fn (array $option): bool => $option['id'] !== '')
            ->unique('id')
            ->sortBy('label')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function visibleRows(?User $user): Collection
    {
        return $this->allRows();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allRows(): Collection
    {
        return $this->buildRows($this->ticketQuery()->get());
    }

    /**
     * @param Collection<int, SupportTicket> $tickets
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRows(Collection $tickets): Collection
    {
        $shipments = $this->shipmentsForTickets($tickets);
        $activity = $this->activityByTicket($tickets);
        $internalNotes = $this->internalNotesByTicket($tickets);
        $workflowActivity = $this->workflowActivityByTicket($tickets);

        return $tickets
            ->map(function (SupportTicket $ticket) use ($shipments, $activity, $internalNotes, $workflowActivity): array {
                $ticketId = (string) $ticket->getKey();
                $shipment = $shipments->get($ticketId);
                $activitySummary = $activity->get($ticketId, $this->emptyActivitySummary($ticket));
                $noteSummary = $internalNotes->get($ticketId, $this->emptyInternalNotesSummary());
                $workflowSummary = $workflowActivity->get($ticketId, $this->emptyWorkflowActivitySummary($ticket));
                $account = $ticket->account;
                $requester = $ticket->user;
                $assignee = $ticket->assignee;

                return [
                    'route_key' => $ticketId,
                    'ticket_number' => $this->ticketNumber($ticket),
                    'subject' => $this->safeText((string) $ticket->subject),
                    'description' => $this->ticketBody($ticket),
                    'description_summary' => Str::limit($this->ticketBody($ticket), 220),
                    'category_key' => Str::lower(trim((string) $ticket->category)),
                    'category_label' => $this->categoryLabel((string) $ticket->category),
                    'status_key' => Str::lower(trim((string) $ticket->status)),
                    'status_label' => $this->statusLabel((string) $ticket->status),
                    'priority_key' => Str::lower(trim((string) $ticket->priority)),
                    'priority_label' => $this->priorityLabel((string) $ticket->priority),
                    'requester' => $requester instanceof User ? [
                        'name' => (string) $requester->name,
                        'email' => (string) $requester->email,
                    ] : null,
                    'assignee' => $assignee instanceof User ? [
                        'id' => (string) $assignee->id,
                        'name' => (string) $assignee->name,
                        'email' => (string) $assignee->email,
                    ] : null,
                    'assigned_team' => $this->assignedTeam($ticket),
                'account_summary' => $account instanceof Account ? [
                    'account' => $account,
                    'name' => (string) $account->name,
                    'slug' => (string) ($account->slug ?? 'N/A'),
                    'type_label' => $account->isOrganization() ? 'Organization' : 'Individual',
                    'organization_label' => $account->isOrganization()
                        ? $this->safeText((string) ($account->organizationProfile?->legal_name ?: $account->organizationProfile?->trade_name ?: ''))
                        : null,
                ] : null,
                    'shipment_summary' => $shipment,
                    'created_at_label' => $this->displayDateTime($ticket->created_at) ?? 'N/A',
                    'updated_at_label' => $this->displayDateTime($ticket->updated_at) ?? 'N/A',
                    'resolved_at_label' => $this->displayDateTime($ticket->getAttribute('resolved_at')) ?? 'N/A',
                    'recent_activity_summary' => $activitySummary['summary'],
                    'recent_activity_at' => $activitySummary['latest_at_label'],
                    'recent_activity' => $activitySummary['items'],
                    'replies_count' => $activitySummary['count'],
                    'internal_notes_summary' => $noteSummary['summary'],
                    'internal_notes_count' => $noteSummary['count'],
                    'internal_notes' => $noteSummary['items'],
                    'workflow_activity_summary' => $workflowSummary['summary'],
                    'workflow_activity_at' => $workflowSummary['latest_at_label'],
                    'workflow_activity' => $workflowSummary['items'],
                ];
            })
            ->sortByDesc('updated_at_label')
            ->values();
    }

    private function ticketQuery()
    {
        return SupportTicket::query()
            ->withoutGlobalScopes()
            ->with([
                'account.organizationProfile',
                'user',
                'assignee',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at');
    }

    /**
     * @param Collection<int, SupportTicket> $tickets
     * @return Collection<string, array<string, string|null>>
     */
    private function shipmentsForTickets(Collection $tickets): Collection
    {
        $shipmentIds = [];
        $shipmentRefs = [];

        foreach ($tickets as $ticket) {
            $shipmentId = $this->linkedShipmentId($ticket);

            if ($shipmentId !== null) {
                if (Str::isUuid($shipmentId)) {
                    $shipmentIds[] = $shipmentId;
                } else {
                    $shipmentRefs[] = $shipmentId;
                }
            }
        }

        $byId = collect();
        $byReference = collect();

        if ($shipmentIds !== []) {
            $byId = Shipment::query()
                ->withoutGlobalScopes()
                ->whereIn('id', array_values(array_unique($shipmentIds)))
                ->get()
                ->keyBy(static fn (Shipment $shipment): string => (string) $shipment->id);
        }

        if ($shipmentRefs !== []) {
            $byReference = Shipment::query()
                ->withoutGlobalScopes()
                ->whereIn('reference_number', array_values(array_unique($shipmentRefs)))
                ->get()
                ->keyBy(static fn (Shipment $shipment): string => (string) $shipment->reference_number);
        }

        return $tickets->mapWithKeys(function (SupportTicket $ticket) use ($byId, $byReference): array {
            $shipmentKey = $this->linkedShipmentId($ticket);

            if ($shipmentKey === null) {
                return [(string) $ticket->getKey() => null];
            }

            /** @var Shipment|null $shipment */
            $shipment = Str::isUuid($shipmentKey)
                ? $byId->get($shipmentKey)
                : $byReference->get($shipmentKey);

            if (! $shipment instanceof Shipment) {
                return [(string) $ticket->getKey() => null];
            }

            $reference = (string) ($shipment->reference_number ?: $shipment->id);
            $tracking = trim((string) ($shipment->tracking_number ?? ''));

            return [
                (string) $ticket->getKey() => [
                    'shipment' => $shipment,
                    'reference' => $reference,
                    'status_label' => $this->headline((string) $shipment->status),
                    'tracking_summary' => $tracking !== '' ? 'Tracking/AWB recorded' : 'No tracking summary recorded',
                ],
            ];
        });
    }

    /**
     * @param Collection<int, SupportTicket> $tickets
     * @return Collection<string, array{summary: string, latest_at_label: string, count: int, items: Collection<int, array<string, string>>}>
     */
    private function activityByTicket(Collection $tickets): Collection
    {
        $ticketIds = $tickets->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        if ($ticketIds === []) {
            return collect();
        }

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
                        'actor_label' => (bool) $reply->is_agent ? 'Support reply' : 'Requester reply',
                        'actor_name' => $reply->user?->name ? (string) $reply->user->name : 'Unknown user',
                        'body' => $this->safeText((string) ($reply->body ?? '')),
                        'created_at' => $reply->created_at,
                        'created_at_label' => $this->displayDateTime($reply->created_at) ?? 'N/A',
                    ];
                });

            $items = $items->concat($legacyReplies);
        }

        if (Schema::hasTable('support_ticket_replies')) {
            $modernReplies = SupportTicketReply::query()
                ->withoutGlobalScopes()
                ->with('user')
                ->whereIn('ticket_id', $ticketIds)
                ->get()
                ->filter(static fn (SupportTicketReply $reply): bool => ! (bool) $reply->is_internal_note)
                ->map(function (SupportTicketReply $reply): array {
                    $actorLabel = $reply->user?->user_type === 'internal'
                        ? 'Internal reply'
                        : 'Requester reply';

                    return [
                        'ticket_id' => (string) $reply->ticket_id,
                        'actor_label' => $actorLabel,
                        'actor_name' => $reply->user?->name ? (string) $reply->user->name : 'Unknown user',
                        'body' => $this->safeText((string) ($reply->body ?? '')),
                        'created_at' => $reply->created_at,
                        'created_at_label' => $this->displayDateTime($reply->created_at) ?? 'N/A',
                    ];
                });

            $items = $items->concat($modernReplies);
        }

        return $items
            ->groupBy('ticket_id')
            ->map(function (Collection $rows): array {
                $latest = $rows->sortByDesc('created_at')->values()->first();
                $thread = $rows->sortBy('created_at')->values();

                return [
                    'summary' => is_array($latest)
                        ? sprintf('%s - %s', $latest['actor_label'], $latest['created_at_label'])
                        : 'No reply activity recorded yet',
                    'latest_at_label' => is_array($latest) ? $latest['created_at_label'] : 'N/A',
                    'count' => $thread->count(),
                    'items' => $thread,
                ];
            });
    }

    /**
     * @return array{summary: string, latest_at_label: string, count: int, items: Collection<int, array<string, string>>}
     */
    private function emptyActivitySummary(SupportTicket $ticket): array
    {
        return [
            'summary' => 'No reply activity recorded yet',
            'latest_at_label' => $this->displayDateTime($ticket->updated_at ?: $ticket->created_at) ?? 'N/A',
            'count' => 0,
            'items' => collect([
                [
                    'actor_label' => 'Ticket created',
                    'actor_name' => $ticket->user?->name ? (string) $ticket->user->name : 'Unknown user',
                    'body' => $this->ticketBody($ticket),
                    'created_at_label' => $this->displayDateTime($ticket->created_at) ?? 'N/A',
                ],
            ]),
        ];
    }

    /**
     * @param Collection<int, SupportTicket> $tickets
     * @return Collection<string, array{summary: string, count: int, items: Collection<int, array<string, string>>}>
     */
    private function internalNotesByTicket(Collection $tickets): Collection
    {
        $ticketIds = $tickets->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        if ($ticketIds === [] || ! Schema::hasTable('support_ticket_replies')) {
            return collect();
        }

        return SupportTicketReply::query()
            ->withoutGlobalScopes()
            ->with('user')
            ->whereIn('ticket_id', $ticketIds)
            ->where('is_internal_note', true)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(static fn (SupportTicketReply $reply): string => (string) $reply->ticket_id)
            ->map(function (Collection $rows): array {
                $items = $rows
                    ->map(function (SupportTicketReply $reply): array {
                        return [
                            'actor_label' => 'Internal note',
                            'actor_name' => $reply->user?->name ? (string) $reply->user->name : 'Unknown user',
                            'body' => $this->safeText((string) ($reply->body ?? '')),
                            'created_at_label' => $this->displayDateTime($reply->created_at) ?? 'N/A',
                        ];
                    })
                    ->values();

                return [
                    'summary' => $items->count() > 0
                        ? sprintf('%d internal note%s', $items->count(), $items->count() === 1 ? '' : 's')
                        : 'No internal notes recorded yet',
                    'count' => $items->count(),
                    'items' => $items,
                ];
            });
    }

    /**
     * @param Collection<int, SupportTicket> $tickets
     * @return Collection<string, array{summary: string, latest_at_label: string, items: Collection<int, array<string, string>>}>
     */
    private function workflowActivityByTicket(Collection $tickets): Collection
    {
        $ticketIds = $tickets->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        if ($ticketIds === [] || ! Schema::hasTable('audit_logs')) {
            return collect();
        }

        $query = AuditLog::query()
            ->withoutGlobalScopes()
            ->with('performer:id,name,email')
            ->orderByDesc('created_at');

        if (Schema::hasColumn('audit_logs', 'entity_type')) {
            $query->where('entity_type', 'SupportTicket');
        } elseif (Schema::hasColumn('audit_logs', 'auditable_type')) {
            $query->where('auditable_type', 'SupportTicket');
        } else {
            return collect();
        }

        if (Schema::hasColumn('audit_logs', 'entity_id')) {
            $query->whereIn('entity_id', $ticketIds);
        } else {
            return collect();
        }

        $actionColumn = Schema::hasColumn('audit_logs', 'action') ? 'action' : (Schema::hasColumn('audit_logs', 'event') ? 'event' : null);

        if ($actionColumn === null) {
            return collect();
        }

        $query->whereIn($actionColumn, [
            'support.ticket_created',
            'support.ticket_status_changed',
            'support.ticket_triaged',
            'support.ticket_assigned',
            'support.ticket_note_added',
            'support.ticket_resolved',
        ]);

        return $query->get()
            ->groupBy(static fn (AuditLog $log): string => (string) $log->entity_id)
            ->map(function (Collection $rows): array {
                $items = $rows
                    ->map(fn (AuditLog $log): array => $this->workflowActivityItem($log))
                    ->values();

                $latest = $items->first();

                return [
                    'summary' => is_array($latest)
                        ? sprintf('%s - %s', $latest['headline'], $latest['created_at_label'])
                        : 'No workflow activity recorded yet',
                    'latest_at_label' => is_array($latest) ? $latest['created_at_label'] : 'N/A',
                    'items' => $items,
                ];
            });
    }

    /**
     * @return array<string, string>
     */
    private function workflowActivityItem(AuditLog $log): array
    {
        $action = (string) $log->action;
        $oldValues = is_array($log->old_values) ? $log->old_values : [];
        $newValues = is_array($log->new_values) ? $log->new_values : [];
        $metadata = is_array($log->metadata) ? $log->metadata : [];

        return match ($action) {
            'support.ticket_status_changed' => [
                'headline' => 'Status changed',
                'actor_name' => $log->performer?->name ? (string) $log->performer->name : 'Unknown user',
                'detail' => $this->statusChangeSummary($oldValues, $newValues, $metadata),
                'created_at_label' => $this->displayDateTime($log->created_at) ?? 'N/A',
            ],
            'support.ticket_triaged' => [
                'headline' => 'Triage updated',
                'actor_name' => $log->performer?->name ? (string) $log->performer->name : 'Unknown user',
                'detail' => $this->triageChangeSummary($oldValues, $newValues, $metadata),
                'created_at_label' => $this->displayDateTime($log->created_at) ?? 'N/A',
            ],
            'support.ticket_assigned' => [
                'headline' => 'Assignment updated',
                'actor_name' => $log->performer?->name ? (string) $log->performer->name : 'Unknown user',
                'detail' => $this->assignmentChangeSummary($oldValues, $newValues, $metadata),
                'created_at_label' => $this->displayDateTime($log->created_at) ?? 'N/A',
            ],
            'support.ticket_note_added' => [
                'headline' => 'Internal note added',
                'actor_name' => $log->performer?->name ? (string) $log->performer->name : 'Unknown user',
                'detail' => sprintf(
                    'Internal note recorded%s.',
                    isset($newValues['note_length']) ? ' (' . (int) $newValues['note_length'] . ' characters)' : ''
                ),
                'created_at_label' => $this->displayDateTime($log->created_at) ?? 'N/A',
            ],
            'support.ticket_resolved' => [
                'headline' => 'Ticket resolved',
                'actor_name' => $log->performer?->name ? (string) $log->performer->name : 'Unknown user',
                'detail' => $this->safeText((string) ($metadata['resolution_notes'] ?? 'Ticket was resolved.')),
                'created_at_label' => $this->displayDateTime($log->created_at) ?? 'N/A',
            ],
            default => [
                'headline' => 'Ticket created',
                'actor_name' => $log->performer?->name ? (string) $log->performer->name : 'Unknown user',
                'detail' => $this->safeText((string) ($newValues['subject'] ?? 'Ticket record created')),
                'created_at_label' => $this->displayDateTime($log->created_at) ?? 'N/A',
            ],
        };
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $metadata
     */
    private function statusChangeSummary(array $oldValues, array $newValues, array $metadata): string
    {
        $before = $this->statusLabel((string) ($oldValues['status'] ?? ''));
        $after = $this->statusLabel((string) ($newValues['status'] ?? ''));

        $summary = sprintf('Change summary: Status: %s -> %s', $before, $after);

        return ! empty($metadata['note_recorded']) ? $summary . ' - Internal note recorded' : $summary;
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $metadata
     */
    private function assignmentChangeSummary(array $oldValues, array $newValues, array $metadata): string
    {
        $before = $this->safeText((string) ($oldValues['assigned_name'] ?? 'Unassigned'));
        $after = $this->safeText((string) ($newValues['assigned_name'] ?? 'Unassigned'));

        $summary = sprintf('Change summary: Assignee: %s -> %s', $before, $after);

        return ! empty($metadata['note_recorded']) ? $summary . ' - Internal note recorded' : $summary;
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $metadata
     */
    private function triageChangeSummary(array $oldValues, array $newValues, array $metadata): string
    {
        $changes = [];

        if (array_key_exists('priority', $newValues)) {
            $changes[] = sprintf(
                'Priority: %s -> %s',
                $this->priorityLabel((string) ($oldValues['priority'] ?? '')),
                $this->priorityLabel((string) ($newValues['priority'] ?? ''))
            );
        }

        if (array_key_exists('category', $newValues)) {
            $changes[] = sprintf(
                'Category: %s -> %s',
                $this->categoryLabel((string) ($oldValues['category'] ?? '')),
                $this->categoryLabel((string) ($newValues['category'] ?? ''))
            );
        }

        $summary = 'Change summary: ' . implode('; ', $changes);

        return ! empty($metadata['note_recorded']) ? $summary . ' - Internal note recorded' : $summary;
    }

    /**
     * @return array{summary: string, count: int, items: Collection<int, array<string, string>>}
     */
    private function emptyInternalNotesSummary(): array
    {
        return [
            'summary' => 'No internal notes recorded yet',
            'count' => 0,
            'items' => collect(),
        ];
    }

    /**
     * @return array{summary: string, latest_at_label: string, items: Collection<int, array<string, string>>}
     */
    private function emptyWorkflowActivitySummary(SupportTicket $ticket): array
    {
        return [
            'summary' => 'No workflow activity recorded yet',
            'latest_at_label' => $this->displayDateTime($ticket->updated_at ?: $ticket->created_at) ?? 'N/A',
            'items' => collect(),
        ];
    }

    private function ticketNumber(SupportTicket $ticket): string
    {
        $ticketNumber = trim((string) $ticket->getAttribute('ticket_number'));

        if ($ticketNumber !== '') {
            return $ticketNumber;
        }

        $legacyReference = trim((string) $ticket->getAttribute('reference_number'));

        return $legacyReference !== '' ? $legacyReference : (string) $ticket->getKey();
    }

    private function ticketBody(SupportTicket $ticket): string
    {
        foreach (['description', 'body'] as $column) {
            $value = trim((string) $ticket->getAttribute($column));

            if ($value !== '') {
                return $this->safeText($value);
            }
        }

        return 'No request summary is currently available.';
    }

    private function linkedShipmentId(SupportTicket $ticket): ?string
    {
        $shipmentId = trim((string) $ticket->getAttribute('shipment_id'));

        if ($shipmentId !== '') {
            return $shipmentId;
        }

        $entityType = Str::lower(trim((string) $ticket->getAttribute('entity_type')));
        $entityId = trim((string) $ticket->getAttribute('entity_id'));

        if ($entityType === 'shipment' && $entityId !== '') {
            return $entityId;
        }

        return null;
    }

    private function assignedTeam(SupportTicket $ticket): string
    {
        $team = trim((string) $ticket->getAttribute('assigned_team'));

        if ($team !== '') {
            return $this->headline($team);
        }

        if ($ticket->assignee instanceof User) {
            $role = $ticket->assignee->internalRoleNames()[0] ?? null;

            if (is_string($role) && trim($role) !== '') {
                return $this->headline($role);
            }

            return 'Assigned';
        }

        return 'Unassigned';
    }

    private function categoryLabel(string $value): string
    {
        $key = Str::lower(trim($value));

        return self::CATEGORY_LABELS[$key] ?? $this->headline($value);
    }

    private function priorityLabel(string $value): string
    {
        $key = Str::lower(trim($value));

        return self::PRIORITY_LABELS[$key] ?? $this->headline($value);
    }

    private function statusLabel(string $value): string
    {
        $key = Str::lower(trim($value));

        return self::STATUS_LABELS[$key] ?? $this->headline($value);
    }

    private function safeText(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value !== '' ? $value : 'N/A';
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

    private function headline(string $value): string
    {
        $value = trim($value);

        return $value === '' ? 'Unknown' : Str::headline($value);
    }
}
