<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SupportTicketConversationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupportWebController extends WebController
{
    public function __construct(
        private readonly SupportTicketConversationService $conversationService,
        private readonly AuditService $auditService,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', SupportTicket::class);

        $query = $this->visibleTicketQuery()
            ->with('user');

        $tickets = $query->latest()->paginate(15);

        $statsQ = fn () => (clone $this->visibleTicketQuery());
        $openCount = $statsQ()->where('status', 'open')->count();
        $resolvedCount = $statsQ()->where('status', 'resolved')->count();

        return view('pages.support.index', compact('tickets', 'openCount', 'resolvedCount'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', SupportTicket::class);

        $payload = $request->validate([
            'subject' => ['required', 'string', 'max:300'],
            'category' => ['nullable', 'string', 'in:shipment,shipping,billing,technical,account,carrier,general'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'body' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $body = $this->normalizeRequiredBody(
            (string) ($payload['body'] ?? $payload['message'] ?? $payload['description'] ?? '')
        );
        $ticketNumber = $this->nextTicketNumber();
        $user = $this->currentUser();

        $ticket = SupportTicket::query()->create($this->filterExistingColumns('support_tickets', [
            'account_id' => $this->currentAccountId(),
            'user_id' => (string) $user->id,
            'subject' => trim((string) $payload['subject']),
            'body' => $body,
            'description' => $body,
            'reference_number' => $ticketNumber,
            'ticket_number' => $ticketNumber,
            'category' => strtolower(trim((string) ($payload['category'] ?? 'general'))),
            'priority' => strtolower(trim((string) ($payload['priority'] ?? 'medium'))),
            'status' => 'open',
        ]));

        $this->auditService->info(
            (string) $ticket->account_id,
            (string) $user->id,
            'support.ticket_created',
            AuditLog::CATEGORY_SYSTEM,
            'SupportTicket',
            (string) $ticket->id,
            null,
            [
                'ticket_number' => $this->ticketNumber($ticket),
                'subject' => trim((string) $payload['subject']),
                'category' => strtolower(trim((string) ($payload['category'] ?? 'general'))),
                'priority' => strtolower(trim((string) ($payload['priority'] ?? 'medium'))),
                'status' => 'open',
            ],
            [
                'surface' => 'external_support_portal',
            ],
        );

        return redirect()
            ->route('support.show', $ticket)
            ->with('success', 'Your help request was submitted successfully.');
    }

    public function show(string $ticket)
    {
        $ticketModel = $this->findTicketForCurrentAccount($ticket);
        $this->authorize('view', $ticketModel);

        $ticketModel->load([
            'user',
            'account.organizationProfile',
            'assignee',
            'shipment',
        ]);

        return view('pages.support.show', [
            'ticket' => $ticketModel,
            'threadItems' => $this->conversationService->customerVisibleThreadForTicket($ticketModel),
            'ticketBody' => $this->ticketBody($ticketModel),
        ]);
    }

    public function reply(Request $request, string $ticket)
    {
        $ticketModel = $this->findTicketForCurrentAccount($ticket);
        $this->authorize('reply', $ticketModel);

        $payload = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        if (in_array((string) $ticketModel->status, ['resolved', 'closed'], true)) {
            return back()->with('error', 'Replies are closed for this help request.');
        }

        try {
            $this->conversationService->addCustomerReply(
                $ticketModel,
                (string) ($payload['body'] ?? $payload['message'] ?? ''),
                $this->currentUser(),
            );
        } catch (BusinessException $exception) {
            return back()
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return back()->with('success', 'Your reply was added successfully.');
    }

    public function resolve(string $ticket)
    {
        $ticketModel = $this->findTicketForCurrentAccount($ticket);
        $this->authorize('close', $ticketModel);

        $ticketModel->update($this->filterExistingColumns('support_tickets', [
            'status' => 'resolved',
            'resolved_at' => now(),
        ]));

        return back()->with('success', 'The help request was marked as resolved.');
    }

    private function currentAccountId(): string
    {
        $currentAccountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($currentAccountId !== '') {
            return $currentAccountId;
        }

        return trim((string) auth()->user()?->account_id);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function findTicketForCurrentAccount(string $ticketId): SupportTicket
    {
        return $this->visibleTicketQuery()
            ->withoutGlobalScopes()
            ->whereKey($ticketId)
            ->firstOrFail();
    }

    private function visibleTicketQuery(): Builder
    {
        $query = SupportTicket::query()
            ->withoutGlobalScopes()
            ->where('account_id', $this->currentAccountId());

        if ($this->currentUserIsExternal()) {
            $query->where('user_id', (string) $this->currentUser()->id);
        }

        return $query;
    }

    private function currentUserIsExternal(): bool
    {
        $user = $this->currentUser();
        $userType = strtolower(trim((string) ($user->user_type ?? '')));

        if ($userType === 'internal' || $userType === 'external') {
            return $userType === 'external';
        }

        return trim((string) ($user->account_id ?? '')) !== '';
    }

    private function normalizeRequiredBody(string $body): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        abort_if($normalized === '', 422, 'A help request message is required.');

        return $normalized;
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

        return $query->where('ticket_number', $candidate)->exists();
    }

    private function ticketBody(SupportTicket $ticket): string
    {
        foreach (['description', 'body'] as $column) {
            $value = trim((string) $ticket->getAttribute($column));

            if ($value !== '') {
                return $value;
            }
        }

        return 'No request summary is currently available.';
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
}
