<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختيار البوابة المناسبة - بوابة الشحن CBEX</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @include('components.pwa-meta')
    <meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
</head>
<body class="entry-page">
    <main class="entry-shell">
        <section class="entry-hero">
            <div class="entry-hero__grid">
                <div class="entry-hero__facts">
                    <span class="entry-hero__eyebrow">بوابات واضحة بحسب نوع الحساب</span>
                    <h1 class="entry-hero__title">اختر المسار المناسب قبل تسجيل الدخول</h1>
                    <p class="entry-hero__copy">
                        تستخدم CBEX ثلاث بوابات مختلفة حتى تبقى التجربة واضحة وآمنة: بوابة الأفراد للحسابات الفردية،
                        وبوابة الأعمال لحسابات المنظمات، والبوابة الداخلية لفريق المنصة. اختيار الباب الصحيح من البداية
                        يساعدك على الوصول مباشرة إلى الأدوات المناسبة دون التباس أو تحويلات مزعجة.
                    </p>
                </div>

                <div class="entry-facts-card">
                    <h2 class="entry-facts-card__title">متى أختار كل بوابة؟</h2>
                    <p class="entry-facts-card__body">
                        إذا كان حسابك شخصيًا فابدأ من بوابة الأفراد. إذا كنت تعمل ضمن منظمة أو فريق فابدأ من بوابة الأعمال.
                        أما إذا كنت من فريق التشغيل أو الإدارة داخل CBEX فاستخدم البوابة الداخلية فقط.
                    </p>
                </div>
            </div>
        </section>

        <section class="entry-grid" aria-label="اختيار البوابة">
            <a href="{{ route('b2c.login') }}" class="entry-card entry-card--b2c">
                <div class="entry-card__top">
                    <span class="entry-card__icon" aria-hidden="true">
                        <x-portal-icon name="individual" />
                    </span>
                    <span class="entry-card__badge">B2C</span>
                </div>

                <div>
                    <h2 class="entry-card__title">بوابة الأفراد</h2>
                    <p class="entry-card__subtitle">للحسابات الفردية الخارجية فقط</p>
                </div>

                <p class="entry-card__body">
                    تجربة شخصية هادئة لإدارة الشحنات الفردية، متابعة الحالة، الوصول إلى المحفظة،
                    وحفظ العناوين المتكررة من واجهة واحدة واضحة.
                </p>

                <ul class="entry-card__list">
                    <li>إنشاء شحنة ومتابعتها خطوة بخطوة</li>
                    <li>عرض الشحنات النشطة والتسليمات الأخيرة</li>
                    <li>الوصول إلى المحفظة والعناوين والدعم</li>
                </ul>

                <span class="entry-card__cta">الدخول إلى بوابة الأفراد</span>
            </a>

            <a href="{{ route('b2b.login') }}" class="entry-card entry-card--b2b">
                <div class="entry-card__top">
                    <span class="entry-card__icon" aria-hidden="true">
                        <x-portal-icon name="organization" />
                    </span>
                    <span class="entry-card__badge">B2B</span>
                </div>

                <div>
                    <h2 class="entry-card__title">بوابة الأعمال</h2>
                    <p class="entry-card__subtitle">لحسابات المنظمات والفرق الخارجية</p>
                </div>

                <p class="entry-card__body">
                    مساحة عمل تشغيلية للمنظمة تشمل الشحنات والطلبات والمحفظة والتقارير،
                    مع إتاحة أدوات التكامل الخاصة بالمنصة عندما يسمح الدور بذلك.
                </p>

                <ul class="entry-card__list">
                    <li>إدارة شحنات المنظمة والطلبات من مساحة واحدة</li>
                    <li>متابعة الفريق والأدوار والصلاحيات المسموح بها</li>
                    <li>الوصول إلى التقارير وأدوات التكامل الخاصة بالمنصة</li>
                </ul>

                <span class="entry-card__cta">الدخول إلى بوابة الأعمال</span>
            </a>

            <a href="{{ route('admin.login') }}" class="entry-card entry-card--admin">
                <div class="entry-card__top">
                    <span class="entry-card__icon" aria-hidden="true">
                        <x-portal-icon name="admin" />
                    </span>
                    <span class="entry-card__badge">Internal</span>
                </div>

                <div>
                    <h2 class="entry-card__title">البوابة الداخلية</h2>
                    <p class="entry-card__subtitle">مخصصة لفريق CBEX الداخلي فقط</p>
                </div>

                <p class="entry-card__body">
                    هذه البوابة خاصة بفرق التشغيل والدعم والإدارة داخل المنصة،
                    وليست بديلًا عن بوابتي الأفراد أو الأعمال للحسابات الخارجية.
                </p>

                <ul class="entry-card__list">
                    <li>متابعة العمليات الداخلية وسياقات الحسابات</li>
                    <li>لوحات تشغيل ومراكز قراءة خاصة بفريق المنصة</li>
                    <li>لا تستخدم هذه البوابة إذا كان حسابك خارجيًا</li>
                </ul>

                <span class="entry-card__cta">فتح البوابة الداخلية</span>
            </a>
        </section>

        <footer class="entry-footer">
            إذا لم تكن متأكدًا من نوع حسابك، ابدأ من البوابة التي تتوافق مع طبيعة حسابك الحالية داخل المنصة.
            <a href="{{ url('/') }}">العودة إلى بوابة الشحن</a>
        </footer>
    </main>

    <script>window.PWA={swUrl:'{{ asset("sw.js") }}',scope:'{{ rtrim(url("/"), "/") }}/'};</script>
    <script src="{{ asset('js/pwa.js') }}" defer></script>
</body>
</html>
