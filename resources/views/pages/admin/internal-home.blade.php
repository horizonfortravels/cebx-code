@extends('layouts.app')

@section('title', 'المساحة الداخلية')

@section('content')
    <div style="display:grid;gap:20px">
        <section style="padding:28px;border:1px solid var(--bd);border-radius:24px;background:linear-gradient(135deg,#0f172a,#1e40af);color:#fff">
            <div style="font-size:13px;opacity:.85;margin-bottom:10px">الداخلية / الصفحة الرئيسية</div>
            <h1 style="margin:0 0 12px;font-size:32px">المساحة الداخلية</h1>
            <p style="margin:0;max-width:760px;line-height:1.9;color:rgba(255,255,255,.9)">
                هذه الصفحة مخصصة للمستخدمين الداخليين الذين لا يحتاجون إلى لوحة الإدارة الكاملة في كل مرة.
                ستجد هنا ما يمكن لدورك الوصول إليه بوضوح، بدل الوصول إلى صفحات تنتهي بمنع غير مفسر.
            </p>
        </section>

        <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
            <article style="padding:20px;border:1px solid var(--bd);border-radius:20px;background:#fff">
                <div style="font-size:12px;color:var(--td);margin-bottom:8px">الدور الداخلي المعتمد</div>
                <h2 style="margin:0 0 8px;font-size:22px">{{ $roleProfile['label'] }}</h2>
                <p style="margin:0;color:var(--td);line-height:1.8">{{ $roleProfile['description'] }}</p>
            </article>

            <article style="padding:20px;border:1px solid var(--bd);border-radius:20px;background:#fff">
                <div style="font-size:12px;color:var(--td);margin-bottom:8px">سياق العمل الحالي</div>
                @if($selectedAccount)
                    <h2 style="margin:0 0 8px;font-size:22px">{{ $selectedAccount->name }}</h2>
                    <p style="margin:0;color:var(--td);line-height:1.8">
                        النوع: {{ $selectedAccount->type === 'organization' ? 'منظمة' : 'فردي' }}.
                        أي صفحات مرتبطة بعميل ستستخدم هذا الحساب ما دام محددًا في الجلسة الحالية.
                    </p>
                @else
                    <h2 style="margin:0 0 8px;font-size:22px">لا يوجد حساب محدد</h2>
                    <p style="margin:0;color:var(--td);line-height:1.8">
                        إذا كان دورك يحتاج مراجعة بيانات عميل معين، اختر الحساب أولًا من أداة اختيار السياق.
                    </p>
                @endif
            </article>

            <article style="padding:20px;border:1px solid var(--bd);border-radius:20px;background:#fff">
                <div style="font-size:12px;color:var(--td);margin-bottom:8px">الوصول المتاح الآن</div>
                <ul style="margin:0;padding-right:18px;line-height:2;color:var(--tx)">
                    <li>{{ $capabilities['tenantContext'] ? 'يمكنك اختيار حساب عميل عند الحاجة.' : 'لا يتوفر لك اختيار سياق الحساب حاليًا.' }}</li>
                    <li>{{ $capabilities['ticketsRead'] || $capabilities['ticketsManage'] ? 'لديك وصول مرتبط بالدعم والتذاكر.' : 'لا توجد مهام دعم مفعلة لدورك الحالي.' }}</li>
                    <li>{{ $capabilities['reportsRead'] ? 'يمكنك الوصول إلى تقارير القراءة المسموح بها لدورك.' : 'لا توجد تقارير مفعلة لدورك الحالي.' }}</li>
                    <li>{{ $capabilities['analyticsRead'] ? 'يمكنك عرض مؤشرات وتحليلات تشغيلية للقراءة فقط.' : 'لا توجد تحليلات مفعلة لدورك الحالي.' }}</li>
                </ul>
            </article>
        </section>

        @if($hasDeprecatedRoleAssignments)
            <section style="padding:18px 20px;border:1px solid #f59e0b;border-radius:18px;background:#fffbeb;color:#92400e">
                تم إخفاء الدور الداخلي القديم من الواجهة النشطة. اطلب من مسؤول المنصة إعادة تعيينه إلى أحد الأدوار الداخلية المعتمدة.
            </section>
        @endif

        <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
            @if($surfaces['tenantContext'])
                <article style="padding:22px;border:1px solid var(--bd);border-radius:20px;background:#fff">
                    <div style="font-size:12px;color:var(--td);margin-bottom:8px">الخطوة التالية</div>
                    <h2 style="margin:0 0 10px;font-size:22px">اختيار الحساب</h2>
                    <p style="margin:0 0 18px;color:var(--td);line-height:1.8">
                        اختر حسابًا عندما تحتاج فحص بيانات عميل أو تنفيذ عملية داخلية مرتبطة بعميل محدد.
                    </p>
                    <a href="{{ route('internal.tenant-context') }}" style="display:inline-flex;padding:12px 18px;border-radius:14px;background:#1d4ed8;color:#fff;text-decoration:none;font-weight:700">
                        فتح أداة اختيار الحساب
                    </a>
                </article>
            @endif

            @if($surfaces['smtpSettings'])
                <article style="padding:22px;border:1px solid var(--bd);border-radius:20px;background:#fff">
                    <div style="font-size:12px;color:var(--td);margin-bottom:8px">إدارة الناقلين والمنصة</div>
                    <h2 style="margin:0 0 10px;font-size:22px">إعدادات SMTP</h2>
                    <p style="margin:0 0 18px;color:var(--td);line-height:1.8">
                        هذه الواجهة متاحة ضمن الدور الداخلي المعتمد لإدارة إعدادات البريد الداخلي والاختبارات المرتبطة به.
                    </p>
                    <a href="{{ route('internal.smtp-settings.edit') }}" style="display:inline-flex;padding:12px 18px;border-radius:14px;background:#0f766e;color:#fff;text-decoration:none;font-weight:700">
                        فتح إعدادات SMTP
                    </a>
                </article>
            @endif

            @if($surfaces['adminDashboard'])
                <article style="padding:22px;border:1px solid var(--bd);border-radius:20px;background:#fff">
                    <div style="font-size:12px;color:var(--td);margin-bottom:8px">إدارة موسعة</div>
                    <h2 style="margin:0 0 10px;font-size:22px">لوحة الإدارة الكاملة</h2>
                    <p style="margin:0 0 18px;color:var(--td);line-height:1.8">
                        لديك صلاحية الدخول إلى لوحة الإدارة الموسعة ومتابعة صفحات المنصة والحسابات المحددة.
                    </p>
                    <a href="{{ route('admin.index') }}" style="display:inline-flex;padding:12px 18px;border-radius:14px;background:#0f172a;color:#fff;text-decoration:none;font-weight:700">
                        فتح لوحة الإدارة
                    </a>
                </article>
            @endif

            <article style="padding:22px;border:1px solid var(--bd);border-radius:20px;background:#fff">
                <div style="font-size:12px;color:var(--td);margin-bottom:8px">ماذا لو احتجت أكثر؟</div>
                <h2 style="margin:0 0 10px;font-size:22px">ترقية الصلاحيات</h2>
                <p style="margin:0;color:var(--td);line-height:1.8">
                    إذا كانت مهمتك اليومية تحتاج صفحات إضافية غير ظاهرة هنا، اطلب من مسؤول المنصة منح الدور المناسب بدل استخدام مسارات غير مخصصة لدورك.
                </p>
            </article>
        </section>
    </div>
@endsection
