<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Services\InternalExternalAccountAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalAccountManagementController extends Controller
{
    public function __construct(
        private readonly InternalExternalAccountAdminService $accountAdminService,
    ) {}

    public function create(): View
    {
        return view('pages.admin.accounts-create', [
            'defaults' => [
                'account_type' => 'individual',
                'language' => 'ar',
                'currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'country' => 'SA',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCreateRequest($request);

        try {
            $account = $this->accountAdminService->createAccount($data, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()
                ->withErrors(['account' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('internal.accounts.show', $account->id)
            ->with('success', 'تم إنشاء الحساب الخارجي بنجاح.');
    }

    public function edit(string $account): View
    {
        $accountModel = $this->findAccountOrFail($account);

        return view('pages.admin.accounts-edit', [
            'account' => $accountModel->loadMissing([
                'organizationProfile',
                'users' => function ($query): void {
                    $query->withoutGlobalScopes()->orderByDesc('is_owner');
                },
            ]),
            'owner' => $accountModel->users
                ->first(static fn (User $user): bool => (bool) ($user->is_owner ?? false))
                ?? $accountModel->users->first(),
        ]);
    }

    public function update(Request $request, string $account): RedirectResponse
    {
        $accountModel = $this->findAccountOrFail($account);
        $data = $this->validateUpdateRequest($request, $accountModel);

        try {
            $this->accountAdminService->updateAccount($accountModel, $data, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()
                ->withErrors(['account' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('internal.accounts.show', $accountModel->id)
            ->with('success', 'تم تحديث بيانات الحساب الخارجي.');
    }

    public function activate(Request $request, string $account): RedirectResponse
    {
        return $this->handleLifecycleAction($request, $account, 'activate', 'تم تفعيل الحساب.');
    }

    public function deactivate(Request $request, string $account): RedirectResponse
    {
        return $this->handleLifecycleAction($request, $account, 'deactivate', 'تم إلغاء تفعيل الحساب.');
    }

    public function suspend(Request $request, string $account): RedirectResponse
    {
        return $this->handleLifecycleAction($request, $account, 'suspend', 'تم تعليق الحساب.');
    }

    public function unsuspend(Request $request, string $account): RedirectResponse
    {
        return $this->handleLifecycleAction($request, $account, 'unsuspend', 'تم رفع تعليق الحساب.');
    }

    private function handleLifecycleAction(
        Request $request,
        string $account,
        string $action,
        string $successMessage,
    ): RedirectResponse {
        $accountModel = $this->findAccountOrFail($account);
        $payload = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->accountAdminService->transitionLifecycle(
                $accountModel,
                $action,
                $this->currentUser($request),
                $payload['note'] ?? null,
            );
        } catch (BusinessException $exception) {
            return back()->withErrors(['account' => $exception->getMessage()]);
        }

        return redirect()
            ->route('internal.accounts.show', $accountModel->id)
            ->with('success', $successMessage);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCreateRequest(Request $request): array
    {
        return $request->validate([
            'account_name' => ['required', 'string', 'min:2', 'max:150'],
            'account_type' => ['required', 'in:individual,organization'],
            'owner_name' => ['required', 'string', 'min:2', 'max:150'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:20'],
            'language' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone:all'],
            'country' => ['nullable', 'string', 'max:3'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'legal_name' => ['required_if:account_type,organization', 'nullable', 'string', 'min:2', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'industry' => ['nullable', 'string', 'max:100'],
            'company_size' => ['nullable', 'in:small,medium,large,enterprise'],
            'org_country' => ['nullable', 'string', 'max:3'],
            'org_city' => ['nullable', 'string', 'max:100'],
            'org_address_line_1' => ['nullable', 'string', 'max:255'],
            'org_address_line_2' => ['nullable', 'string', 'max:255'],
            'org_postal_code' => ['nullable', 'string', 'max:20'],
            'org_phone' => ['nullable', 'string', 'max:20'],
            'org_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUpdateRequest(Request $request, Account $account): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'language' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone:all'],
            'country' => ['nullable', 'string', 'max:3'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ];

        if ($account->isOrganization()) {
            $rules = array_merge($rules, [
                'legal_name' => ['required', 'string', 'min:2', 'max:200'],
                'trade_name' => ['nullable', 'string', 'max:200'],
                'registration_number' => ['nullable', 'string', 'max:100'],
                'tax_id' => ['nullable', 'string', 'max:100'],
                'industry' => ['nullable', 'string', 'max:100'],
                'company_size' => ['nullable', 'in:small,medium,large,enterprise'],
                'org_country' => ['nullable', 'string', 'max:3'],
                'org_city' => ['nullable', 'string', 'max:100'],
                'org_address_line_1' => ['nullable', 'string', 'max:255'],
                'org_address_line_2' => ['nullable', 'string', 'max:255'],
                'org_postal_code' => ['nullable', 'string', 'max:20'],
                'org_phone' => ['nullable', 'string', 'max:20'],
                'org_email' => ['nullable', 'email', 'max:255'],
                'website' => ['nullable', 'url', 'max:255'],
            ]);
        }

        return $request->validate($rules);
    }

    private function findAccountOrFail(string $account): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->with([
                'organizationProfile',
                'users' => function ($query): void {
                    $query->withoutGlobalScopes()->orderByDesc('is_owner');
                },
            ])
            ->findOrFail($account);
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
