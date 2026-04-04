<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\InternalTicketWorkflowService;
use App\Services\SupportTicketConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InternalTicketConversationController extends Controller
{
    public function __construct(
        private readonly SupportTicketConversationService $conversationService,
        private readonly InternalTicketWorkflowService $workflowService,
    ) {}

    public function storeReply(Request $request, string $ticket): RedirectResponse
    {
        $payload = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        $ticketModel = $this->findTicketOrFail($ticket);

        try {
            $this->conversationService->addStaffReply(
                $ticketModel,
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
            ->with('success', 'The customer-visible support reply was added successfully.');
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
