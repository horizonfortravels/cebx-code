@extends('layouts.app')
@section('title', 'بوابة الأعمال | واجهة المطور')

@section('content')
<div style="display:grid;gap:24px">
    <section style="padding:28px;border-radius:24px;background:linear-gradient(135deg,#111827,#0f766e);color:#fff">
        <div style="font-size:12px;opacity:.82;margin-bottom:8px">بوابة الأعمال / أدوات المطور</div>
        <h1 style="margin:0 0 10px;font-size:30px">واجهة المطور</h1>
        <p style="margin:0;max-width:760px;line-height:1.9;color:rgba(255,255,255,.9)">
            هذه المساحة تجمع ما يحتاجه فريق التكامل داخل المتصفح: حالة الربط، مفاتيح API، وإعدادات الويبهوكات.
            وإذا بقي إجراء معيّن عبر API فقط فسيظهر هنا بوضوح مع توجيه عملي بدل الغموض.
        </p>
    </section>

    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px">
        @foreach($developerTools as $tool)
            <article class="card">
                <div class="card-title">{{ $tool['label'] }}</div>
                <p style="margin:0 0 16px;color:var(--td);line-height:1.8">{{ $tool['description'] }}</p>
                <a href="{{ route($tool['route']) }}" class="btn btn-pr">فتح الصفحة</a>
            </article>
        @endforeach
    </section>

    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px">
        <article class="card">
            <div class="card-title">أحدث مفاتيحك</div>
            @if($recentApiKeys->isEmpty())
                <div class="empty-state">لا توجد مفاتيح API حالية لهذا المستخدم. يمكنك إنشاء مفتاح جديد من صفحة المفاتيح.</div>
            @else
                <div style="display:flex;flex-direction:column;gap:12px">
                    @foreach($recentApiKeys as $key)
                        <div style="padding:14px;border:1px solid var(--bd);border-radius:14px">
                            <div style="font-weight:700;color:var(--tx)">{{ $key->name }}</div>
                            <div class="td-mono" style="margin-top:4px">{{ $key->key_prefix }}…</div>
                            <div style="margin-top:6px;color:var(--td);font-size:13px">{{ $key->is_active ? 'نشط' : 'ملغى' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="card">
            <div class="card-title">سجل ويبهوكات سريع</div>
            @if($recentWebhookEvents->isEmpty())
                <div class="empty-state">لم تصل أحداث ويبهوك بعد لهذا الحساب. عند تفعيل الربط من المتاجر أو الشركاء ستظهر هنا.</div>
            @else
                <div style="display:flex;flex-direction:column;gap:12px">
                    @foreach($recentWebhookEvents as $event)
                        <div style="padding:14px;border:1px solid var(--bd);border-radius:14px">
                            <div style="font-weight:700;color:var(--tx)">{{ $event->platform }} / {{ $event->event_type }}</div>
                            <div style="margin-top:4px;color:var(--td);font-size:13px">{{ $event->store?->name ?? 'متجر غير معروف' }}</div>
                            <div style="margin-top:6px;color:var(--td);font-size:12px">{{ optional($event->created_at)->format('Y-m-d H:i') }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>

    <section class="card">
        <div class="card-title">نبضة الربط الحالية</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
            @foreach($integrationSummaries as $integration)
                <article style="padding:18px;border:1px solid var(--bd);border-radius:18px;background:#fff">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center">
                        <div style="font-weight:700;color:var(--tx)">{{ $integration['name'] }}</div>
                        <span style="padding:4px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700">{{ $integration['status_label'] }}</span>
                    </div>
                    <p style="margin:10px 0 0;color:var(--td);line-height:1.8">{{ $integration['summary'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
</div>
@endsection
