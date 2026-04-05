@extends('layouts.auth')

@section('title', 'دخول الأفراد - الحسابات الفردية')
@section('auth-portal', 'b2c')

@section('brand-mark')
    <x-portal-icon name="individual" class="brand-logo__icon" />
@endsection

@section('brand-badge', 'الحسابات الفردية الخارجية')
@section('brand-title', 'بوابة الأفراد')
@section('brand-description', 'بوابة مخصصة للحسابات الفردية الخارجية فقط، لإدارة الشحنات الشخصية، المتابعة، المحفظة، والعناوين ضمن تجربة واضحة وسهلة.')
@section('brand-features')
    <li>بدء الشحنة ومتابعتها بخطوات واضحة من المتصفح</li>
    <li>عرض الشحنات الجارية وحالات التسليم الأخيرة</li>
    <li>إدارة العناوين والمحفظة والدعم من مساحة واحدة</li>
@endsection

@section('form-title', 'دخول بوابة الأفراد')
@section('form-subtitle', 'سجل دخولك بالحساب الفردي الخارجي للوصول إلى شحناتك الشخصية ومحفظتك')
@section('form-action', route('b2c.login.submit'))
@section('email-placeholder', 'مثال: name@example.sa')
@section('btn-text', 'الدخول إلى بوابة الأفراد')

@section('form-badge')
    حساب فردي خارجي فقط
@endsection

@section('form-note')
    <strong>متى أستخدم هذه الصفحة؟</strong>
    استخدم هذه الصفحة إذا كان حسابك الشخصي داخل CBEX مرتبطًا بفرد واحد. إذا كان الحساب تابعًا لمنظمة أو فريق عمل،
    فستحتاج إلى بوابة الأعمال بدلًا من هذه البوابة.
@endsection

@section('form-support')
    <p class="login-support-title">بوابات أخرى قد تحتاجها</p>
    <div class="login-support-links">
        <a href="{{ route('b2b.login') }}" class="login-support-link">
            <span class="login-support-link__text">
                <span class="login-support-link__title">هل لديك حساب منظمة؟</span>
                <span class="login-support-link__meta">استخدم بوابة الأعمال للحسابات المؤسسية والفرق.</span>
            </span>
            <span class="login-support-link__icon" aria-hidden="true">
                <x-portal-icon name="organization" />
            </span>
        </a>
        <a href="{{ route('login') }}" class="login-support-link">
            <span class="login-support-link__text">
                <span class="login-support-link__title">العودة إلى اختيار البوابة</span>
                <span class="login-support-link__meta">إذا لم تكن متأكدًا من نوع حسابك، ابدأ من صفحة الاختيار.</span>
            </span>
            <span class="login-support-link__icon" aria-hidden="true">
                <x-portal-icon name="dashboard" />
            </span>
        </a>
    </div>
@endsection
