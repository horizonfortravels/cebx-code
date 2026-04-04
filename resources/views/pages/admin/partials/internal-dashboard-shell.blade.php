@php
    $iconMap = [
        'accounts' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4.75 8.25 12 4.5l7.25 3.75"></path><path d="M6.5 9.25V18"></path><path d="M12 9.25V18"></path><path d="M17.5 9.25V18"></path><path d="M4.75 19.5h14.5"></path></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z"></path><path d="M5.75 19.25a6.25 6.25 0 0 1 12.5 0"></path></svg>',
        'shipments' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4.75 8.5 12 4.75l7.25 3.75v7L12 19.25l-7.25-3.75z"></path><path d="M12 10.25 4.75 6.5"></path><path d="M12 10.25 19.25 6.5"></path><path d="M12 10.25v9"></path></svg>',
        'activity' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4.75 12h3.5l2-4.25 3.5 8.5 2.25-4.25h4.25"></path></svg>',
        'alert' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m12 4.75 7 12.5a1 1 0 0 1-.87 1.5H5.87a1 1 0 0 1-.87-1.5z"></path><path d="M12 9v4.5"></path><path d="M12 16.5h.01"></path></svg>',
        'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"></circle><path d="m8.5 12 2.25 2.25L15.5 9.5"></path></svg>',
        'kyc' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4.75 18 7v4.75c0 3.46-2.27 6.59-6 7.5-3.73-.91-6-4.04-6-7.5V7z"></path><path d="m9.5 12 1.75 1.75L14.75 10"></path></svg>',
        'tickets' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5.75 7.25A2.5 2.5 0 0 1 8.25 4.75h7.5a2.5 2.5 0 0 1 2.5 2.5v3a1.75 1.75 0 0 0 0 3.5v3a2.5 2.5 0 0 1-2.5 2.5h-7.5a2.5 2.5 0 0 1-2.5-2.5v-3a1.75 1.75 0 0 0 0-3.5z"></path><path d="M9.5 9.5h5"></path><path d="M9.5 14.5h5"></path></svg>',
        'integrations' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7.5a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z"></path><path d="M20.5 7.5A2.75 2.75 0 1 1 15 7.5a2.75 2.75 0 0 1 5.5 0Z"></path><path d="M14.25 16.5a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z"></path><path d="m8.2 9.5 2.4 4"></path><path d="m15.8 9.5-2.4 4"></path></svg>',
        'billing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4.75 8.5A2.75 2.75 0 0 1 7.5 5.75h9A2.75 2.75 0 0 1 19.25 8.5v7A2.75 2.75 0 0 1 16.5 18.25h-9A2.75 2.75 0 0 1 4.75 15.5z"></path><path d="M16.5 11.25h2.75v2.5H16.5a1.25 1.25 0 1 1 0-2.5Z"></path></svg>',
        'reports' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5.25 19.25h13.5"></path><path d="M8 17v-4.5"></path><path d="M12 17V8"></path><path d="M16 17v-6.5"></path></svg>',
        'carriers' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4.75 7.25h9.5v7.5h-9.5z"></path><path d="M14.25 10.25h3l2 2v2.5h-5"></path><circle cx="8" cy="17.5" r="1.5"></circle><circle cx="17" cy="17.5" r="1.5"></circle></svg>',
        'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.75" y="6" width="16.5" height="12" rx="2"></rect><path d="m5.5 7.75 6.5 5 6.5-5"></path></svg>',
        'roles' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4.75 18 7v4.75c0 3.46-2.27 6.59-6 7.5-3.73-.91-6-4.04-6-7.5V7z"></path><path d="M9.5 12h5"></path></svg>',
        'context' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7.5h10.5"></path><path d="m12.5 4.5 3 3-3 3"></path><path d="M19 16.5H8.5"></path><path d="m11.5 13.5-3 3 3 3"></path></svg>',
    ];

    $toneBadge = static fn (?string $tone): string => match ($tone) {
        'success' => 'badge-ac',
        'warning' => 'badge-wn',
        'danger' => 'badge-dg',
        'info' => 'badge-pr',
        default => 'badge-td',
    };

    $tonePanel = static fn (?string $tone): string => match ($tone) {
        'success' => 'dashboard-surface--success',
        'warning' => 'dashboard-surface--warning',
        'danger' => 'dashboard-surface--danger',
        'info' => 'dashboard-surface--info',
        default => 'dashboard-surface--neutral',
    };

    $icon = static fn (?string $name): string => $iconMap[$name] ?? $iconMap['activity'];

    $iconForKpi = static function (array $kpi): string {
        $label = (string) ($kpi['label'] ?? '');

        return match (true) {
            str_contains($label, 'الحساب') => 'accounts',
            str_contains($label, 'مستخدم') => 'users',
            str_contains($label, 'KYC'), str_contains($label, 'التحقق') => 'kyc',
            str_contains($label, 'تذاكر') => 'tickets',
            str_contains($label, 'محافظ'), str_contains($label, 'رصيد'), str_contains($label, 'فوترة') => 'billing',
            str_contains($label, 'تكامل'), str_contains($label, 'ناقل') => 'integrations',
            str_contains($label, 'استثن'), str_contains($label, 'تحتاج متابعة') => 'alert',
            str_contains($label, 'شحن') => 'shipments',
            default => 'activity',
        };
    };

    $iconForAction = static function (array $action): string {
        $label = (string) ($action['label'] ?? '');
        $href = (string) ($action['href'] ?? '');

        return match (true) {
            str_contains($href, 'tenant-context') || str_contains($label, 'الحساب') => 'context',
            str_contains($href, 'users') || str_contains($label, 'مستخدم') => 'users',
            str_contains($href, 'roles') || str_contains($label, 'أدوار') => 'roles',
            str_contains($href, 'reports') || str_contains($label, 'تقارير') => 'reports',
            str_contains($href, 'shipments') || str_contains($label, 'شحن') => 'shipments',
            str_contains($href, 'tickets') || str_contains($label, 'تذاكر') => 'tickets',
            str_contains($href, 'kyc') || str_contains($label, 'تحقق') => 'kyc',
            str_contains($href, 'integrations') || str_contains($label, 'تكامل') => 'integrations',
            str_contains($href, 'carriers') || str_contains($label, 'ناقل') => 'carriers',
            str_contains($href, 'smtp') => 'mail',
            str_contains($href, 'staff') || str_contains($label, 'فريق') => 'users',
            default => 'activity',
        };
    };

    $buttonClass = static fn (?string $variant): string => match ($variant) {
        'primary' => 'btn btn-pr',
        'disabled' => 'btn btn-s dashboard-button-disabled',
        default => 'btn btn-s',
    };

    $withTrendHeights = static function (array $points): array {
        $max = max(1, (int) collect($points)->max('value'));

        return collect($points)->map(static function (array $point) use ($max): array {
            $value = (int) ($point['value'] ?? 0);

            return $point + [
                'display' => number_format($value),
                'height' => $value > 0 ? max(10, (int) round(($value / $max) * 100)) : 0,
            ];
        })->all();
    };

    $dashboard = $dashboard ?? [];
    $mainChart = $dashboard['main_chart'] ?? null;
    $kpis = $dashboard['kpis'] ?? [];
    $pills = $dashboard['pills'] ?? [];
    $heroActions = $dashboard['hero_actions'] ?? [];
    $chartCards = $dashboard['chart_cards'] ?? [];
    $streamCards = $dashboard['stream_cards'] ?? [];
    $sideCards = $dashboard['side_cards'] ?? [];
