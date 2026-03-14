@extends('layouts.app')
@section('title', 'بوابة الأعمال | الويبهوكات')

@section('content')
<div style="display:grid;gap:24px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div>
            <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
                <a href="{{ route('b2b.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأعمال</a>
                <span style="margin:0 6px">/</span>
                <a href="{{ route('b2b.developer.index') }}" style="color:inherit;text-decoration:none">واجهة المطور</a>
                <span style="margin:0 6px">/</span>
                <span>الويبهوكات</span>
            </div>
            <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">مركز الويبهوكات</h1>
            <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
                راجع نقاط الاستقبال الثابتة وسجل الأحداث الواردة للحساب. تسجيل الويبهوكات على مستوى المتجر ما زال API-only،
                لكن هذه الصفحة تشرح مكان التنفيذ وتعرض ما يصل فعليًا إلى المنصة.
            </p>
        </div>
        <a href="{{ route('b2b.developer.integrations') }}" class="btn btn-ghost">العودة إلى التكاملات</a>
    </div>

    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
        <article class="card">
            <div class="card-title">نقاط الاستقبال العامة</div>
            <div style="display:grid;gap:12px">
                <div style="padding:12px;border:1px solid var(--bd);border-radius:14px">
                    <div style="font-weight:700;color:var(--tx)">Store events</div>
                    <code style="display:block;margin-top:8px;direction:ltr;text-align:left">{{ $baseWebhookUrl }}/{platform}/{storeId}</code>
                </div>
                <div style="padding:12px;border:1px solid var(--bd);border-radius:14px">
                    <div style="font-weight:700;color:var(--tx)">DHL tracking</div>
                    <code style="display:block;margin-top:8px;direction:ltr;text-align:left">{{ $baseWebhookUrl }}/dhl/tracking</code>
                </div>
                <div style="padding:12px;border:1px solid var(--bd);border-radius:14px">
                    <div style="font-weight:700;color:var(--tx)">Tracking fallback</div>
                    <code style="display:block;margin-top:8px;direction:ltr;text-align:left">{{ $baseWebhookUrl }}/track/{trackingNumber}</code>
                </div>
            </div>
        </article>

        <article class="card">
            <div class="card-title">ما الذي ما زال API-only؟</div>
            <div style="display:grid;gap:12px">
                <div style="padding:12px;border:1px solid var(--bd);border-radius:14px;background:#f8fafc">
                    <strong>تسجيل ويبهوكات متجر جديد</strong>
                    <p style="margin:8px 0 0;color:var(--td);line-height:1.8">
                        يتم حاليًا عبر المسار البرمجي:
                        <code>/api/v1/stores/{storeId}/register-webhooks</code>.
                        الهدف من هذه الصفحة هو إعطاء فريق المطورين نقطة انطلاق واضحة بدل البحث في الـAPI يدويًا.
                    </p>
                </div>
                <div style="padding:12px;border:1px solid var(--bd);border-radius:14px;background:#f8fafc">
                    <strong>اختبار التوقيع والتحقق</strong>
                    <p style="margin:8px 0 0;color:var(--td);line-height:1.8">
                        تفاصيل التوقيع والتجارب المتقدمة تبقى API-first حاليًا، لكن سجل الأحداث الظاهر هنا يساعد على التأكد من أن الربط يعمل فعليًا.
                    </p>
                </div>
            </div>
        </article>
    </section>

    <section class="card">
        <div class="card-title">المتاجر القابلة للربط</div>
        @if($stores->isEmpty())
            <div class="empty-state">لا توجد متاجر مرتبطة بهذا الحساب حتى الآن.</div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
                @foreach($stores as $store)
                    <div style="padding:14px;border:1px solid var(--bd);border-radius:16px;background:#fff">
                        <div style="font-weight:700;color:var(--tx)">{{ $store->name }}</div>
                        <div style="font-size:12px;color:var(--td);margin-top:6px">استخدم API store registration لتسجيل ويبهوكات هذا المتجر.</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card">
        <div class="card-title">سجل الأحداث الأخيرة</div>
        <div style="overflow:auto">
            <table class="table">
                <thead>
                <tr>
                    <th>المنصة</th>
                    <th>نوع الحدث</th>
                    <th>المتجر</th>
                    <th>الحالة</th>
                    <th>التوقيت</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentWebhookEvents as $event)
                    <tr>
                        <td>{{ $event->platform }}</td>
                        <td>{{ $event->event_type }}</td>
                        <td>{{ $event->store?->name ?? '—' }}</td>
                        <td>{{ $event->status }}</td>
                        <td>{{ optional($event->created_at)->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-state">لا توجد أحداث ويبهوك واردة لهذا الحساب حتى الآن.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
