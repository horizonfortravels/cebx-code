@extends('layouts.auth')

@section('title', 'دخول الموظفين - البوابة الداخلية')
@section('auth-portal', 'admin')

@section('brand-mark')
    <x-portal-icon name="admin" class="brand-logo__icon" />
@endsection

@section('brand-badge', 'البوابة الداخلية لفريق CBEX')
@section('brand-title', 'مساحة عمل الموظفين')
@section('brand-description', 'هذه البوابة مخصصة لفرق التشغيل والدعم والإدارة داخل CBEX فقط، للوصول إلى لوحات المتابعة ومراكز القراءة والإجراءات الداخلية بحسب الدور والصلاحيات الممنوحة لكل موظف.')
@section('brand-features')
    <li>متابعة الشحنات والحسابات والتنبيهات التشغيلية من مساحة موحدة</li>
    <li>الوصول إلى المراكز واللوحات الداخلية وفق الدور دون خلط مع بوابات العملاء</li>
    <li>مراجعة السياقات الحساسة والدعم والعمليات اليومية ضمن تجربة واضحة وآمنة</li>
@endsection

@section('form-title', 'دخول البوابة الداخلية')
@section('form-subtitle', 'سجّل دخولك بحسابك الوظيفي للوصول إلى أدوات التشغيل والدعم ولوحات المتابعة الداخلية')
@section('form-action', route('admin.login.submit'))
@section('email-placeholder', 'مثال: staff@cbex.sa')
@section('btn-text', 'الدخول إلى البوابة الداخلية')

@section('form-badge')
    مخصصة لموظفي المنصة فقط
@endsection

@section('form-note')
    <strong>متى أستخدم هذه الصفحة؟</strong>
    استخدم هذه الصفحة إذا كنت من فريق CBEX الداخلي. إذا كان حسابك خارجيًا كفرد أو كمنظمة، فانتقل إلى بوابة الأفراد أو بوابة الأعمال بدلًا من هذه الصفحة.
@endsection

@section('form-support')
    <p class="login-support-title">بوابات أخرى قد تحتاجها</p>
    <div class="login-support-links">
        <a href="{{ route('login') }}" class="login-support-link">
            <span class="login-support-link__text">
                <span class="login-support-link__title">العودة إلى اختيار البوابة</span>
                <span class="login-support-link__meta">ابدأ من صفحة الاختيار إذا كنت تريد مراجعة نوع البوابة المناسبة قبل تسجيل الدخول.</span>
            </span>
            <span class="login-support-link__icon" aria-hidden="true">
                <x-portal-icon name="dashboard" />
            </span>
        </a>
        <a href="{{ route('b2c.login') }}" class="login-support-link">
            <span class="login-support-link__text">
                <span class="login-support-link__title">هل حسابك فردي؟</span>
                <span class="login-support-link__meta">استخدم بوابة الأفراد إذا كان الحساب مرتبطًا بشخص واحد وحساب شحن خارجي فردي.</span>
            </span>
            <span class="login-support-link__icon" aria-hidden="true">
                <x-portal-icon name="individual" />
            </span>
        </a>
        <a href="{{ route('b2b.login') }}" class="login-support-link">
            <span class="login-support-link__text">
                <span class="login-support-link__title">هل تعمل ضمن منظمة؟</span>
                <span class="login-support-link__meta">استخدم بوابة الأعمال إذا كان الحساب تابعًا لمنظمة أو فريق خارجي داخل المنصة.</span>
            </span>
            <span class="login-support-link__icon" aria-hidden="true">
                <x-portal-icon name="organization" />
            </span>
        </a>
    </div>
@endsection
