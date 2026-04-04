<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\InternalTicketAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InternalTicketManagementController extends Controller
{
    public function __construct(
        private readonly InternalTicketAdminService $ticketAdminService,
    ) {}

    public function create(): View
    {
        return $this->renderCreateForm(null, null);
    }

    public function createForAccount(string $account): View
    {
        $accountModel = $this->findAccountOrFail($account);

        return $this->renderCreateForm($accountModel, null);
    }

    public function createForShipment(string $shipment): View
    {
        $shipmentModel = $this->findShipmentOrFail($shipment);
        $accountModel = $shipmentModel->account;

        abort_unless($accountModel instanceof Account, 404);

        return $this->renderCreateForm($accountModel, $shipmentModel);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateStoreRequest($request);

        try {
            $ticket = $this->ticketAdminService->createTicket($data, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()
                ->withErrors(['ticket' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('internal.tickets.show', $ticket)
            ->with('success', 'The internal ticket was created successfully and linked to the selected context.');
    }

    private function renderCreateForm(?Account $account, ?Shipment $shipment): View
    {
        $selectedAccount = $account instanceof Account ? $this->accountSummary($account) : null;
        $selectedShipment = $shipment instanceof Shipment ? $this->shipmentSummary($shipment) : null;

        return view('pages.admin.tickets-create', [
            'accountOptions' => $selectedAccount ? collect() : $this->accountOptions(),
            'selectedAccount' => $selectedAccount,
            'selectedShipment' => $selectedShipment,
            'defaults' => [
                'category' => $selectedShipment ? 'shipping' : ($selectedAccount ? 'account' : 'general'),
                'priority' => 'medium',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStoreRequest(Request $request): array
    {
        return $request->validate([
            'account_id' => [
                'required',
                'string',
                Rule::exists('accounts', 'id'),
            ],
            'shipment_id' => [
                'nullable',
                'string',
                Rule::exists('shipments', 'id')->where(function ($query) use ($request): void {
                    $query->where('account_id', (string) $request->input('account_id'));
                }),
            ],
            'subject' => ['required', 'string', 'min:4', 'max:300'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['required', Rule::in(['shipping', 'billing', 'technical', 'account', 'carrier', 'general'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
        ]);
    }

    /**
     * @return Collection<int, array{id: string, label: string}>
     */
    private function accountOptions(): Collection
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->with('organizationProfile')
            ->orderBy('name')
            ->get()
            ->map(function (Account $account): array {
                $label = $account->name . ' - ' . ($account->isOrganization() ? 'Organization' : 'Individual');

                if ($account->slug) {
                    $label .= ' - ' . $account->slug;
                }

                return [
                    'id' => (string) $account->id,
                    'label' => $label,
                ];
            })
            ->values();
    }

    private function accountSummary(Account $account): array
    {
        $organizationName = trim((string) ($account->organizationProfile?->legal_name ?: $account->organizationProfile?->trade_name ?: ''));

        return [
            'account' => $account,
            'id' => (string) $account->id,
            'name' => (string) $account->name,
            'slug' => (string) ($account->slug ?? 'N/A'),
            'type_label' => $account->isOrganization() ? 'Organization' : 'Individual',
            'organization_label' => $organizationName !== '' ? $organizationName : null,
        ];
    }

    private function shipmentSummary(Shipment $shipment): array
    {
        $tracking = trim((string) ($shipment->tracking_number ?: $shipment->carrier_tracking_number ?: ''));

        return [
            'shipment' => $shipment,
            'id' => (string) $shipment->id,
            'reference' => (string) ($shipment->reference_number ?: $shipment->id),
            'status_label' => $this->headline((string) ($shipment->status ?? '')),
            'tracking_summary' => $tracking !== '' ? 'Tracking/AWB recorded' : 'No tracking summary recorded',
        ];
    }

    private function findAccountOrFail(string $account): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->with('organizationProfile')
            ->findOrFail($account);
    }

    private function findShipmentOrFail(string $shipment): Shipment
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->with('account.organizationProfile')
            ->findOrFail($shipment);
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function headline(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return 'Not available';
        }

        return str_replace('_', ' ', ucwords(strtolower($normalized), '_'));
    }
}
