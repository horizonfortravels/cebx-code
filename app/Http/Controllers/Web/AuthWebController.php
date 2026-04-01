<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Internal\InternalControlPlane;
use App\Support\Tenancy\WebTenantContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthWebController extends Controller
{
    public function showLogin(): RedirectResponse|View
    {
        return $this->portalSelector();
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();
        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return $this->postLoginRedirect($user);
    }

    public function portalSelector(): RedirectResponse|View
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            return redirect()->route('dashboard');
        }

        return view('pages.auth.portal-selector');
    }

    public function showB2bLogin(): RedirectResponse|View
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            if ($user->account && $user->account->type === 'organization') {
                return redirect()->route('b2b.dashboard');
            }
        }

        return view('pages.auth.login-b2b');
    }

    public function loginB2b(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();
        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        if (! $user->account || $user->account->type !== 'organization') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => $this->portalMismatchMessage($user, 'organization'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect()->route('b2b.dashboard');
    }

    public function showB2cLogin(): RedirectResponse|View
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            if ($user->account && $user->account->type === 'individual') {
                return redirect()->route('b2c.dashboard');
            }
        }

        return view('pages.auth.login-b2c');
    }

    public function loginB2c(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();
        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        if (! $user->account || $user->account->type !== 'individual') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => $this->portalMismatchMessage($user, 'individual'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect()->route('b2c.dashboard');
    }

    public function showAdminLogin(): RedirectResponse|View
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            return redirect()->route('dashboard');
        }

        return view('pages.auth.login-admin');
    }

    public function loginAdmin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();
        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        if (! $this->isInternal($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'هذا الحساب لا يملك وصولًا داخليًا. استخدم بوابة الأفراد للحسابات الفردية أو بوابة الأعمال لحسابات المنظمات بحسب نوع حسابك.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return $this->postLoginRedirect($user);
    }

    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $loginUrl = url('/login');

        if ($user) {
            $loginUrl = match (true) {
                $this->isInternal($user) => url('/admin/login'),
                optional($user->account)->type === 'individual' => url('/b2c/login'),
                optional($user->account)->type === 'organization' => url('/b2b/login'),
                default => url('/login'),
            };
        }

        Auth::logout();
        WebTenantContext::clear($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to($loginUrl);
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('pages.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors(['email' => __($status)])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', $credentials['email'])
            ->first();

        return redirect()
            ->route($this->passwordResetLoginRouteName($user))
            ->with('success', 'تمت إعادة تعيين كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.');
    }

    private function postLoginRedirect(User $user): RedirectResponse
    {
        if ($this->isInternal($user)) {
            return redirect()->route($this->internalHomeRouteName($user));
        }

        return redirect()->intended(url('/'));
    }

    private function passwordResetLoginRouteName(?User $user): string
    {
        if (! $user instanceof User) {
            return 'login';
        }

        if ($this->isInternal($user)) {
            return 'admin.login';
        }

        return match ((string) optional($user->account)->type) {
            'organization' => 'b2b.login',
            'individual' => 'b2c.login',
            default => 'login',
        };
    }

    private function internalHomeRouteName(User $user): string
    {
        return app(InternalControlPlane::class)->landingRouteName($user);
    }

    private function isInternal(User $user): bool
    {
        return ($user->user_type ?? null) === 'internal';
    }

    private function rejectInactiveUser(Request $request, User $user): ?RedirectResponse
    {
        $status = (string) ($user->status ?? '');
        if (! in_array($status, ['suspended', 'disabled'], true)) {
            return null;
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return back()->withErrors([
            'email' => $status === 'suspended'
                ? 'تم تعليق هذا الحساب مؤقتًا. تواصل مع الدعم أو مدير الحساب لمراجعة حالة الوصول.'
                : 'تم إيقاف هذا الحساب حاليًا. تواصل مع الدعم أو مدير الحساب لإعادة التفعيل.',
        ])->onlyInput('email');
    }

    private function portalMismatchMessage(User $user, string $requiredType): string
    {
        if ($this->isInternal($user)) {
            return 'هذا الحساب داخلي. استخدم البوابة الداخلية للمنصة بدل بوابات العملاء.';
        }

        $actualType = optional($user->account)->type;

        if ($requiredType === 'organization') {
            return $actualType === 'individual'
                ? 'هذا الحساب فردي. استخدم بوابة الأفراد المخصصة للحسابات الفردية بدل بوابة الأعمال المخصصة لحسابات المنظمات.'
                : 'هذا الحساب لا يتبع حساب منظمة. استخدم البوابة المناسبة لنوع الحساب.';
        }

        return $actualType === 'organization'
            ? 'هذا الحساب يتبع منظمة. استخدم بوابة الأعمال المخصصة لحسابات المنظمات بدل بوابة الأفراد المخصصة للحسابات الفردية.'
            : 'هذا الحساب لا يتبع حسابًا فرديًا. استخدم البوابة المناسبة لنوع الحساب.';
    }
}
