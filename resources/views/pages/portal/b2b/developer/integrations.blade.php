@extends('layouts.app')
@section('title', 'بوابة الأعمال | التكاملات')

@section('content')
<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / واجهة المطور / التكاملات"
        title="حالة التكاملات"
        subtitle="قراءة لحالة الربط الحالية بين منظمتك والمنصة. هذه الصفحة تصف تكاملات المنصة المتاحة لك، لا ملكية الناقلين ولا إعداد عقودهم."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        <a href="{{ route('b2b.developer.index') }}" class="btn btn-s">العودة إلى واجهة المطور</a>
    </x-page-header>

    <div class="stats-grid b2b-metrics-grid">
        @foreach($workspaceStats as $stat)
            <x-stat-card
                :iconName="$stat['iconName']"
                :label="$stat['label']"
                :value="$stat['value']"
                :meta="$stat['meta']"
                :eyebrow="$stat['eyebrow']"
            />
        @endforeach
    </div>

    <div class="b2b-dev-grid">
        @foreach($integrations as $integration)
            <article class="b2b-dev-card">
                <div class="b2b-dev-card__header">
                    <div>
                        <div class="b2b-dev-card__title">{{ $integration['name'] }}</div>
                        <div class="b2b-dev-note">{{ $integration['category'] }}</div>
                    </div>
                    <span class="b2b-status-pill b2b-status-pill--{{ in_array($integration['status'], ['healthy', 'unknown'], true) ? 'success' : 'danger' }}">{{ $integration['status_label'] }}</span>
                </div>
                <p class="b2b-dev-card__body">{{ $integration['summary'] }}</p>
                <div class="b2b-dev-note">أنماط النقل: {{ implode('، ', $integration['modes']) }}</div>
                <div class="b2b-dev-note">
                    آخر فحص:
                    {{ $integration['checked_at'] ? $integration['checked_at']->format('Y-m-d H:i') : 'لا يوجد بعد' }}
                    @if($integration['response_time_ms'])
                        • {{ $integration['response_time_ms'] }} ms
                    @endif
                </div>
                @if(auth()->user()->hasPermission('integrations.manage'))
                    <form method="POST" action="{{ route('b2b.developer.integrations.check', $integration['id']) }}">
                        @csrf
                        <button type="submit" class="btn btn-s">تشغيل فحص سريع</button>
                    </form>
                @else
                    <div class="b2b-inline-empty">لديك صلاحية القراءة فقط هنا. اطلب دوراً يسمح بإدارة التكاملات إذا احتجت تشغيل فحص مباشر.</div>
                @endif
            </article>
        @endforeach
    </div>
</div>
@endsection
