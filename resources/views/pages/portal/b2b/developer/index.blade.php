@extends('layouts.app')
@section('title', 'بوابة الأعمال | واجهة المطور')

@section('content')
<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / أدوات المطور"
        title="واجهة المطور"
        subtitle="هذه المساحة تجمع أدوات التكامل الخاصة بالمنظمة مع المنصة: حالة الربط، مفاتيح API، وسجل الويبهوكات، من دون الإيحاء بملكية تكاملات الناقلين أو إعدادها."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        @if($developerTools->isNotEmpty())
            <a href="{{ route($developerTools->first()['route']) }}" class="btn btn-s">فتح أول أداة متاحة</a>
        @endif
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
        @foreach($developerTools as $tool)
            <a href="{{ route($tool['route']) }}" class="b2b-dev-card">
                <div class="b2b-dev-card__title">{{ $tool['label'] }}</div>
                <p class="b2b-dev-card__body">{{ $tool['description'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="b2b-workspace-grid">
        <section class="b2b-panel-stack">
            <x-card title="أحدث مفاتيح API">
                <div class="b2b-mini-stack">
                    @forelse($recentApiKeys as $key)
                        <div class="b2b-mini-stack__item">
                            <div>
                                <div class="b2b-mini-stack__title">{{ $key->name }}</div>
                                <div class="b2b-mini-stack__meta td-mono">{{ $key->key_prefix }}...</div>
                            </div>
                            <div class="b2b-mini-stack__value">{{ $key->is_active ? 'نشط' : 'ملغي' }}</div>
                        </div>
                    @empty
                        <div class="b2b-inline-empty">لا توجد مفاتيح API حالية للمستخدم الحالي. أنشئ مفتاحاً جديداً عند الحاجة لربط أنظمتك الداخلية بالمنصة.</div>
                    @endforelse
                </div>
            </x-card>

            <x-card title="نبضة التكاملات">
                <div class="b2b-dev-grid b2b-dev-grid--compact">
                    @foreach($integrationSummaries as $integration)
                        <article class="b2b-dev-card">
                            <div class="b2b-dev-card__title">{{ $integration['name'] }}</div>
                            <p class="b2b-dev-card__body">{{ $integration['summary'] }}</p>
                            <div class="b2b-dev-note">{{ $integration['status_label'] }}</div>
                        </article>
                    @endforeach
                </div>
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="سجل ويبهوكات سريع">
                <div class="b2b-mini-stack">
                    @forelse($recentWebhookEvents as $event)
                        <div class="b2b-mini-stack__item">
                            <div>
                                <div class="b2b-mini-stack__title">{{ $event->platform }} / {{ $event->event_type }}</div>
                                <div class="b2b-mini-stack__meta">{{ $event->store?->name ?? 'متجر غير محدد' }}</div>
                            </div>
                            <div class="b2b-mini-stack__value">{{ optional($event->created_at)->format('Y-m-d H:i') ?? '—' }}</div>
                        </div>
                    @empty
                        <div class="b2b-inline-empty">لم تصل أحداث ويبهوك بعد لهذا الحساب. عند تفعيل الربط ستظهر هنا أول الإشارات التشغيلية.</div>
                    @endforelse
                </div>
            </x-card>
        </aside>
    </div>
</div>
@endsection
