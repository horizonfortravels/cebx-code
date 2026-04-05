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
use Symfony\Component\HttpFoundation\Response;

class AuthWebController extends Controller
{
    public function showLogin(): RedirectResponse|View|Response
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
            return back()->withErrors([
                'email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.',
            ])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();

        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return $this->postLoginRedirect($user, $request);
    }

    public function portalSelector(): RedirectResponse|View|Response
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            return $this->redirectAuthenticatedUserHome($user);
        }

        return view('pages.auth.portal-selector');
    }

    public function showB2bLogin(): RedirectResponse|View|Response
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            if ((string) optional($user->account)->type === 'organization') {
                return redirect()->route('b2b.dashboard');
            }

            return $this->wrongPortalResponse($user, 'b2b');
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
            return back()->withErrors([
                'email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.',
            ])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();

        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        if ((string) optional($user->account)->type !== 'organization') {
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

    public function showB2cLogin(): RedirectResponse|View|Response
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            if ((string) optional($user->account)->type === 'individual') {
                return redirect()->route('b2c.dashboard');
            }

            return $this->wrongPortalResponse($user, 'b2c');
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
            return back()->withErrors([
                'email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.',
            ])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();

        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        if ((string) optional($user->account)->type !== 'individual') {
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

    public function showAdminLogin(): RedirectResponse|View|Response
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            return $this->wrongPortalResponse($user, 'admin');
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
            return back()->withErrors([
                'email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.',
            ])->onlyInput('email');
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

        return $this->postLoginRedirect($user, $request);
    }

    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $loginUrl = url('/login');

        if ($user) {
            $loginUrl = match (true) {
                $this->isInternal($user) => url('/admin/login'),
                (string) optional($user->account)->type === 'individual' => url('/b2c/login'),
                (string) optional($user->account)->type === 'organization' => url('/b2b/login'),
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

    private function postLoginRedirect(User $user, Request $request): RedirectResponse
    {
        if ($this->isInternal($user)) {
            return redirect()->route($this->internalHomeRouteName($user));
        }

        $fallbackRoute = $this->externalHomeRouteName($user);
        $intended = (string) $request->session()->get('url.intended', '');

        if ($this->isAllowedIntendedUrl($user, $intended)) {
            return redirect()->to($intended);
        }

        return redirect()->route($fallbackRoute);
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

    private function externalHomeRouteName(User $user): string
    {
        return match ((string) optional($user->account)->type) {
            'organization' => 'b2b.dashboard',
            'individual' => 'b2c.dashboard',
            default => 'login',
        };
    }

    private function externalLoginRouteName(User $user): string
    {
        return match ((string) optional($user->account)->type) {
            'organization' => 'b2b.login',
            'individual' => 'b2c.login',
            default => 'login',
        };
    }

    private function redirectAuthenticatedUserHome(User $user): RedirectResponse
    {
        if ($this->isInternal($user)) {
            return redirect()->route($this->internalHomeRouteName($user));
        }

        return redirect()->route($this->externalHomeRouteName($user));
    }

    private function isAllowedIntendedUrl(User $user, string $intended): bool
    {
        if ($intended === '') {
            return false;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        return match ((string) optional($user->account)->type) {
            'individual' => str_starts_with($path, '/b2c/') || $path === '/notifications',
            'organization' => str_starts_with($path, '/b2b/') || $path === '/notifications',
            default => false,
        };
    }

    private function wrongPortalResponse(User $user, string $targetPortal): Response
    {
        if ($targetPortal === 'admin') {
            return response()->view('pages.browser-guidance', [
                'statusCode' => 403,
                'eyebrow' => 'بوابة داخلية فقط',
                'title' => 'البوابة الداخلية',
                'heading' => 'هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة',
                'message' => 'تم تسجيل دخولك بحساب خارجي، لذلك لن تتمكن من متابعة العمل من بوابة الإدارة الداخلية. استخدم بوابتك الحالية بدلًا من ذلك.',
                'primaryActionLabel' => (string) optional($user->account)->type === 'organization'
                    ? 'العودة إلى بوابة الأعمال'
                    : 'العودة إلى بوابة الأفراد',
                'primaryActionUrl' => route($this->externalHomeRouteName($user)),
                'secondaryActionLabel' => 'العودة إلى اختيار البوابة',
                'secondaryActionUrl' => route('login'),
            ], 403);
        }

        if ($targetPortal === 'b2b') {
            return response()->view('pages.browser-guidance', [
                'statusCode' => 403,
                'eyebrow' => 'البوابة غير المناسبة',
                'title' => 'بوابة الأعمال',
                'heading' => 'هذه المنطقة مخصصة لبوابة الأعمال الخاصة بحسابات المنظمات',
                'message' => 'حسابك الحالي فردي، لذلك ستجد شحناتك ومحفظتك والمتابعة الخاصة بك داخل بوابة الأفراد وليس داخل بوابة الأعمال.',
                'primaryActionLabel' => 'العودة إلى بوابة الأفراد',
                'primaryActionUrl' => route('b2c.dashboard'),
                'secondaryActionLabel' => 'فتح صفحة دخول الأفراد',
                'secondaryActionUrl' => route('b2c.login'),
            ], 403);
        }

        return response()->view('pages.browser-guidance', [
            'statusCode' => 403,
            'eyebrow' => 'البوابة غير المناسبة',
            'title' => 'بوابة الأفراد',
            'heading' => 'هذه المنطقة مخصصة لبوابة الأفراد الخاصة بالحسابات الفردية',
            'message' => 'حسابك الحالي تابع لمنظمة، لذلك ستجد الشحنات والتقارير وأدوات الفريق داخل بوابة الأعمال وليس داخل بوابة الأفراد.',
            'primaryActionLabel' => 'العودة إلى بوابة الأعمال',
            'primaryActionUrl' => route('b2b.dashboard'),
            'secondaryActionLabel' => 'فتح صفحة دخول الأعمال',
            'secondaryActionUrl' => route('b2b.login'),
        ], 403);
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

        $actualType = (string) optional($user->account)->type;

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
