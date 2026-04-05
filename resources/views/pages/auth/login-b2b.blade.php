@extends('layouts.auth')

@section('title', 'دخول الأعمال - حسابات المنظمات')
@section('auth-portal', 'b2b')

@section('brand-mark')
    <x-portal-icon name="organization" class="brand-logo__icon" />
@endsection

@section('brand-badge', 'حسابات المنظمات والفرق')
@section('brand-title', 'بوابة الأعمال')
@section('brand-description', 'بوابة مخصصة لحسابات المنظمات الخارجية، لإدارة الشحنات والطلبات والمحفظة والتقارير وأدوات المنصة المسموح بها ضمن مساحة عمل واحدة.')
@section('brand-features')
    <li>لوحة تشغيل موحدة للشحنات والطلبات اليومية</li>
    <li>متابعة الفريق والأدوار وفق الصلاحيات المتاحة</li>
    <li>الوصول إلى أدوات التكامل الخاصة بالمنصة عند السماح بها</li>
@endsection

@section('form-title', 'دخول بوابة الأعمال')
@section('form-subtitle', 'سجل دخولك بحساب المنظمة للوصول إلى الشحنات والتقارير وأدوات المنصة الخاصة بفريقك')
@section('form-action', route('b2b.login.submit'))
@section('email-placeholder', 'مثال: ops@company.sa')
@section('btn-text', 'الدخول إلى بوابة الأعمال')

@section('form-badge')
    حساب منظمة خارجي فقط
@endsection

@section('form-note')
    <strong>متى أستخدم هذه الصفحة؟</strong>
    استخدم هذه الصفحة إذا كان حسابك تابعًا لمنظمة أو فريق عمل داخل CBEX. أدوات المطور والتكاملات هنا
    تخص تكامل منظمتك مع المنصة، ولا تعني امتلاك إعدادات الناقلين أو إدارتها مباشرة.
@endsection

@section('form-support')
    <p class="login-support-title">بوابات أخرى قد تحتاجها</p>
    <div class="login-support-links">
        <a href="{{ route('b2c.login') }}" class="login-support-link">
            <span class="login-support-link__text">
                <span class="login-support-link__title">هل حسابك فردي؟</span>
                <span class="login-support-link__meta">استخدم بوابة الأفراد إذا كان الحساب مرتبطًا بشخص واحد.</span>
            </span>
            <span class="login-support-link__icon" aria-hidden="true">
                <x-portal-icon name="individual" />
            </span>
        </a>
        <a href="{{ route('login') }}" class="login-support-link">
            <span class="login-support-link__text">
                <span class="login-support-link__title">العودة إلى اختيار البوابة</span>
                <span class="login-support-link__meta">ابدأ من صفحة الاختيار إذا كنت تريد مراجعة نوع البوابة المناسبة.</span>
            </span>
            <span class="login-support-link__icon" aria-hidden="true">
                <x-portal-icon name="dashboard" />
            </span>
        </a>
    </div>
@endsection
