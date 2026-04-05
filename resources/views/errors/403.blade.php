@php
    $accountType = (string) optional(auth()->user()?->account)->type;
    $isInternalUser = (string) (auth()->user()?->user_type ?? '') === 'internal';
    $portal = request()->is('b2c/*') || $accountType === 'individual'
        ? 'b2c'
        : ((request()->is('b2b/*') || request()->is('notifications') || $accountType === 'organization') ? 'b2b' : null);
    $isExternalPortal = $portal !== null;
    $primaryRoute = $portal === 'b2c' ? 'b2c.dashboard' : ($portal === 'b2b' ? 'b2b.dashboard' : null);
    $secondaryRoute = $portal === 'b2c' ? 'b2c.shipments.index' : ($portal === 'b2b' ? 'b2b.shipments.index' : null);
    $fallbackMessage = $isExternalPortal
        ? __('portal_shipments.errors.external.403.message')
        : 'هذه الصفحة غير متاحة بصلاحيات الحساب الحالية.';
    $message = trim((string) ($exception->getMessage() ?? ''));

    if ($message === '' || $message === 'This action is unauthorized.') {
        $message = $fallbackMessage;
    }
@endphp

<x-browser-safe-page
    :page-title="$isExternalPortal ? __('portal_shipments.errors.external.403.heading') : 'وصول مقيّد'"
    :variant="$isExternalPortal ? $portal : ($isInternalUser ? 'internal' : 'neutral')"
    icon="alert"
    :eyebrow="$isExternalPortal ? __('portal_shipments.errors.external.403.eyebrow') : '403 وصول مقيّد'"
    :heading="$isExternalPortal ? __('portal_shipments.errors.external.403.heading') : 'لا يمكنك فتح هذه الصفحة.'"
    :message="$message"
    :summary="$isExternalPortal
        ? __('portal_shipments.errors.external.403.message')
        : 'استخدم البوابة الصحيحة أو اطلب الصلاحية المطلوبة قبل إعادة المحاولة.'"
    :status-code="403"
    :primary-action-url="$primaryRoute && \Illuminate\Support\Facades\Route::has($primaryRoute) ? route($primaryRoute) : url('/')"
    :primary-action-label="$isExternalPortal ? __('portal_shipments.errors.external.primary_action') : 'العودة إلى الرئيسية'"
    :secondary-action-url="$secondaryRoute && \Illuminate\Support\Facades\Route::has($secondaryRoute) ? route($secondaryRoute) : url()->previous()"
    :secondary-action-label="$isExternalPortal ? __('portal_shipments.errors.external.secondary_action') : 'العودة'"
    meta="HTTP 403"
>
    <ul class="browser-safe-steps">
        <li>قد تكون الصفحة خارج نطاق دورك الحالي، أو تتطلب بوابة مختلفة عن بوابتك الحالية.</li>
        <li>إذا كنت مستخدمًا خارجيًا فارجع إلى بوابة حسابك الحالية بدلًا من محاولة استخدام صفحات غير مخصصة له.</li>
        <li>إذا استمر المنع داخل نفس البوابة، فراجع صلاحياتك مع مدير الحساب أو فريق الدعم دون مشاركة أي تفاصيل حساسة.</li>
    </ul>
</x-browser-safe-page>
