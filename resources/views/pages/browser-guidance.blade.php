@php
    $user = auth()->user();
    $accountType = (string) optional($user?->account)->type;
    $isInternalUser = (string) ($user->user_type ?? '') === 'internal';
    $variant = $isInternalUser
        ? 'internal'
        : ($accountType === 'individual' || request()->is('b2c/*')
            ? 'b2c'
            : ($accountType === 'organization' || request()->is('b2b/*') || request()->is('notifications')
                ? 'b2b'
                : 'neutral'));
    $icon = $isInternalUser
        ? 'admin'
        : ($variant === 'b2c' ? 'individual' : ($variant === 'b2b' ? 'organization' : 'alert'));
    $heading = $heading ?? 'تحتاج إلى خطوة مختلفة للمتابعة';
    $message = $message ?? 'راجع البوابة الحالية أو ارجع إلى الصفحة المناسبة لحسابك.';
@endphp

<x-browser-safe-page
    :page-title="$title ?? 'إرشاد الوصول'"
    :variant="$variant"
    :icon="$icon"
    :eyebrow="$eyebrow ?? 'إرشاد'"
    :heading="$heading"
    :message="$message"
    :summary="'نحافظ على فصل واضح بين بوابات الأفراد والأعمال والبوابة الداخلية حتى تصل إلى الأدوات الصحيحة لحسابك الحالي دون تعارض أو صلاحيات غير مناسبة.'"
    :status-code="$statusCode ?? 403"
    :primary-action-url="$primaryActionUrl ?? null"
    :primary-action-label="$primaryActionLabel ?? 'الانتقال إلى الخطوة التالية'"
    :secondary-action-url="$secondaryActionUrl ?? null"
    :secondary-action-label="$secondaryActionLabel ?? 'العودة'"
    :secondary-action-method="$secondaryActionMethod ?? 'get'"
    :meta="'إذا بدا لك أن هذه الصفحة لا تطابق نوع حسابك، ارجع إلى صفحة اختيار البوابة أو تواصل مع مدير الحساب بدل المحاولة عبر مسار مختلف.'"
>
    <ul class="browser-safe-steps">
        <li>إذا كان حسابك فرديًا فابدأ من بوابة الأفراد، وإذا كان تابعًا لمنظمة فابدأ من بوابة الأعمال، أما فريق CBEX الداخلي فله بوابة منفصلة.</li>
        <li>عند ظهور منع مرتبط بالصلاحيات، فهذا يعني أن حسابك الحالي لا يملك الوصول المطلوب لهذه الصفحة أو لهذه الوظيفة الآن.</li>
        <li>إذا كنت مستخدمًا داخليًا، فاختر سياق الحساب عند الحاجة فقط بدل التعامل مع البوابة الخارجية كما لو كانت مخصصة لك.</li>
    </ul>
</x-browser-safe-page>
