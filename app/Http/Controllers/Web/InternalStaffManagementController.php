<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InternalStaffAdminService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class InternalStaffManagementController extends Controller
{
    public function __construct(
        private readonly InternalStaffAdminService $staffAdminService,
    ) {}

    public function create(InternalControlPlane $controlPlane): View
    {
        return view('pages.admin.staff-create', [
            'defaults' => [
                'locale' => 'en',
                'timezone' => 'UTC',
                'role' => InternalControlPlane::ROLE_SUPPORT,
            ],
            'roleOptions' => $this->roleOptions($controlPlane),
        ]);
    }

    public function store(Request $request, InternalControlPlane $controlPlane): RedirectResponse
    {
        $data = $this->validateStoreRequest($request, $controlPlane);
        $mode = $data['provisioning_mode'] ?? 'create';

        try {
            $staffUser = $mode === 'invite'
                ? $this->staffAdminService->inviteStaffUser($data, $this->currentUser($request))
                : $this->staffAdminService->createStaffUser($data, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()
                ->withErrors(['staff' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('internal.staff.show', $staffUser)
            ->with('success', $mode === 'invite'
                ? 'تمت إضافة الموظف وإرسال رابط إعداد كلمة المرور.'
                : 'تم إنشاء حساب الموظف الداخلي بنجاح.');
    }

    public function edit(string $user, InternalControlPlane $controlPlane): View
    {
        $staffUser = $this->findInternalStaffOrFail($user);

        return view('pages.admin.staff-edit', [
            'staffUser' => $staffUser,
            'currentRole' => $controlPlane->primaryCanonicalRole($staffUser),
            'roleOptions' => $this->roleOptions($controlPlane),
        ]);
    }

    public function update(Request $request, string $user, InternalControlPlane $controlPlane): RedirectResponse
    {
        $staffUser = $this->findInternalStaffOrFail($user);
        $data = $this->validateUpdateRequest($request, $staffUser, $controlPlane);

        try {
            $this->staffAdminService->updateStaffUser($staffUser, $data, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()
                ->withErrors(['staff' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('internal.staff.show', $staffUser)
            ->with('success', 'تم تحديث ملف الموظف الداخلي وتعيين دوره المعتمد.');
    }

    public function activate(Request $request, string $user): RedirectResponse
    {
        return $this->handleLifecycleAction($request, $user, 'activate', 'تم تفعيل حساب الموظف الداخلي.');
    }

    public function deactivate(Request $request, string $user): RedirectResponse
    {
        return $this->handleLifecycleAction($request, $user, 'deactivate', 'تم إيقاف حساب الموظف الداخلي.');
    }

    public function suspend(Request $request, string $user): RedirectResponse
    {
        return $this->handleLifecycleAction($request, $user, 'suspend', 'تم تعليق حساب الموظف الداخلي.');
    }

    public function unsuspend(Request $request, string $user): RedirectResponse
    {
        return $this->handleLifecycleAction($request, $user, 'unsuspend', 'تم رفع تعليق حساب الموظف الداخلي.');
    }

    public function passwordReset(Request $request, string $user): RedirectResponse
    {
        $staffUser = $this->findInternalStaffOrFail($user);

        try {
            $this->staffAdminService->sendStaffPasswordReset($staffUser, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()->withErrors(['staff' => $exception->getMessage()]);
        }

        return redirect()
            ->route('internal.staff.show', $staffUser)
            ->with('success', 'تم إرسال رابط إعادة تعيين كلمة المرور إلى ' . $staffUser->email . '.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStoreRequest(Request $request, InternalControlPlane $controlPlane): array
    {
        return $request->validate([
            'provisioning_mode' => ['nullable', Rule::in(['create', 'invite'])],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone:all'],
            'role' => ['required', Rule::in($controlPlane->canonicalRoles())],
            'password' => [
                'nullable',
                'required_if:provisioning_mode,create',
                'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUpdateRequest(Request $request, User $staffUser, InternalControlPlane $controlPlane): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore((string) $staffUser->id)],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone:all'],
            'role' => ['required', Rule::in($controlPlane->canonicalRoles())],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(InternalControlPlane $controlPlane): array
    {
        $options = [];

        foreach ($controlPlane->canonicalRoles() as $roleName) {
            $options[$roleName] = $controlPlane->roleProfileForCanonicalRole($roleName)['label'];
        }

        return $options;
    }

    private function findInternalStaffOrFail(string $user): User
    {
        return $this->internalUsersQuery()
            ->with(['internalRoles'])
            ->findOrFail($user);
    }

    private function internalUsersQuery(): Builder
    {
        $query = User::query()->withoutGlobalScopes();

        if (!Schema::hasColumn('users', 'user_type')) {
            return $query->whereNull('account_id');
        }

        return $query->where(function (Builder $inner): void {
            $inner->where('user_type', 'internal')
                ->orWhere(function (Builder $legacy): void {
                    $legacy->where(function (Builder $legacyType): void {
                        $legacyType->whereNull('user_type')
                            ->orWhere('user_type', '');
                    })->whereNull('account_id');
                });
        });
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function handleLifecycleAction(
        Request $request,
        string $user,
        string $action,
        string $successMessage,
    ): RedirectResponse {
        $staffUser = $this->findInternalStaffOrFail($user);
        $payload = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->staffAdminService->transitionLifecycle(
                $staffUser,
                $action,
                $this->currentUser($request),
                $payload['note'] ?? null,
            );
        } catch (BusinessException $exception) {
            return back()->withErrors(['staff' => $exception->getMessage()]);
        }

        return redirect()
            ->route('internal.staff.show', $staffUser)
            ->with('success', $successMessage);
    }
}
