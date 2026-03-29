<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Address;
use App\Services\ShipmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PortalAddressBookController extends Controller
{
    public function __construct(protected ShipmentService $shipmentService)
    {
    }

    public function b2cIndex(Request $request): View
    {
        return $this->indexWorkspace($request, 'b2c');
    }

    public function b2cCreate(Request $request): View
    {
        return $this->formWorkspace($request, 'b2c');
    }

    public function storeB2c(Request $request): RedirectResponse
    {
        return $this->storeWorkspace($request, 'b2c');
    }

    public function b2cEdit(Request $request, string $id): View
    {
        return $this->formWorkspace($request, 'b2c', $id);
    }

    public function updateB2c(Request $request, string $id): RedirectResponse
    {
        return $this->updateWorkspace($request, $id, 'b2c');
    }

    public function destroyB2c(Request $request, string $id): RedirectResponse
    {
        return $this->destroyWorkspace($request, $id, 'b2c');
    }

    public function b2bIndex(Request $request): View
    {
        return $this->indexWorkspace($request, 'b2b');
    }

    public function b2bCreate(Request $request): View
    {
        return $this->formWorkspace($request, 'b2b');
    }

    public function storeB2b(Request $request): RedirectResponse
    {
        return $this->storeWorkspace($request, 'b2b');
    }

    public function b2bEdit(Request $request, string $id): View
    {
        return $this->formWorkspace($request, 'b2b', $id);
    }

    public function updateB2b(Request $request, string $id): RedirectResponse
    {
        return $this->updateWorkspace($request, $id, 'b2b');
    }

    public function destroyB2b(Request $request, string $id): RedirectResponse
    {
        return $this->destroyWorkspace($request, $id, 'b2b');
    }

    private function indexWorkspace(Request $request, string $portal): View
    {
        $this->authorize('viewAny', Address::class);

        $account = $this->currentAccount();
        $addresses = $this->shipmentService->listAddresses((string) $account->id);

        return view('pages.portal.addresses.index', [
            'account' => $account,
            'addresses' => $addresses,
            'portalConfig' => $this->portalConfig($portal),
            'copy' => $this->indexCopy($portal),
            'stats' => $this->addressStats($addresses),
            'canManageAddresses' => $request->user()?->can('create', Address::class) ?? false,
        ]);
    }

    private function formWorkspace(Request $request, string $portal, ?string $addressId = null): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->portalConfig($portal);
        $address = null;

        if ($addressId !== null) {
            $address = $this->findAddressForPortal($accountId, $addressId);
            $this->authorize('update', $address);
        } else {
            $this->authorize('create', Address::class);
        }

        return view('pages.portal.addresses.form', [
            'account' => $account,
            'address' => $address,
            'portalConfig' => $config,
            'copy' => $this->formCopy($portal, $address !== null),
            'typeOptions' => $this->addressTypeOptions(),
            'formRoute' => $address
                ? route($config['update_route'], ['id' => (string) $address->id])
                : route($config['store_route']),
            'formMethod' => $address ? 'PATCH' : 'POST',
        ]);
    }

    private function storeWorkspace(Request $request, string $portal): RedirectResponse
    {
        $this->authorize('create', Address::class);

        $account = $this->currentAccount();
        $data = $this->validateAddressPayload($request);

        $this->shipmentService->saveAddress((string) $account->id, $data, $request->user());

        return redirect()
            ->route($portal . '.addresses.index')
            ->with('success', __('portal_addresses.flash.created'));
    }

    private function updateWorkspace(Request $request, string $addressId, string $portal): RedirectResponse
    {
        $account = $this->currentAccount();
        $address = $this->findAddressForPortal((string) $account->id, $addressId);
        $this->authorize('update', $address);

        $data = $this->validateAddressPayload($request);
        $this->shipmentService->updateAddress((string) $account->id, (string) $address->id, $data, $request->user());

        return redirect()
            ->route($portal . '.addresses.index')
            ->with('success', __('portal_addresses.flash.updated'));
    }

    private function destroyWorkspace(Request $request, string $addressId, string $portal): RedirectResponse
    {
        $account = $this->currentAccount();
        $address = $this->findAddressForPortal((string) $account->id, $addressId);
        $this->authorize('delete', $address);

        $this->shipmentService->deleteAddress((string) $account->id, (string) $address->id);

        return redirect()
            ->route($portal . '.addresses.index')
            ->with('success', __('portal_addresses.flash.deleted'));
    }

    private function currentAccount(): Account
    {
        if (app()->bound('current_account')) {
            $account = app('current_account');

            if ($account instanceof Account) {
                return $account;
            }
        }

        $user = auth()->user();
        abort_unless($user && $user->account instanceof Account, 400, 'Unable to resolve the current account.');

        return $user->account;
    }

    private function findAddressForPortal(string $accountId, string $addressId): Address
    {
        return $this->shipmentService->findAddress($accountId, $addressId);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAddressPayload(Request $request): array
    {
        $request->merge([
            'country' => Str::upper(trim((string) $request->input('country'))),
        ]);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['sender', 'recipient', 'both'])],
            'label' => ['required', 'string', 'max:100'],
            'contact_name' => ['required', 'string', 'max:200'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:300'],
            'address_line_2' => ['nullable', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100', Rule::requiredIf(
                fn (): bool => Str::upper((string) $request->input('country')) === 'US'
            )],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
        ], [
            'state.required' => __('portal_addresses.validation.state_required'),
        ]);

        $state = trim((string) ($data['state'] ?? ''));
        $data['state'] = $state === ''
            ? null
            : (($data['country'] ?? '') === 'US' ? Str::upper($state) : $state);

        foreach (['company_name', 'email', 'address_line_2', 'postal_code'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function portalConfig(string $portal): array
    {
        return match ($portal) {
            'b2c' => [
                'portal' => 'b2c',
                'label' => __('portal_shipments.common.portal_b2c'),
                'dashboard_route' => 'b2c.dashboard',
                'index_route' => 'b2c.addresses.index',
                'create_route' => 'b2c.addresses.create',
                'store_route' => 'b2c.addresses.store',
                'edit_route' => 'b2c.addresses.edit',
                'update_route' => 'b2c.addresses.update',
                'destroy_route' => 'b2c.addresses.destroy',
                'shipment_create_route' => 'b2c.shipments.create',
            ],
            'b2b' => [
                'portal' => 'b2b',
                'label' => __('portal_shipments.common.portal_b2b'),
                'dashboard_route' => 'b2b.dashboard',
                'index_route' => 'b2b.addresses.index',
                'create_route' => 'b2b.addresses.create',
                'store_route' => 'b2b.addresses.store',
                'edit_route' => 'b2b.addresses.edit',
                'update_route' => 'b2b.addresses.update',
                'destroy_route' => 'b2b.addresses.destroy',
                'shipment_create_route' => 'b2b.shipments.create',
            ],
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function indexCopy(string $portal): array
    {
        return [
            'title' => __('portal_addresses.index.' . $portal . '.title'),
            'description' => __('portal_addresses.index.' . $portal . '.description'),
            'create_cta' => __('portal_addresses.common.create_cta'),
            'empty_state' => __('portal_addresses.index.' . $portal . '.empty_state'),
            'guidance_title' => __('portal_addresses.index.' . $portal . '.guidance_title'),
            'guidance_cards' => __('portal_addresses.index.' . $portal . '.guidance_cards'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formCopy(string $portal, bool $editing): array
    {
        $mode = $editing ? 'edit' : 'create';

        return [
            'title' => __('portal_addresses.form.' . $portal . '.' . $mode . '_title'),
            'description' => __('portal_addresses.form.' . $portal . '.' . $mode . '_description'),
            'submit' => __('portal_addresses.common.' . ($editing ? 'update_cta' : 'save_cta')),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function addressTypeOptions(): array
    {
        return [
            'both' => __('portal_addresses.types.both'),
            'sender' => __('portal_addresses.types.sender'),
            'recipient' => __('portal_addresses.types.recipient'),
        ];
    }

    /**
     * @return array<int, array{icon: string, label: string, value: string}>
     */
    private function addressStats(Collection $addresses): array
    {
        $senderReady = $addresses->filter(
            fn (Address $address): bool => in_array((string) $address->type, ['sender', 'both'], true)
        )->count();

        $recipientReady = $addresses->filter(
            fn (Address $address): bool => in_array((string) $address->type, ['recipient', 'both'], true)
        )->count();

        return [
            [
                'icon' => 'ADR',
                'label' => __('portal_addresses.stats.total'),
                'value' => number_format($addresses->count()),
            ],
            [
                'icon' => 'SND',
                'label' => __('portal_addresses.stats.sender_ready'),
                'value' => number_format($senderReady),
            ],
            [
                'icon' => 'RCV',
                'label' => __('portal_addresses.stats.recipient_ready'),
                'value' => number_format($recipientReady),
            ],
        ];
    }
}
