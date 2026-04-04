<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalTicketAdminService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @throws BusinessException
     */
    public function createTicket(array $data, User $actor): SupportTicket
    {
        return DB::transaction(function () use ($data, $actor): SupportTicket {
            $account = $this->resolveAccount((string) $data['account_id']);
            $shipment = $this->resolveShipment($account, $data['shipment_id'] ?? null);
            $requester = $this->resolveRequester($account, $shipment);
            $ticketNumber = $this->nextTicketNumber();

            $ticket = SupportTicket::query()
                ->withoutGlobalScopes()
                ->create($this->buildTicketPayload($data, $account, $shipment, $requester, $ticketNumber));

            $this->auditService->info(
                (string) $account->id,
                (string) $actor->id,
                'support.ticket_created',
                AuditLog::CATEGORY_SYSTEM,
                'SupportTicket',
                (string) $ticket->id,
                null,
                [
                    'ticket_number' => $ticketNumber,
                    'subject' => (string) $data['subject'],
                    'category' => (string) $data['category'],
                    'priority' => (string) $data['priority'],
                    'status' => 'open',
                    'requester_id' => (string) $requester->id,
                    'shipment_id' => $shipment?->id ? (string) $shipment->id : null,
                ],
                [
                    'source' => 'internal_tickets_center',
                    'context' => $shipment instanceof Shipment ? 'shipment' : 'account',
                    'organization_account' => $account->isOrganization(),
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
    private function resolveAccount(string $accountId): Account
    {
        $account = Account::query()
            ->withoutGlobalScopes()
            ->with('organizationProfile')
            ->find($accountId);

        if (! $account instanceof Account) {
            throw BusinessException::make(
                'ERR_TICKET_ACCOUNT_NOT_FOUND',
                'The linked account for this internal ticket could not be found.',
                ['account_id' => $accountId],
                422,
            );
        }

        return $account;
    }

    /**
     * @throws BusinessException
     */
    private function resolveShipment(Account $account, mixed $shipmentId): ?Shipment
    {
        $normalizedId = trim((string) $shipmentId);
        if ($normalizedId === '') {
            return null;
        }

        $shipment = Shipment::query()
            ->withoutGlobalScopes()
            ->with('user')
            ->find($normalizedId);

        if (! $shipment instanceof Shipment) {
            throw BusinessException::make(
                'ERR_TICKET_SHIPMENT_NOT_FOUND',
                'The linked shipment for this internal ticket could not be found.',
                ['shipment_id' => $normalizedId],
                422,
            );
        }

        if ((string) $shipment->account_id !== (string) $account->id) {
            throw BusinessException::make(
                'ERR_TICKET_CONTEXT_MISMATCH',
                'The selected shipment does not belong to the selected account.',
                [
                    'account_id' => (string) $account->id,
                    'shipment_id' => (string) $shipment->id,
                ],
                422,
            );
        }

        return $shipment;
    }

    /**
     * @throws BusinessException
     */
    private function resolveRequester(Account $account, ?Shipment $shipment): User
    {
        if (
            $shipment instanceof Shipment
            && $shipment->user instanceof User
            && (string) $shipment->user->account_id === (string) $account->id
            && $this->isExternalUser($shipment->user)
        ) {
            return $shipment->user;
        }

        $externalUsers = User::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->when(
                Schema::hasColumn('users', 'user_type'),
                static fn ($query) => $query->where('user_type', 'external')
            )
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get();

        /** @var User|null $activeOwner */
        $activeOwner = $externalUsers->first(function (User $user): bool {
            return (bool) ($user->is_owner ?? false) && $this->isLoginCapable($user);
        });

        if ($activeOwner instanceof User) {
            return $activeOwner;
        }

        /** @var User|null $activeUser */
        $activeUser = $externalUsers->first(fn (User $user): bool => $this->isLoginCapable($user));
        if ($activeUser instanceof User) {
            return $activeUser;
        }

        /** @var User|null $fallbackUser */
        $fallbackUser = $externalUsers->first();
        if ($fallbackUser instanceof User) {
            return $fallbackUser;
        }

        throw BusinessException::make(
            'ERR_TICKET_REQUESTER_UNAVAILABLE',
            'No external requester could be resolved for this account.',
            ['account_id' => (string) $account->id],
            422,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildTicketPayload(
        array $data,
        Account $account,
        ?Shipment $shipment,
        User $requester,
        string $ticketNumber,
    ): array {
        $payload = [
            'account_id' => (string) $account->id,
            'user_id' => (string) $requester->id,
            'subject' => trim((string) $data['subject']),
            'body' => trim((string) $data['description']),
            'description' => trim((string) $data['description']),
            'reference_number' => $ticketNumber,
            'ticket_number' => $ticketNumber,
            'category' => trim((string) $data['category']),
            'priority' => trim((string) $data['priority']),
            'status' => 'open',
            'assigned_to' => null,
            'assigned_team' => 'support',
        ];

        if ($shipment instanceof Shipment && $this->supportsStringForeignKey('support_tickets', 'shipment_id')) {
            $payload['shipment_id'] = (string) $shipment->id;
        }

        if ($shipment instanceof Shipment && Schema::hasColumn('support_tickets', 'entity_type')) {
            $payload['entity_type'] = 'shipment';
        }

        if ($shipment instanceof Shipment && Schema::hasColumn('support_tickets', 'entity_id')) {
            $payload['entity_id'] = (string) $shipment->id;
        }

        return $this->filterExistingColumns('support_tickets', $payload);
    }

    private function nextTicketNumber(): string
    {
        do {
            $candidate = 'TKT-' . strtoupper(Str::random(8));
        } while ($this->ticketNumberExists($candidate));

        return $candidate;
    }

    private function ticketNumberExists(string $candidate): bool
    {
        $query = SupportTicket::query()->withoutGlobalScopes();

        if (Schema::hasColumn('support_tickets', 'reference_number') && Schema::hasColumn('support_tickets', 'ticket_number')) {
            return $query
                ->where('reference_number', $candidate)
                ->orWhere('ticket_number', $candidate)
                ->exists();
        }

        if (Schema::hasColumn('support_tickets', 'reference_number')) {
            return $query->where('reference_number', $candidate)->exists();
        }

        if (Schema::hasColumn('support_tickets', 'ticket_number')) {
            return $query->where('ticket_number', $candidate)->exists();
        }

        return false;
    }

    private function supportsStringForeignKey(string $table, string $column): bool
    {
        if (! Schema::hasColumn($table, $column)) {
            return false;
        }

        return ! in_array(
            strtolower((string) Schema::getColumnType($table, $column)),
            ['bigint', 'integer', 'int', 'smallint', 'mediumint', 'tinyint'],
            true,
        );
    }

    private function isExternalUser(User $user): bool
    {
        if (! Schema::hasColumn('users', 'user_type')) {
            return ! empty($user->account_id);
        }

        return strtolower(trim((string) ($user->user_type ?? ''))) === 'external';
    }

    private function isLoginCapable(User $user): bool
    {
        $status = strtolower(trim((string) ($user->status ?? 'active')));

        return ! in_array($status, ['disabled', 'suspended'], true);
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
}
