@extends('layouts.app')
@section('title', 'بوابة الأعمال | الإعدادات')

@section('content')
<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / الإعدادات"
        title="إعدادات حساب المنظمة"
        subtitle="هذه المساحة تجمع الحالة الحالية للحساب وتوضح ما الذي يمكن مراجعته من البوابة الآن، وما الذي يبقى مرتبطاً بإدارة الصلاحيات أو دعم المنصة."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        <a href="{{ route('b2b.dashboard') }}" class="btn btn-s">العودة إلى الرئيسية</a>
    </x-page-header>

    <div class="b2b-workspace-grid">
        <section class="b2b-panel-stack">
            <x-card title="ملخص الحالة الحالية">
                <div class="b2b-guidance-list">
                    <div class="b2b-guidance-card">
                        <strong>بوابة الأعمال أصبحت المسار الأساسي</strong>
                        <p>تم نقل هذه الصفحة إلى نفس لغة الواجهة الخارجية الجديدة حتى لا يعود المستخدم إلى شاشة قديمة أو منفصلة عن بقية مساحة العمل.</p>
                    </div>
                    <div class="b2b-guidance-card">
                        <strong>الإعدادات الحساسة تبقى خاضعة للصلاحيات</strong>
                        <p>إدارة المستخدمين والأدوار وأدوات التكامل المالية أو التقنية تبقى ضمن الصفحات المخصصة لها وبحسب الصلاحيات الممنوحة لدورك الحالي.</p>
                    </div>
                    <div class="b2b-guidance-card">
                        <strong>استخدم المراكز المتخصصة عند الحاجة</strong>
                        <p>إن كنت تراجع الفريق أو الصلاحيات أو الصورة المالية للحساب، فستحصل على قراءة أوضح من المساحات المخصصة لذلك داخل بوابة الأعمال.</p>
                    </div>
                </div>
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="خطوات مقترحة">
                <div class="b2b-tool-list">
                    <a href="{{ route('b2b.dashboard') }}" class="b2b-tool-link">
                        <span>العودة إلى مركز العمليات</span>
                        <small>ابدأ من لوحة العمل لمراجعة الشحنات والطلبات والحالة التشغيلية الحالية.</small>
                    </a>
                    <a href="{{ route('b2b.addresses.index') }}" class="b2b-tool-link">
                        <span>مراجعة العناوين</span>
                        <small>حدّث سجل العناوين المحفوظة قبل إنشاء شحنات جديدة أو مشاركة الحساب مع الفريق.</small>
                    </a>
                </div>
            </x-card>
        </aside>
    </div>
</div>
@endsection