@endphp

<div class="internal-dashboard-shell">
    <section class="internal-dashboard-hero">
        <div class="internal-dashboard-hero__copy">
            <div class="internal-dashboard-eyebrow">{{ $dashboard['eyebrow'] ?? '' }}</div>
            <h1 class="internal-dashboard-title">{{ $dashboard['title'] ?? '' }}</h1>
            <p class="internal-dashboard-description">{{ $dashboard['description'] ?? '' }}</p>

            @if(!empty($pills))
                <div class="internal-dashboard-pill-row">
                    @foreach($pills as $pill)
                        <span class="badge {{ $toneBadge($pill['tone'] ?? 'neutral') }}">{{ $pill['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="internal-dashboard-hero__aside">
            <div class="internal-dashboard-aside-label">أقرب المسارات المتاحة</div>
            <p class="internal-dashboard-role-copy">
                تظهر هنا الخطوات الأكثر ارتباطًا بالدور الحالي فقط، مع الحفاظ على حدود الوصول وسياق الحساب كما هو معتمد داخل المنصة.
            </p>

            @if(!empty($heroActions))
                <div class="dashboard-hero-action-list">
                    @foreach($heroActions as $action)
                        @if(!empty($action['href']))
                            <a href="{{ $action['href'] }}" class="{{ $buttonClass($action['variant'] ?? null) }} dashboard-hero-action">
                                <span class="dashboard-hero-action__icon">{!! $icon($iconForAction($action)) !!}</span>
                                <span class="dashboard-hero-action__body">
                                    <span class="dashboard-hero-action__title">{{ $action['label'] }}</span>
                                    <span class="dashboard-hero-action__copy">{{ $action['description'] }}</span>
                                </span>
                            </a>
                        @else
                            <div class="{{ $buttonClass($action['variant'] ?? 'disabled') }} dashboard-hero-action">
                                <span class="dashboard-hero-action__icon">{!! $icon($iconForAction($action)) !!}</span>
                                <span class="dashboard-hero-action__body">
                                    <span class="dashboard-hero-action__title">{{ $action['label'] }}</span>
                                    <span class="dashboard-hero-action__copy">{{ $action['description'] }}</span>
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="dashboard-empty-state dashboard-empty-state--soft">
                    ستظهر المسارات السريعة هنا فور توفر صفحات مناسبة للدور الحالي.
                </div>
            @endif
        </div>
    </section>

    @if(!empty($kpis))
        <div class="stats-grid internal-dashboard-kpis">
            @foreach($kpis as $kpi)
                <x-stat-card
                    class="internal-kpi-card"
                    :label="$kpi['label']"
                    :value="$kpi['display']"
                    :meta="$kpi['hint'] ?? null"
                    :iconSvg="$icon($iconForKpi($kpi))"
                />
            @endforeach
        </div>
    @endif

    <div class="internal-dashboard-grid">
        <div class="internal-dashboard-main">
            @if(!empty($mainChart))
                @php
                    $mainChartPoints = $withTrendHeights($mainChart['points'] ?? []);
                    $mainChartTotal = collect($mainChartPoints)->sum('value');
                @endphp
                <x-card :title="$mainChart['title']" class="dashboard-panel-card dashboard-panel-card--featured">
                    <p class="dashboard-card-copy">{{ $mainChart['summary'] ?? '' }}</p>

                    @if($mainChartTotal > 0)
                        <div class="dashboard-bar-chart">
                            @foreach($mainChartPoints as $point)
                                <div class="dashboard-bar-chart__item">
                                    <div class="dashboard-bar-chart__value">{{ $point['display'] }}</div>
                                    <div class="dashboard-bar-chart__bar-wrap">
                                        <span class="dashboard-bar-chart__bar" style="height: {{ $point['height'] }}%"></span>
                                    </div>
                                    <div class="dashboard-bar-chart__label">{{ $point['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="dashboard-empty-state">
                            <strong>{{ $mainChart['empty_title'] ?? 'لا توجد بيانات كافية' }}</strong>
                            <p>{{ $mainChart['empty_body'] ?? 'ستظهر المؤشرات هنا فور توفر بيانات كافية للنطاق الحالي.' }}</p>
                        </div>
                    @endif
                </x-card>
            @endif

            @if(!empty($chartCards))
                <div class="internal-dashboard-visual-grid">
                    @foreach($chartCards as $card)
                        @php
                            $items = $card['items'] ?? [];
                            $total = max(0, (int) collect($items)->sum('value'));
                        @endphp
                        <x-card :title="$card['title']" class="dashboard-panel-card">
                            <p class="dashboard-card-copy">{{ $card['summary'] ?? '' }}</p>

                            @if($total > 0)
                                <div class="dashboard-distribution-list">
                                    @foreach($items as $item)
                                        @php
                                            $percent = $total > 0 ? max(4, (int) round(((int) ($item['value'] ?? 0) / $total) * 100)) : 0;
                                        @endphp
                                        <div class="dashboard-distribution-row {{ $tonePanel($item['tone'] ?? 'neutral') }}">
                                            <div class="dashboard-distribution-row__head">
                                                <span>{{ $item['label'] }}</span>
                                                <strong>{{ $item['display'] }}</strong>
                                            </div>
                                            <div class="dashboard-distribution-row__track">
                                                <span class="dashboard-distribution-row__fill" style="width: {{ $percent }}%"></span>
                                            </div>
                                            <div class="dashboard-distribution-row__foot">
                                                @if(!empty($item['detail']))
                                                    <span>{{ $item['detail'] }}</span>
                                                @else
                                                    <span>من إجمالي هذا القسم</span>
                                                @endif
                                                <span>{{ $percent }}%</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="dashboard-empty-state">
                                    <strong>{{ $card['empty_title'] ?? 'لا توجد عناصر معروضة' }}</strong>
                                    <p>{{ $card['empty_body'] ?? 'سيظهر هذا القسم تلقائيًا عند توفر بيانات للنطاق الحالي.' }}</p>
                                </div>
                            @endif
                        </x-card>
                    @endforeach
                </div>
            @endif

            @if(!empty($streamCards))
                <div class="internal-dashboard-stream-grid">
                    @foreach($streamCards as $card)
                        @php $rows = $card['rows'] ?? []; @endphp
                        <x-card :title="$card['title']" class="dashboard-panel-card">
                            <p class="dashboard-card-copy">{{ $card['summary'] ?? '' }}</p>

                            @if(!empty($rows))
                                <div class="dashboard-list">
                                    @foreach($rows as $row)
                                        <div class="dashboard-list-item">
                                            <div class="dashboard-list-item__body">
                                                <div class="dashboard-list-item__title">{{ $row['title'] }}</div>
                                                @if(!empty($row['meta']))
                                                    <div class="dashboard-list-item__meta">{{ $row['meta'] }}</div>
                                                @endif
                                                @if(!empty($row['support']))
                                                    <div class="dashboard-list-item__detail">{{ $row['support'] }}</div>
                                                @endif
                                            </div>
                                            @if(!empty($row['value']))
                                                <span class="badge {{ $toneBadge($row['tone'] ?? 'neutral') }}">{{ $row['value'] }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="dashboard-empty-state">
                                    <strong>{{ $card['empty_title'] ?? 'لا توجد عناصر حالية' }}</strong>
                                    <p>{{ $card['empty_body'] ?? 'ستظهر العناصر الحديثة هنا عندما يتوفر نشاط جديد.' }}</p>
                                </div>
                            @endif
                        </x-card>
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="internal-dashboard-rail">
            @foreach($sideCards as $card)
                @if(($card['type'] ?? null) === 'context')
                    @php
                        $miniTrend = $card['mini_trend']['points'] ?? [];
                        $miniTrendPoints = $withTrendHeights($miniTrend);
                        $miniTrendTotal = collect($miniTrendPoints)->sum('value');
                        $miniDistribution = $card['mini_distribution']['items'] ?? [];
                        $miniDistributionTotal = max(0, (int) collect($miniDistribution)->sum('value'));
                        $rows = $card['rows'] ?? [];
                    @endphp
                    <x-card :title="$card['title']" class="dashboard-panel-card dashboard-panel-card--muted dashboard-context-card">
                        <div class="dashboard-context-stack">
                            @if(($card['state'] ?? null) === 'selected')
                                <div class="dashboard-context-header">
                                    <div>
                                        <h2 class="dashboard-account-panel__title">{{ $card['account_name'] ?? 'حساب محدد' }}</h2>
                                        <p class="dashboard-account-panel__copy">{{ $card['description'] ?? '' }}</p>
                                    </div>
                                    <span class="badge badge-in">عدسة حساب نشطة</span>
                                </div>

                                @if(!empty($card['metrics']))
                                    <div class="dashboard-account-stats">
                                        @foreach($card['metrics'] as $metric)
                                            <div class="dashboard-account-stats__item">
                                                <span>{{ $metric['label'] }}</span>
                                                <strong>{{ $metric['value'] }}</strong>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if($miniTrendTotal > 0)
                                    <div class="dashboard-context-section">
                                        <div class="dashboard-section-title">{{ $card['mini_trend']['title'] ?? 'اتجاه حديث' }}</div>
                                        <p class="dashboard-section-copy">{{ $card['mini_trend']['summary'] ?? '' }}</p>
                                        <div class="dashboard-mini-trend">
                                            @foreach($miniTrendPoints as $point)
                                                <div class="dashboard-mini-trend__item">
                                                    <span class="dashboard-mini-trend__value">{{ $point['display'] }}</span>
                                                    <span class="dashboard-mini-trend__bar" style="height: {{ $point['height'] }}%"></span>
                                                    <span class="dashboard-mini-trend__label">{{ $point['label'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if($miniDistributionTotal > 0)
                                    <div class="dashboard-context-section">
                                        <div class="dashboard-section-title">{{ $card['mini_distribution']['title'] ?? 'توزيع حديث' }}</div>
                                        <p class="dashboard-section-copy">{{ $card['mini_distribution']['summary'] ?? '' }}</p>
                                        <div class="dashboard-distribution-list dashboard-distribution-list--compact">
                                            @foreach($miniDistribution as $item)
                                                @php
                                                    $percent = $miniDistributionTotal > 0 ? max(4, (int) round(((int) ($item['value'] ?? 0) / $miniDistributionTotal) * 100)) : 0;
                                                @endphp
                                                <div class="dashboard-distribution-row {{ $tonePanel($item['tone'] ?? 'neutral') }}">
                                                    <div class="dashboard-distribution-row__head">
                                                        <span>{{ $item['label'] }}</span>
                                                        <strong>{{ $item['display'] }}</strong>
                                                    </div>
                                                    <div class="dashboard-distribution-row__track">
                                                        <span class="dashboard-distribution-row__fill" style="width: {{ $percent }}%"></span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(!empty($rows))
                                    <div class="dashboard-context-section">
                                        <div class="dashboard-section-title">أحدث شحنات الحساب</div>
                                        <div class="dashboard-list dashboard-list--compact">
                                            @foreach($rows as $row)
                                                <div class="dashboard-list-item">
                                                    <div class="dashboard-list-item__body">
                                                        <div class="dashboard-list-item__title">{{ $row['title'] }}</div>
                                                        @if(!empty($row['meta']))
                                                            <div class="dashboard-list-item__meta">{{ $row['meta'] }}</div>
                                                        @endif
                                                    </div>
                                                    @if(!empty($row['value']))
                                                        <span class="badge {{ $toneBadge($row['tone'] ?? 'neutral') }}">{{ $row['value'] }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="dashboard-context-empty">
                                    <div class="dashboard-context-empty__icon">{!! $icon('context') !!}</div>
                                    <div>
                                        <h2 class="dashboard-account-panel__title">لا يوجد حساب محدد</h2>
                                        <p class="dashboard-account-panel__copy">{{ $card['description'] ?? '' }}</p>
                                    </div>
                                </div>
                            @endif

                            @if(!empty($card['cta']['href']))
                                <a href="{{ $card['cta']['href'] }}" class="btn btn-s dashboard-context-cta">
                                    <span class="dashboard-context-cta__icon">{!! $icon('context') !!}</span>
                                    <span>{{ $card['cta']['label'] }}</span>
                                </a>
                            @endif
                        </div>
                    </x-card>
                @elseif(($card['type'] ?? null) === 'actions')
                    @php $items = $card['items'] ?? []; @endphp
                    <x-card :title="$card['title']" class="dashboard-panel-card">
                        <p class="dashboard-card-copy">{{ $card['summary'] ?? '' }}</p>
                        <div class="dashboard-action-list">
                            @foreach($items as $action)
                                @if(!empty($action['href']))
                                    <a href="{{ $action['href'] }}" class="dashboard-action-card dashboard-action-card--{{ $action['variant'] ?? 'secondary' }}">
                                        <span class="dashboard-action-card__icon">{!! $icon($iconForAction($action)) !!}</span>
                                        <span class="dashboard-action-card__body">
                                            <span class="dashboard-action-card__title">{{ $action['label'] }}</span>
                                            <span class="dashboard-action-card__copy">{{ $action['description'] }}</span>
                                        </span>
                                    </a>
                                @else
                                    <div class="dashboard-action-card dashboard-action-card--disabled">
                                        <span class="dashboard-action-card__icon">{!! $icon($iconForAction($action)) !!}</span>
                                        <span class="dashboard-action-card__body">
                                            <span class="dashboard-action-card__title">{{ $action['label'] }}</span>
                                            <span class="dashboard-action-card__copy">{{ $action['description'] }}</span>
                                        </span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </x-card>
                @elseif(($card['type'] ?? null) === 'summary')
                    @php $items = $card['items'] ?? []; @endphp
                    <x-card :title="$card['title']" class="dashboard-panel-card">
                        <div class="dashboard-summary-grid">
                            @foreach($items as $item)
                                <div class="dashboard-summary-card {{ $tonePanel($item['tone'] ?? 'neutral') }}">
                                    <div class="dashboard-summary-card__value">{{ $item['value'] }}</div>
                                    <div class="dashboard-summary-card__label">{{ $item['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @elseif(($card['type'] ?? null) === 'note')
                    <x-card :title="$card['title']" class="dashboard-panel-card dashboard-note-card">
                        <p class="dashboard-note-card__body">{{ $card['body'] ?? '' }}</p>
                    </x-card>
                @elseif(($card['type'] ?? null) === 'warning')
                    <x-card :title="$card['title']" class="dashboard-panel-card dashboard-warning-card">
                        <p class="dashboard-warning-card__body">{{ $card['body'] ?? '' }}</p>
                    </x-card>
                @endif
            @endforeach
        </aside>
    </div>
</div>
