<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\InternalTicketWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternalTicketWorkflowController extends Controller
{
    public function __construct(
        private readonly InternalTicketWorkflowService $workflowService,
    ) {}

    public function updateStatus(Request $request, string $ticket): RedirectResponse
    {
        $payload = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->workflowService->manualStatusOptions()))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $ticketModel = $this->findTicketOrFail($ticket);

        try {
            $this->workflowService->changeStatus(
                (string) $ticketModel->id,
                (string) $payload['status'],
                (string) ($payload['note'] ?? ''),
                $this->currentUser($request),
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.tickets.show', $ticketModel)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.tickets.show', $ticketModel)
            ->with('success', 'The ticket workflow state was updated successfully.');
    }

    public function updateAssignment(Request $request, string $ticket): RedirectResponse
    {
        $payload = $request->validate([
            'assigned_to' => ['nullable', 'string', Rule::exists('users', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $ticketModel = $this->findTicketOrFail($ticket);

        try {
            $this->workflowService->assign(
                (string) $ticketModel->id,
                $payload['assigned_to'] ?? null,
                (string) ($payload['note'] ?? ''),
                $this->currentUser($request),
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.tickets.show', $ticketModel)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.tickets.show', $ticketModel)
            ->with('success', 'The ticket assignment was updated successfully.');
    }

    public function updateTriage(Request $request, string $ticket): RedirectResponse
    {
        $payload = $request->validate([
            'priority' => ['required', Rule::in(array_keys($this->workflowService->priorityOptions()))],
            'category' => ['required', Rule::in(array_keys($this->workflowService->categoryOptions()))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $ticketModel = $this->findTicketOrFail($ticket);

        try {
            $this->workflowService->changeTriage(
                (string) $ticketModel->id,
                (string) $payload['priority'],
                (string) $payload['category'],
                (string) ($payload['note'] ?? ''),
                $this->currentUser($request),
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.tickets.show', $ticketModel)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.tickets.show', $ticketModel)
            ->with('success', 'The ticket triage details were updated successfully.');
    }

    public function storeInternalNote(Request $request, string $ticket): RedirectResponse
    {
        $payload = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        $ticketModel = $this->findTicketOrFail($ticket);

        try {
            $this->workflowService->addInternalNote(
                (string) $ticketModel->id,
                (string) $payload['body'],
                $this->currentUser($request),
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.tickets.show', $ticketModel)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.tickets.show', $ticketModel)
            ->with('success', 'The internal ticket note was added successfully.');
    }

    private function findTicketOrFail(string $ticket): SupportTicket
    {
        return SupportTicket::query()
            ->withoutGlobalScopes()
            ->findOrFail($ticket);
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
