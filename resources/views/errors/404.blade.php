@php
    $accountType = (string) optional(auth()->user()?->account)->type;
    $portal = request()->is('b2c/*') || $accountType === 'individual'
        ? 'b2c'
        : ((request()->is('b2b/*') || request()->is('notifications') || $accountType === 'organization') ? 'b2b' : null);
    $isExternalPortal = $portal !== null;
    $primaryRoute = $portal === 'b2c' ? 'b2c.dashboard' : ($portal === 'b2b' ? 'b2b.dashboard' : null);
    $secondaryRoute = $portal === 'b2c' ? 'b2c.shipments.index' : ($portal === 'b2b' ? 'b2b.shipments.index' : null);
@endphp

<x-browser-safe-page
    :page-title="$isExternalPortal ? __('portal_shipments.errors.external.404.heading') : 'الصفحة غير متاحة'"
    :variant="$isExternalPortal ? $portal : 'neutral'"
    icon="dashboard"
    :eyebrow="$isExternalPortal ? __('portal_shipments.errors.external.404.eyebrow') : '404 الصفحة غير متاحة'"
    :heading="$isExternalPortal ? __('portal_shipments.errors.external.404.heading') : 'الصفحة المطلوبة غير متاحة.'"
    :message="$isExternalPortal ? __('portal_shipments.errors.external.404.message') : 'قد لا تكون الصفحة المطلوبة موجودة أو لم تعد متاحة عبر هذا المسار.'"
    :summary="$isExternalPortal
        ? __('portal_shipments.errors.external.404.message')
        : 'يمكنك العودة إلى الصفحة الرئيسية أو استخدام التنقل داخل البوابة للوصول إلى المسار الصحيح.'"
    :status-code="404"
    :primary-action-url="$primaryRoute && \Illuminate\Support\Facades\Route::has($primaryRoute) ? route($primaryRoute) : url('/')"
    :primary-action-label="$isExternalPortal ? __('portal_shipments.errors.external.primary_action') : 'العودة إلى الرئيسية'"
    :secondary-action-url="$secondaryRoute && \Illuminate\Support\Facades\Route::has($secondaryRoute) ? route($secondaryRoute) : url('/')"
    :secondary-action-label="$isExternalPortal ? __('portal_shipments.errors.external.secondary_action') : 'العودة'"
    meta="HTTP 404"
>
    <ul class="browser-safe-steps">
        <li>تحقق من أنك داخل البوابة المناسبة لنوع حسابك قبل متابعة البحث عن الصفحة المطلوبة.</li>
        <li>إذا وصلت إلى هذا الرابط من رسالة أو إشعار قديم، فمن الأفضل العودة إلى الشاشة الرئيسية ثم المتابعة من التنقل الحالي.</li>
        <li>لن نعرض تفاصيل تقنية في هذه الصفحة حتى تبقى التجربة واضحة وآمنة للمستخدم النهائي.</li>
    </ul>
</x-browser-safe-page>
