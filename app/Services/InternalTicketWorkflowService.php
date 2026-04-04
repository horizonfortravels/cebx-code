<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalTicketWorkflowService
{
    /**
     * @var array<string, string>
     */
    private const MANUAL_STATUS_LABELS = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'waiting_customer' => 'Waiting on customer',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    /**
     * @var array<string, string>
     */
    private const MANUAL_PRIORITY_LABELS = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    /**
     * @var array<string, string>
     */
    private const MANUAL_CATEGORY_LABELS = [
        'shipping' => 'Shipping',
        'billing' => 'Billing',
        'technical' => 'Technical',
        'account' => 'Account',
        'carrier' => 'Carrier',
        'general' => 'General',
    ];

    /**
     * @var array<int, string>
     */
    private const ASSIGNABLE_ROLES = [
        InternalControlPlane::ROLE_SUPER_ADMIN,
        InternalControlPlane::ROLE_SUPPORT,
    ];

    public function __construct(
        private readonly AuditService $auditService,
        private readonly InternalControlPlane $controlPlane,
    ) {}

    /**
     * @return array<string, string>
     */
    public function manualStatusOptions(): array
    {
        return self::MANUAL_STATUS_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public function priorityOptions(): array
    {
        return self::MANUAL_PRIORITY_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public function categoryOptions(): array
    {
        return self::MANUAL_CATEGORY_LABELS;
    }

    /**
     * @return Collection<int, array{id: string, label: string, role_key: string, role_label: string}>
     */
    public function assignableUsers(): Collection
    {
        return User::query()
            ->withoutGlobalScopes()
            ->with('internalRoles')
            ->when(
                Schema::hasColumn('users', 'user_type'),
                static fn ($query) => $query->where('user_type', 'internal')
            )
            ->orderBy('name')
            ->get()
            ->filter(function (User $user): bool {
                $roleKey = $this->controlPlane->primaryCanonicalRole($user);

                return in_array($roleKey, self::ASSIGNABLE_ROLES, true)
                    && $this->isLoginCapable($user)
                    && $user->hasPermission('tickets.manage')
                    && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_TICKETS_ACTIONS);
            })
            ->map(function (User $user): array {
                $roleKey = (string) $this->controlPlane->primaryCanonicalRole($user);
                $roleProfile = $this->controlPlane->roleProfileForCanonicalRole($roleKey);

                return [
                    'id' => (string) $user->id,
                    'label' => sprintf('%s - %s - %s', (string) $user->name, (string) $user->email, (string) $roleProfile['label']),
                    'role_key' => $roleKey,
                    'role_label' => (string) $roleProfile['label'],
                ];
            })
            ->values();
    }

    /**
     * @throws BusinessException
     */
    public function changeStatus(string $ticketId, string $status, ?string $note, User $actor): SupportTicket
    {
        $ticket = $this->resolveTicket($ticketId);
        $normalizedStatus = strtolower(trim($status));

        if (! array_key_exists($normalizedStatus, self::MANUAL_STATUS_LABELS)) {
            throw BusinessException::make(
                'ERR_TICKET_STATUS_INVALID',
                'The selected ticket status is not available for the internal helpdesk workflow.',
                ['status' => $status],
                422,
            );
        }

        $noteSummary = $this->normalizeOptionalSummary($note);
        $oldStatus = strtolower(trim((string) $ticket->status));

        if ($oldStatus === $normalizedStatus) {
            throw BusinessException::make(
                'ERR_TICKET_STATUS_UNCHANGED',
                'The ticket is already in that workflow state.',
                ['status' => $normalizedStatus],
                422,
            );
        }

        if (in_array($normalizedStatus, ['waiting_customer', 'resolved', 'closed'], true) && $noteSummary === null) {
            throw BusinessException::make(
                'ERR_TICKET_STATUS_REASON_REQUIRED',
                'A short internal reason is required for this workflow transition.',
                ['status' => $normalizedStatus],
                422,
            );
        }

        return DB::transaction(function () use ($ticket, $actor, $normalizedStatus, $noteSummary, $oldStatus): SupportTicket {
            $updates = ['status' => $normalizedStatus];

            if (Schema::hasColumn('support_tickets', 'resolved_at')) {
                if ($normalizedStatus === 'resolved') {
                    $updates['resolved_at'] = $ticket->getAttribute('resolved_at') ?: now();
                } else {
                    $updates['resolved_at'] = null;
                }
            }

            if (Schema::hasColumn('support_tickets', 'closed_at')) {
                $updates['closed_at'] = $normalizedStatus === 'closed' ? now() : null;
            }

            $ticket->forceFill($this->filterExistingColumns('support_tickets', $updates))->save();
            $ticket->touch();

            $noteReply = $noteSummary !== null
                ? $this->createInternalNoteRecord(
                    $ticket,
                    $actor,
                    sprintf(
                        'Workflow update: %s -> %s. %s',
                        $this->statusLabel($oldStatus),
                        $this->statusLabel($normalizedStatus),
                        $noteSummary,
                    )
                )
                : null;

            $this->auditService->info(
                (string) $ticket->account_id,
                (string) $actor->id,
                'support.ticket_status_changed',
                AuditLog::CATEGORY_SYSTEM,
                'SupportTicket',
                (string) $ticket->id,
                [
                    'status' => $oldStatus,
                ],
                [
                    'status' => $normalizedStatus,
                ],
                [
                    'surface' => 'internal_tickets_workflow',
                    'ticket_number' => $this->ticketNumber($ticket),
                    'status_label_before' => $this->statusLabel($oldStatus),
                    'status_label_after' => $this->statusLabel($normalizedStatus),
                    'note_recorded' => $noteReply instanceof SupportTicketReply,
                    'note_id' => $noteReply?->id ? (string) $noteReply->id : null,
                ],
            );

            return $ticket->fresh([
                'account.organizationProfile',
                'user',
                'assignee',
            ]);
        });
    }

    /**
     * @throws BusinessException
     */
    public function assign(string $ticketId, ?string $assigneeId, ?string $note, User $actor): SupportTicket
    {
        $ticket = $this->resolveTicket($ticketId);
        $normalizedAssigneeId = trim((string) $assigneeId);
        $assignee = $normalizedAssigneeId === '' ? null : $this->resolveAssignableUser($normalizedAssigneeId);
        $noteSummary = $this->normalizeOptionalSummary($note);

        $oldAssignee = $ticket->assignee;
        $oldAssignedTo = trim((string) $ticket->assigned_to);
        $oldAssignedTeam = trim((string) $ticket->getAttribute('assigned_team'));
        $newAssignedTo = $assignee instanceof User ? (string) $assignee->id : null;
        $newAssignedTeam = $assignee instanceof User
            ? (string) $this->controlPlane->primaryCanonicalRole($assignee)
            : null;

        if ($oldAssignedTo === ($newAssignedTo ?? '') && $oldAssignedTeam === ($newAssignedTeam ?? '')) {
            throw BusinessException::make(
                'ERR_TICKET_ASSIGNMENT_UNCHANGED',
                'The ticket assignment is already set to that value.',
                ['assigned_to' => $newAssignedTo],
                422,
            );
        }

        return DB::transaction(function () use ($ticket, $actor, $assignee, $oldAssignee, $oldAssignedTeam, $newAssignedTo, $newAssignedTeam, $noteSummary): SupportTicket {
            $ticket->forceFill($this->filterExistingColumns('support_tickets', [
                'assigned_to' => $newAssignedTo,
                'assigned_team' => $newAssignedTeam,
            ]))->save();
            $ticket->touch();

            $noteReply = $noteSummary !== null
                ? $this->createInternalNoteRecord(
                    $ticket,
                    $actor,
                    sprintf(
                        'Assignment update: %s -> %s. %s',
                        $oldAssignee?->name ? (string) $oldAssignee->name : 'Unassigned',
                        $assignee?->name ? (string) $assignee->name : 'Unassigned',
                        $noteSummary,
                    )
                )
                : null;

            $this->auditService->info(
                (string) $ticket->account_id,
                (string) $actor->id,
                'support.ticket_assigned',
                AuditLog::CATEGORY_SYSTEM,
                'SupportTicket',
                (string) $ticket->id,
                [
                    'assigned_to' => $oldAssignee?->id ? (string) $oldAssignee->id : null,
                    'assigned_name' => $oldAssignee?->name ? (string) $oldAssignee->name : null,
                    'assigned_team' => $oldAssignedTeam !== '' ? $oldAssignedTeam : null,
                ],
                [
                    'assigned_to' => $newAssignedTo,
                    'assigned_name' => $assignee?->name ? (string) $assignee->name : null,
                    'assigned_team' => $newAssignedTeam,
                ],
                [
                    'surface' => 'internal_tickets_workflow',
                    'ticket_number' => $this->ticketNumber($ticket),
                    'note_recorded' => $noteReply instanceof SupportTicketReply,
                    'note_id' => $noteReply?->id ? (string) $noteReply->id : null,
                ],
            );

            return $ticket->fresh([
                'account.organizationProfile',
                'user',
                'assignee',
            ]);
        });
    }

    /**
     * @throws BusinessException
     */
    public function changeTriage(string $ticketId, string $priority, string $category, ?string $note, User $actor): SupportTicket
    {
        $ticket = $this->resolveTicket($ticketId);
        $normalizedPriority = strtolower(trim($priority));
        $normalizedCategory = strtolower(trim($category));

        if (! array_key_exists($normalizedPriority, self::MANUAL_PRIORITY_LABELS)) {
            throw BusinessException::make(
                'ERR_TICKET_PRIORITY_INVALID',
                'The selected ticket priority is not available for internal triage.',
                ['priority' => $priority],
                422,
            );
        }

        if (! array_key_exists($normalizedCategory, self::MANUAL_CATEGORY_LABELS)) {
            throw BusinessException::make(
                'ERR_TICKET_CATEGORY_INVALID',
                'The selected ticket category is not available for internal triage.',
                ['category' => $category],
                422,
            );
        }

        $oldPriority = strtolower(trim((string) $ticket->priority));
        $oldCategory = strtolower(trim((string) $ticket->category));
        $noteSummary = $this->normalizeOptionalSummary($note);

        if ($oldPriority === $normalizedPriority && $oldCategory === $normalizedCategory) {
            throw BusinessException::make(
                'ERR_TICKET_TRIAGE_UNCHANGED',
                'The ticket triage details are already set to those values.',
                [
                    'priority' => $normalizedPriority,
                    'category' => $normalizedCategory,
                ],
                422,
            );
        }

        return DB::transaction(function () use (
            $ticket,
            $actor,
            $normalizedPriority,
            $normalizedCategory,
            $oldPriority,
            $oldCategory,
            $noteSummary
        ): SupportTicket {
            $ticket->forceFill($this->filterExistingColumns('support_tickets', [
                'priority' => $normalizedPriority,
                'category' => $normalizedCategory,
            ]))->save();
            $ticket->touch();

            $changes = [];

            if ($oldPriority !== $normalizedPriority) {
                $changes[] = sprintf(
                    'Priority %s -> %s',
                    $this->priorityLabel($oldPriority),
                    $this->priorityLabel($normalizedPriority),
                );
            }

            if ($oldCategory !== $normalizedCategory) {
                $changes[] = sprintf(
                    'Category %s -> %s',
                    $this->categoryLabel($oldCategory),
                    $this->categoryLabel($normalizedCategory),
                );
            }

            $noteReply = $noteSummary !== null
                ? $this->createInternalNoteRecord(
                    $ticket,
                    $actor,
                    sprintf(
                        'Triage update: %s. %s',
                        implode('; ', $changes),
                        $noteSummary,
                    )
                )
                : null;

            $this->auditService->info(
                (string) $ticket->account_id,
                (string) $actor->id,
                'support.ticket_triaged',
                AuditLog::CATEGORY_SYSTEM,
                'SupportTicket',
                (string) $ticket->id,
                [
                    'priority' => $oldPriority,
                    'category' => $oldCategory,
                ],
                [
                    'priority' => $normalizedPriority,
                    'category' => $normalizedCategory,
                ],
                [
                    'surface' => 'internal_tickets_triage',
                    'ticket_number' => $this->ticketNumber($ticket),
                    'priority_label_before' => $this->priorityLabel($oldPriority),
                    'priority_label_after' => $this->priorityLabel($normalizedPriority),
                    'category_label_before' => $this->categoryLabel($oldCategory),
                    'category_label_after' => $this->categoryLabel($normalizedCategory),
                    'note_recorded' => $noteReply instanceof SupportTicketReply,
                    'note_id' => $noteReply?->id ? (string) $noteReply->id : null,
                ],
            );

            return $ticket->fresh([
                'account.organizationProfile',
                'user',
                'assignee',
            ]);
        });
    }

    /**
     * @throws BusinessException
     */
    public function addInternalNote(string $ticketId, string $body, User $actor): SupportTicketReply
    {
        if (! Schema::hasTable('support_ticket_replies')) {
            throw BusinessException::make(
                'ERR_TICKET_NOTES_UNAVAILABLE',
                'Internal ticket notes are not available in the current helpdesk schema.',
                [],
                422,
            );
        }

        $ticket = $this->resolveTicket($ticketId);
        $body = $this->normalizeRequiredBody($body);

        return DB::transaction(function () use ($ticket, $body, $actor): SupportTicketReply {
            $reply = $this->createInternalNoteRecord($ticket, $actor, $body);

            $ticket->touch();

            $this->auditService->info(
                (string) $ticket->account_id,
                (string) $actor->id,
                'support.ticket_note_added',
                AuditLog::CATEGORY_SYSTEM,
                'SupportTicket',
                (string) $ticket->id,
                null,
                [
                    'note_id' => (string) $reply->id,
                    'note_length' => mb_strlen($body),
                    'is_internal_note' => true,
                ],
                [
                    'surface' => 'internal_tickets_workflow',
                    'ticket_number' => $this->ticketNumber($ticket),
                ],
            );

            return $reply->fresh('user');
        });
    }

    /**
     * @throws BusinessException
     */
    private function resolveTicket(string $ticketId): SupportTicket
    {
        $ticket = SupportTicket::query()
            ->withoutGlobalScopes()
            ->with([
                'account.organizationProfile',
                'user',
                'assignee',
            ])
            ->find($ticketId);

        if (! $ticket instanceof SupportTicket) {
            throw BusinessException::make(
                'ERR_TICKET_NOT_FOUND',
                'The selected internal ticket could not be found.',
                ['ticket_id' => $ticketId],
                404,
            );
        }

        return $ticket;
    }

    /**
     * @throws BusinessException
     */
    private function resolveAssignableUser(string $userId): User
    {
        $user = User::query()
            ->withoutGlobalScopes()
            ->with('internalRoles')
            ->find($userId);

        if (! $user instanceof User) {
            throw BusinessException::make(
                'ERR_TICKET_ASSIGNEE_NOT_FOUND',
                'The selected assignee could not be found.',
                ['user_id' => $userId],
                422,
            );
        }

        $roleKey = $this->controlPlane->primaryCanonicalRole($user);

        if (
            ! in_array($roleKey, self::ASSIGNABLE_ROLES, true)
            || ! $this->isLoginCapable($user)
            || ! $user->hasPermission('tickets.manage')
            || ! $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_TICKETS_ACTIONS)
        ) {
            throw BusinessException::make(
                'ERR_TICKET_ASSIGNEE_INVALID',
                'Tickets can only be assigned to active internal support or super-admin users.',
                [
                    'user_id' => $userId,
                    'role' => $roleKey,
                ],
                422,
            );
        }

        return $user;
    }

    private function normalizeOptionalSummary(?string $value): ?string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $normalized !== '' ? Str::limit($normalized, 500, '') : null;
    }

    /**
     * @throws BusinessException
     */
    private function normalizeRequiredBody(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($normalized === '') {
            throw BusinessException::make(
                'ERR_TICKET_NOTE_REQUIRED',
                'An internal note is required before the helpdesk thread can be updated.',
                [],
                422,
            );
        }

        return $normalized;
    }

    /**
     * @throws BusinessException
     */
    private function createInternalNoteRecord(SupportTicket $ticket, User $actor, string $body): SupportTicketReply
    {
        if (! Schema::hasTable('support_ticket_replies')) {
            throw BusinessException::make(
                'ERR_TICKET_NOTES_UNAVAILABLE',
                'Internal ticket notes are not available in the current helpdesk schema.',
                [],
                422,
            );
        }

        return SupportTicketReply::query()
            ->withoutGlobalScopes()
            ->create([
                'ticket_id' => (string) $ticket->id,
                'user_id' => (string) $actor->id,
                'body' => $body,
                'is_internal_note' => true,
                'attachments' => null,
            ]);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function filterExistingColumns(string $table, array $values): array
    {
        $filtered = [];

        foreach ($values as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    private function ticketNumber(SupportTicket $ticket): string
    {
        $number = trim((string) ($ticket->getAttribute('ticket_number') ?: $ticket->getAttribute('reference_number')));

        return $number !== '' ? $number : (string) $ticket->id;
    }

    private function statusLabel(string $status): string
    {
        $normalized = strtolower(trim($status));

        return self::MANUAL_STATUS_LABELS[$normalized] ?? Str::headline($normalized);
    }

    private function priorityLabel(string $priority): string
    {
        $normalized = strtolower(trim($priority));

        return self::MANUAL_PRIORITY_LABELS[$normalized] ?? Str::headline($normalized);
    }

    private function categoryLabel(string $category): string
    {
        $normalized = strtolower(trim($category));

        return self::MANUAL_CATEGORY_LABELS[$normalized] ?? Str::headline($normalized);
    }

    private function isLoginCapable(User $user): bool
    {
        $status = strtolower(trim((string) ($user->status ?? 'active')));

        return ! in_array($status, ['disabled', 'suspended'], true);
    }
}
