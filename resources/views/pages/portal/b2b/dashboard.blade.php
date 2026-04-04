@extends('layouts.app')
@section('title', 'بوابة الأعمال | الرئيسية')

@section('content')
<div style="display:grid;gap:24px">
    <section style="padding:28px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff">
        <div style="font-size:12px;opacity:.82;margin-bottom:8px">بوابة الأعمال / الرئيسية</div>
        <h1 style="margin:0 0 10px;font-size:30px">مركز تشغيل حساب المنظمة</h1>
        <p style="margin:0;max-width:760px;line-height:1.9;color:rgba(255,255,255,.9)">
            هذه الصفحة تجمع ما يحتاجه فريق المنظمة {{ $account->name }} يوميًا: الشحنات، الطلبات، المستخدمون، والتقارير عبر شبكة الناقلين التابعة للمنصة.
            @if($developerTools->isNotEmpty())
                وإذا كان لديك دور تكاملات أو API فستجد هنا أدوات تكامل المنصة بشكل واضح بدل الاعتماد على المسارات البرمجية فقط.
            @endif
        </p>
    </section>

    <section class="stats-grid">
        @foreach($stats as $stat)
            <x-stat-card :icon="$stat['icon']" :label="$stat['label']" :value="$stat['value']" />
        @endforeach
    </section>

    <section class="grid-auto-220" style="gap:18px">
        @foreach([
            ['title' => 'الشحنات', 'desc' => 'متابعة الشحنات الجارية وأولوية العمل اليومي.', 'route' => 'b2b.shipments.index', 'cta' => 'فتح الشحنات'],
            ['title' => 'الطلبات', 'desc' => 'مراجعة الطلبات الواردة قبل الشحن أو المزامنة.', 'route' => 'b2b.orders.index', 'cta' => 'فتح الطلبات'],
            ['title' => 'المحفظة', 'desc' => 'الرصيد والحركات والجاهزية المالية السريعة.', 'route' => 'b2b.wallet.index', 'cta' => 'فتح المحفظة'],
            ['title' => 'التقارير', 'desc' => 'ملخص تنفيذي سريع قبل الانتقال للتفاصيل الكاملة.', 'route' => 'b2b.reports.index', 'cta' => 'فتح التقارير'],
        ] as $card)
            <article class="card">
                <div class="card-title">{{ $card['title'] }}</div>
                <p style="margin:0 0 16px;color:var(--td);line-height:1.8">{{ $card['desc'] }}</p>
                <a href="{{ route($card['route']) }}" class="btn btn-pr">{{ $card['cta'] }}</a>
            </article>
        @endforeach
    </section>

    @if($developerTools->isNotEmpty())
        @php($developerEntryRoute = $developerTools->first()['route'] ?? 'b2b.developer.index')
        <section class="card">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px">
                <div>
                    <div style="font-size:12px;color:var(--tm);margin-bottom:6px">أدوات المطور</div>
                    <h2 style="margin:0;font-size:24px;color:var(--tx)">مسار واضح لفريق التكامل</h2>
                    <p style="margin:8px 0 0;color:var(--td);line-height:1.8;max-width:760px">
                        هذه الأدوات تظهر فقط للأدوار التي تملك صلاحيات التكاملات أو مفاتيح API أو الويبهوكات.
                        هي مخصصة لتكامل المنظمة مع المنصة فقط، ولا تعني ملكية تكاملات الناقلين أو عقودهم. بعض الإجراءات المتقدمة ما زالت API-only، لكنك ستجد من هنا أين تبدأ وماذا يمكنك إنجازه من المتصفح.
                    </p>
                </div>
                <a href="{{ route($developerEntryRoute) }}" class="btn btn-pr">فتح واجهة المطور</a>
            </div>

            <div class="grid-auto-220">
                @foreach($developerTools as $tool)
                    <article style="padding:18px;border:1px solid var(--bd);border-radius:18px;background:#fff">
                        <div style="font-weight:700;color:var(--tx);margin-bottom:8px">{{ $tool['label'] }}</div>
                        <p style="margin:0 0 14px;color:var(--td);line-height:1.8">{{ $tool['description'] }}</p>
                        <a href="{{ route($tool['route']) }}" class="btn btn-ghost">الانتقال</a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
