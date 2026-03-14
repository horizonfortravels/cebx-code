@extends('layouts.app')
@section('title', 'بوابة الأعمال | التكاملات')

@section('content')
<div style="display:grid;gap:24px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div>
            <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
                <a href="{{ route('b2b.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأعمال</a>
                <span style="margin:0 6px">/</span>
                <a href="{{ route('b2b.developer.index') }}" style="color:inherit;text-decoration:none">واجهة المطور</a>
                <span style="margin:0 6px">/</span>
                <span>التكاملات</span>
            </div>
            <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">حالة التكاملات</h1>
            <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
                هذه الصفحة تعرض نبضة الربط الحالية للحساب <strong>{{ $account->name }}</strong>.
                يمكنك تشغيل فحص سريع للخدمات التي تملك صلاحية إدارتها، بينما إعداد الربط التفصيلي يبقى كما هو في واجهات API الحالية.
            </p>
        </div>
        <a href="{{ route('b2b.developer.webhooks') }}" class="btn btn-ghost">الانتقال إلى الويبهوكات</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px">
        @foreach($integrations as $integration)
            <article class="card">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:10px">
                    <div>
                        <div class="card-title">{{ $integration['name'] }}</div>
                        <div style="color:var(--td);font-size:13px">{{ $integration['category'] }}</div>
                    </div>
                    <span style="padding:5px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700">{{ $integration['status_label'] }}</span>
                </div>
                <p style="margin:0 0 14px;color:var(--td);line-height:1.8">{{ $integration['summary'] }}</p>
                <div style="font-size:13px;color:var(--td);margin-bottom:10px">أنماط النقل: {{ implode('، ', $integration['modes']) }}</div>
                <div style="font-size:13px;color:var(--td);margin-bottom:16px">
                    آخر فحص:
                    {{ $integration['checked_at'] ? $integration['checked_at']->format('Y-m-d H:i') : 'لا يوجد بعد' }}
                    @if($integration['response_time_ms'])
                        • {{ $integration['response_time_ms'] }} ms
                    @endif
                </div>
                @if(auth()->user()->hasPermission('integrations.manage'))
                    <form method="POST" action="{{ route('b2b.developer.integrations.check', $integration['id']) }}">
                        @csrf
                        <button type="submit" class="btn btn-pr">تشغيل فحص سريع</button>
                    </form>
                @else
                    <div class="empty-state" style="text-align:right">
                        لديك صلاحية القراءة فقط هنا. إذا احتجت فحصًا مباشرًا فاطلب دورًا يسمح بإدارة التكاملات.
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</div>
@endsection
