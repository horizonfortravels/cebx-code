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

    $toneClass = static fn (?string $tone): string => match ($tone) {
        'success' => 'ops-tone--success',
        'warning' => 'ops-tone--warning',
        'danger' => 'ops-tone--danger',
        'info' => 'ops-tone--info',
        default => 'ops-tone--neutral',
    };
    $pillClass = static fn (?string $tone): string => 'ops-pill '.$toneClass($tone);
    $statusClass = static fn (?string $tone): string => 'ops-status '.$toneClass($tone);
    $quickActionClass = static fn (?string $variant): string => match ($variant) {
        'primary' => 'ops-quick-action ops-quick-action--primary',
        'disabled' => 'ops-quick-action ops-quick-action--disabled',
        default => 'ops-quick-action ops-quick-action--secondary',
    };
    $actionCardClass = static fn (?string $variant): string => match ($variant) {
        'primary' => 'ops-action-card ops-action-card--primary',
        'disabled' => 'ops-action-card ops-action-card--disabled',
        default => 'ops-action-card',
    };
    $icon = static fn (?string $name): string => $iconMap[$name] ?? $iconMap['activity'];

    $iconForKpi = static function (array $kpi): string {
        $label = (string) ($kpi['label'] ?? '');

        return match (true) {
            str_contains($label, 'الحساب') => 'accounts',
            str_contains($label, 'مستخدم') => 'users',
            str_contains($label, 'KYC'), str_contains($label, 'التحقق') => 'kyc',
            str_contains($label, 'تذاكر') => 'tickets',
            str_contains($label, 'محفظ'), str_contains($label, 'رصيد'), str_contains($label, 'فوترة') => 'billing',
            str_contains($label, 'تكامل'), str_contains($label, 'ناقل') => 'integrations',
            str_contains($label, 'استثن'), str_contains($label, 'متابعة') => 'alert',
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

    $iconForPulse = static function (array $item): string {
        $label = (string) ($item['label'] ?? '');
        $tone = (string) ($item['tone'] ?? 'neutral');

        return match (true) {
            str_contains($label, 'KYC'), str_contains($label, 'التحقق') => 'kyc',
            str_contains($label, 'تكامل') => 'integrations',
            str_contains($label, 'محفظ'), str_contains($label, 'رصيد'), str_contains($label, 'فوترة') => 'billing',
            str_contains($label, 'تذاكر') => 'tickets',
            str_contains($label, 'شحن') => 'shipments',
            $tone === 'success' => 'check',
            $tone === 'danger' => 'alert',
            default => 'activity',
        };
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
    $mainChart = is_array($dashboard['main_chart'] ?? null) ? $dashboard['main_chart'] : null;
    $kpis = array_values($dashboard['kpis'] ?? []);
    $pills = array_values($dashboard['pills'] ?? []);
    $heroActions = array_values($dashboard['hero_actions'] ?? []);
    $chartCards = array_values($dashboard['chart_cards'] ?? []);
    $streamCards = array_values($dashboard['stream_cards'] ?? []);
    $sideCards = array_values($dashboard['side_cards'] ?? []);

    $contextCard = collect($sideCards)->firstWhere('type', 'context');
    $actionsCard = collect($sideCards)->firstWhere('type', 'actions');
    $summaryCard = collect($sideCards)->firstWhere('type', 'summary');
    $noteCards = collect($sideCards)->where('type', 'note')->values()->all();
    $warningCards = collect($sideCards)->where('type', 'warning')->values()->all();

    $actionItems = is_array($actionsCard) ? array_values($actionsCard['items'] ?? []) : [];
    $heroActionPool = $heroActions !== [] ? $heroActions : array_slice($actionItems, 0, 3);
    $heroActionKeys = collect($heroActionPool)->map(static fn (array $action): string => (string) ($action['label'] ?? '').'|'.(string) ($action['href'] ?? ''))->all();
    $remainingActions = array_values(array_filter($actionItems, static function (array $action) use ($heroActionKeys): bool {
        return ! in_array((string) ($action['label'] ?? '').'|'.(string) ($action['href'] ?? ''), $heroActionKeys, true);
    }));

    $heroMetrics = array_slice($kpis, 0, min(4, count($kpis)));
    $secondaryKpis = array_slice($kpis, count($heroMetrics));
    $pulseItems = is_array($summaryCard) ? array_values($summaryCard['items'] ?? []) : [];
    $headlineVisualCards = array_slice($chartCards, 0, 2);
    $railVisualCards = array_slice($chartCards, 2);
    $primaryStreamCards = array_slice($streamCards, 0, 2);
    $secondaryStreamCards = array_slice($streamCards, 2);

    $contextState = is_array($contextCard) ? (string) ($contextCard['state'] ?? '') : '';
    $contextMetrics = is_array($contextCard) ? array_values($contextCard['metrics'] ?? []) : [];
    $contextRows = is_array($contextCard) ? array_values($contextCard['rows'] ?? []) : [];
    $contextMiniTrend = is_array($contextCard) ? $withTrendHeights($contextCard['mini_trend']['points'] ?? []) : [];
    $contextMiniTrendTotal = (int) collect($contextMiniTrend)->sum('value');
    $contextMiniDistribution = is_array($contextCard) ? array_values($contextCard['mini_distribution']['items'] ?? []) : [];
    $contextMiniDistributionTotal = (int) collect($contextMiniDistribution)->sum(static fn (array $item): int => (int) ($item['value'] ?? 0));
    $contextTitle = is_array($contextCard) ? (string) ($contextCard['title'] ?? 'سياق الحساب') : 'سياق الحساب';
    $contextDescription = is_array($contextCard) ? (string) ($contextCard['description'] ?? '') : '';
    $contextMiniDistributionTitle = is_array($contextCard) ? (string) data_get($contextCard, 'mini_distribution.title', 'حالة الحساب المحدد') : 'حالة الحساب المحدد';
    $contextMiniTrendTitle = is_array($contextCard) ? (string) data_get($contextCard, 'mini_trend.title', 'اتجاه قصير') : 'اتجاه قصير';
    $actionsTitle = is_array($actionsCard) ? (string) ($actionsCard['title'] ?? 'مراكز العمل') : 'مراكز العمل';
    $actionsSummary = is_array($actionsCard) ? (string) ($actionsCard['summary'] ?? '') : '';
    $pulseTitle = is_array($summaryCard) ? (string) ($summaryCard['title'] ?? 'نبض التشغيل') : 'نبض التشغيل';

    $roleBadge = trim((string) ($dashboard['role_badge'] ?? ''));
    $roleDescription = trim((string) ($dashboard['role_description'] ?? ''));
@endphp

<div class="ops-dashboard">
    <section class="ops-dashboard__hero">
        <div class="ops-dashboard__hero-main">
            <div class="ops-dashboard__eyebrow">{{ $dashboard['eyebrow'] ?? '' }}</div>
            <h1 class="ops-dashboard__title">{{ $dashboard['title'] ?? '' }}</h1>
            <p class="ops-dashboard__description">{{ $dashboard['description'] ?? '' }}</p>

            @if($roleBadge !== '' || $roleDescription !== '')
                <div class="ops-dashboard__role-note">
                    <span class="ops-dashboard__role-icon" aria-hidden="true">{!! $icon('activity') !!}</span>
                    <div>
                        @if($roleBadge !== '')
                            <strong class="ops-dashboard__role-title">{{ $roleBadge }}</strong>
                        @endif
                        @if($roleDescription !== '')
                            <p class="ops-dashboard__role-copy">{{ $roleDescription }}</p>
                        @endif
                    </div>
                </div>
            @endif

            @if(!empty($pills))
                <div class="ops-dashboard__pill-row">
                    @foreach($pills as $pill)
                        <span class="{{ $pillClass($pill['tone'] ?? 'neutral') }}">{{ $pill['label'] }}</span>
                    @endforeach
                </div>
            @endif

            @if(!empty($heroMetrics))
                <div class="ops-dashboard__snapshot-grid">
                    @foreach($heroMetrics as $kpi)
                        <article class="ops-snapshot-card {{ $toneClass($kpi['tone'] ?? 'neutral') }}">
                            <div class="ops-snapshot-card__icon" aria-hidden="true">{!! $icon($iconForKpi($kpi)) !!}</div>
                            <div class="ops-snapshot-card__body">
                                <span class="ops-snapshot-card__label">{{ $kpi['label'] }}</span>
                                <strong class="ops-snapshot-card__value">{{ $kpi['display'] }}</strong>
                                <span class="ops-snapshot-card__hint">{{ $kpi['hint'] ?? '' }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="ops-dashboard__hero-side">
            @if(is_array($contextCard))
                <section class="ops-context-card">
                    <div class="ops-context-card__header">
                        <div>
                            <span class="ops-context-card__eyebrow">{{ $contextTitle }}</span>
                            <h2 class="ops-context-card__title">{{ $contextState === 'selected' ? ($contextCard['account_name'] ?? 'حساب محدد') : 'لا يوجد حساب محدد' }}</h2>
                        </div>
                        <span class="ops-context-card__badge {{ $contextState === 'selected' ? 'ops-context-card__badge--active' : 'ops-context-card__badge--idle' }}">
                            {{ $contextState === 'selected' ? 'عدسة تشغيلية نشطة' : 'المنصة الكاملة' }}
                        </span>
                    </div>

                    <p class="ops-context-card__description">{{ $contextDescription }}</p>

                    @if($contextState === 'selected' && !empty($contextMetrics))
                        <div class="ops-context-card__metrics">
                            @foreach($contextMetrics as $metric)
                                <div class="ops-context-card__metric">
                                    <span>{{ $metric['label'] }}</span>
                                    <strong>{{ $metric['value'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($contextState === 'selected' && $contextMiniDistributionTotal > 0)
                        <div class="ops-context-card__section">
                            <div class="ops-context-card__section-head">
                                <span>{{ $contextMiniDistributionTitle }}</span>
                                <strong>{{ number_format($contextMiniDistributionTotal) }}</strong>
                            </div>
                            <div class="ops-context-card__chip-grid">
                                @foreach($contextMiniDistribution as $item)
                                    <span class="ops-context-card__chip {{ $toneClass($item['tone'] ?? 'neutral') }}">
                                        <span>{{ $item['label'] }}</span>
                                        <strong>{{ $item['display'] }}</strong>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($contextState === 'selected' && $contextMiniTrendTotal > 0)
                        <div class="ops-context-card__section">
                            <div class="ops-context-card__section-head">
                                <span>{{ $contextMiniTrendTitle }}</span>
                                <strong>{{ number_format($contextMiniTrendTotal) }}</strong>
                            </div>
                            <div class="ops-mini-trend">
                                @foreach($contextMiniTrend as $point)
                                    <div class="ops-mini-trend__item">
                                        <span class="ops-mini-trend__value">{{ $point['display'] }}</span>
                                        <span class="ops-mini-trend__rail">
                                            <span class="ops-mini-trend__fill" style="--ops-mini-height: {{ $point['height'] }}%"></span>
                                        </span>
                                        <span class="ops-mini-trend__label">{{ $point['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($contextState === 'selected' && !empty($contextRows))
                        <div class="ops-context-card__section">
                            <div class="ops-context-card__section-head">
                                <span>أحدث الشحنات ضمن الحساب</span>
                                <strong>{{ count($contextRows) }}</strong>
                            </div>
                            <div class="ops-context-card__list">
                                @foreach(array_slice($contextRows, 0, 3) as $row)
                                    <div class="ops-context-card__list-row">
                                        <div>
                                            <div class="ops-context-card__list-title">{{ $row['title'] }}</div>
                                            @if(!empty($row['meta']))
                                                <div class="ops-context-card__list-meta">{{ $row['meta'] }}</div>
                                            @endif
                                        </div>
                                        @if(!empty($row['value']))
                                            <span class="{{ $statusClass($row['tone'] ?? 'neutral') }}">{{ $row['value'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(!empty($contextCard['cta']['href']))
                        <a href="{{ $contextCard['cta']['href'] }}" class="ops-context-card__cta">
                            <span aria-hidden="true">{!! $icon('context') !!}</span>
                            <span>{{ $contextCard['cta']['label'] }}</span>
                        </a>
                    @endif
                </section>
            @endif

            <section class="ops-action-cluster">
                <div class="ops-action-cluster__head">
                    <span class="ops-action-cluster__eyebrow">الانتقال السريع</span>
                    <h2 class="ops-action-cluster__title">ابدأ من المسار الأقرب للعمل</h2>
                    <p class="ops-action-cluster__copy">
                        {{ $actionsSummary !== '' ? $actionsSummary : 'كل إجراء هنا يحافظ على حدود الدور الحالي وسياق الحساب النشط دون تجاوز للصلاحيات.' }}
                    </p>
                </div>

                @if(!empty($heroActionPool))
                    <div class="ops-action-cluster__list">
                        @foreach($heroActionPool as $action)
                            @if(!empty($action['href']))
                                <a href="{{ $action['href'] }}" class="{{ $quickActionClass($action['variant'] ?? null) }}">
                                    <span class="ops-quick-action__icon" aria-hidden="true">{!! $icon($iconForAction($action)) !!}</span>
                                    <span class="ops-quick-action__body">
                                        <span class="ops-quick-action__title">{{ $action['label'] }}</span>
                                        <span class="ops-quick-action__copy">{{ $action['description'] }}</span>
                                    </span>
                                </a>
                            @else
                                <div class="{{ $quickActionClass($action['variant'] ?? 'disabled') }}">
                                    <span class="ops-quick-action__icon" aria-hidden="true">{!! $icon($iconForAction($action)) !!}</span>
                                    <span class="ops-quick-action__body">
                                        <span class="ops-quick-action__title">{{ $action['label'] }}</span>
                                        <span class="ops-quick-action__copy">{{ $action['description'] }}</span>
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="ops-empty-state ops-empty-state--hero">
                        <div class="ops-empty-state__icon" aria-hidden="true">{!! $icon('activity') !!}</div>
                        <strong class="ops-empty-state__title">لا توجد مسارات سريعة معروضة</strong>
                        <p class="ops-empty-state__body">ستظهر هنا أقرب المسارات المتاحة عندما تتوفر أسطح مناسبة للدور الحالي.</p>
                    </div>
                @endif
            </section>
        </div>
    </section>

    @if(!empty($pulseItems))
        <section class="ops-pulse-strip">
            <div class="ops-pulse-strip__header">
                <span class="ops-pulse-strip__eyebrow">{{ $pulseTitle }}</span>
                <p class="ops-pulse-strip__copy">مؤشرات مركزة لما يحتاج انتباهًا مباشرًا قبل الانتقال إلى بقية اللوحة.</p>
            </div>
            <div class="ops-pulse-strip__grid">
                @foreach($pulseItems as $item)
                    <article class="ops-pulse-card {{ $toneClass($item['tone'] ?? 'neutral') }}">
                        <span class="ops-pulse-card__icon" aria-hidden="true">{!! $icon($iconForPulse($item)) !!}</span>
                        <div class="ops-pulse-card__body">
                            <span class="ops-pulse-card__label">{{ $item['label'] }}</span>
                            <strong class="ops-pulse-card__value">{{ $item['value'] }}</strong>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if(!empty($secondaryKpis))
        <div class="stats-grid ops-dashboard__kpis">
            @foreach($secondaryKpis as $kpi)
                <x-stat-card
                    class="ops-kpi-card {{ $toneClass($kpi['tone'] ?? 'neutral') }}"
                    :label="$kpi['label']"
                    :value="$kpi['display']"
                    :meta="$kpi['hint'] ?? null"
                    :iconSvg="$icon($iconForKpi($kpi))"
                />
            @endforeach
        </div>
    @endif

    <div class="ops-dashboard__layout">
        <div class="ops-dashboard__main">
            @if(!empty($mainChart))
                @php
                    $mainChartPoints = $withTrendHeights($mainChart['points'] ?? []);
                    $mainChartTotal = (int) collect($mainChartPoints)->sum('value');
                    $mainChartPeak = $mainChartPoints !== [] ? collect($mainChartPoints)->sortByDesc('value')->first() : null;
                    $mainChartLatest = $mainChartPoints !== [] ? collect($mainChartPoints)->last() : null;
                @endphp
                <x-card :title="$mainChart['title']" class="ops-card ops-card--feature ops-card--chart">
                    <div class="ops-card__lead">
                        <p class="ops-card__copy">{{ $mainChart['summary'] ?? '' }}</p>

                        @if($mainChartTotal > 0)
                            <div class="ops-card__fact-row">
                                <div class="ops-fact-tile">
                                    <span>إجمالي القراءة</span>
                                    <strong>{{ number_format($mainChartTotal) }}</strong>
                                </div>
                                <div class="ops-fact-tile">
                                    <span>أعلى يوم</span>
                                    <strong>{{ $mainChartPeak['display'] ?? '0' }}</strong>
                                    <small>{{ $mainChartPeak['label'] ?? '-' }}</small>
                                </div>
                                <div class="ops-fact-tile">
                                    <span>آخر قراءة</span>
                                    <strong>{{ $mainChartLatest['display'] ?? '0' }}</strong>
                                    <small>{{ $mainChartLatest['label'] ?? '-' }}</small>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($mainChartTotal > 0)
                        <div class="ops-trend">
                            @foreach($mainChartPoints as $point)
                                <div class="ops-trend__item">
                                    <span class="ops-trend__value">{{ $point['display'] }}</span>
                                    <span class="ops-trend__rail">
                                        <span class="ops-trend__fill" style="--ops-bar-height: {{ $point['height'] }}%"></span>
                                    </span>
                                    <span class="ops-trend__label">{{ $point['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="ops-empty-state">
                            <div class="ops-empty-state__icon" aria-hidden="true">{!! $icon('reports') !!}</div>
                            <strong class="ops-empty-state__title">{{ $mainChart['empty_title'] ?? 'لا توجد بيانات كافية' }}</strong>
                            <p class="ops-empty-state__body">{{ $mainChart['empty_body'] ?? 'ستظهر المؤشرات الزمنية هنا فور توفر بيانات ضمن النطاق الحالي.' }}</p>
                        </div>
                    @endif
                </x-card>
            @endif

            @if(!empty($headlineVisualCards))
                <div class="ops-dashboard__visual-grid">
                    @foreach($headlineVisualCards as $card)
                        @php
                            $items = array_values($card['items'] ?? []);
                            $total = max(0, (int) collect($items)->sum(static fn (array $item): int => (int) ($item['value'] ?? 0)));
                            $activeSegments = collect($items)->filter(static fn (array $item): bool => (int) ($item['value'] ?? 0) > 0)->count();
                        @endphp
                        <x-card :title="$card['title']" class="ops-card">
                            <div class="ops-card__lead">
                                <p class="ops-card__copy">{{ $card['summary'] ?? '' }}</p>
                                <div class="ops-card__metric-chip">
                                    <span>إجمالي العناصر</span>
                                    <strong>{{ number_format($total) }}</strong>
                                    <small>{{ number_format($activeSegments) }} فئات نشطة</small>
                                </div>
                            </div>

                            @if($total > 0)
                                <div class="ops-distribution-list">
                                    @foreach($items as $item)
                                        @php
                                            $percent = $total > 0 ? max(4, (int) round(((int) ($item['value'] ?? 0) / $total) * 100)) : 0;
                                        @endphp
                                        <div class="ops-distribution-row {{ $toneClass($item['tone'] ?? 'neutral') }}">
                                            <div class="ops-distribution-row__head">
                                                <div>
                                                    <div class="ops-distribution-row__label">{{ $item['label'] }}</div>
                                                    @if(!empty($item['detail']))
                                                        <div class="ops-distribution-row__detail">{{ $item['detail'] }}</div>
                                                    @endif
                                                </div>
                                                <strong class="ops-distribution-row__value">{{ $item['display'] }}</strong>
                                            </div>
                                            <div class="ops-distribution-row__track">
                                                <span class="ops-distribution-row__fill" style="--ops-fill-width: {{ $percent }}%"></span>
                                            </div>
                                            <div class="ops-distribution-row__foot">
                                                <span>{{ $percent }}%</span>
                                                <span>من هذا القسم</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="ops-empty-state">
                                    <div class="ops-empty-state__icon" aria-hidden="true">{!! $icon('activity') !!}</div>
                                    <strong class="ops-empty-state__title">{{ $card['empty_title'] ?? 'لا توجد عناصر معروضة' }}</strong>
                                    <p class="ops-empty-state__body">{{ $card['empty_body'] ?? 'سيمتلئ هذا القسم تلقائيًا عند توفر بيانات للنطاق الحالي.' }}</p>
                                </div>
                            @endif
                        </x-card>
                    @endforeach
                </div>
            @endif

            @if(!empty($primaryStreamCards))
                <div class="ops-dashboard__stream-grid">
                    @foreach($primaryStreamCards as $card)
                        @php $rows = array_values($card['rows'] ?? []); @endphp
                        <x-card :title="$card['title']" class="ops-card ops-card--stream">
                            <p class="ops-card__copy">{{ $card['summary'] ?? '' }}</p>

                            @if(!empty($rows))
                                <div class="ops-stream-list">
                                    @foreach($rows as $row)
                                        <div class="ops-stream-row {{ $toneClass($row['tone'] ?? 'neutral') }}">
                                            <div class="ops-stream-row__body">
                                                <div class="ops-stream-row__title">{{ $row['title'] }}</div>
                                                @if(!empty($row['meta']))
                                                    <div class="ops-stream-row__meta">{{ $row['meta'] }}</div>
                                                @endif
                                                @if(!empty($row['support']))
                                                    <div class="ops-stream-row__detail">{{ $row['support'] }}</div>
                                                @endif
                                            </div>
                                            @if(!empty($row['value']))
                                                <span class="{{ $statusClass($row['tone'] ?? 'neutral') }}">{{ $row['value'] }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="ops-empty-state">
                                    <div class="ops-empty-state__icon" aria-hidden="true">{!! $icon('shipments') !!}</div>
                                    <strong class="ops-empty-state__title">{{ $card['empty_title'] ?? 'لا توجد عناصر حالية' }}</strong>
                                    <p class="ops-empty-state__body">{{ $card['empty_body'] ?? 'عند ظهور نشاط جديد سيظهر هنا ضمن هذا القسم.' }}</p>
                                </div>
                            @endif
                        </x-card>
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="ops-dashboard__rail">
            @if(!empty($remainingActions))
                <x-card :title="$actionsTitle" class="ops-card ops-card--stack">
                    <p class="ops-card__copy">{{ $actionsSummary !== '' ? $actionsSummary : 'وصول منظم إلى بقية المراكز المتاحة ضمن الصلاحيات الحالية.' }}</p>
                    <div class="ops-action-list">
                        @foreach($remainingActions as $action)
                            @if(!empty($action['href']))
                                <a href="{{ $action['href'] }}" class="{{ $actionCardClass($action['variant'] ?? null) }}">
                                    <span class="ops-action-card__icon" aria-hidden="true">{!! $icon($iconForAction($action)) !!}</span>
                                    <span class="ops-action-card__body">
                                        <span class="ops-action-card__title">{{ $action['label'] }}</span>
                                        <span class="ops-action-card__copy">{{ $action['description'] }}</span>
                                    </span>
                                </a>
                            @else
                                <div class="{{ $actionCardClass($action['variant'] ?? 'disabled') }}">
                                    <span class="ops-action-card__icon" aria-hidden="true">{!! $icon($iconForAction($action)) !!}</span>
                                    <span class="ops-action-card__body">
                                        <span class="ops-action-card__title">{{ $action['label'] }}</span>
                                        <span class="ops-action-card__copy">{{ $action['description'] }}</span>
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </x-card>
            @endif

            @foreach($railVisualCards as $card)
                @php
                    $items = array_values($card['items'] ?? []);
                    $total = max(0, (int) collect($items)->sum(static fn (array $item): int => (int) ($item['value'] ?? 0)));
                @endphp
                <x-card :title="$card['title']" class="ops-card">
                    <p class="ops-card__copy">{{ $card['summary'] ?? '' }}</p>

                    @if($total > 0)
                        <div class="ops-distribution-list ops-distribution-list--compact">
                            @foreach($items as $item)
                                @php
                                    $percent = $total > 0 ? max(4, (int) round(((int) ($item['value'] ?? 0) / $total) * 100)) : 0;
                                @endphp
                                <div class="ops-distribution-row ops-distribution-row--compact {{ $toneClass($item['tone'] ?? 'neutral') }}">
                                    <div class="ops-distribution-row__head">
                                        <span class="ops-distribution-row__label">{{ $item['label'] }}</span>
                                        <strong class="ops-distribution-row__value">{{ $item['display'] }}</strong>
                                    </div>
                                    <div class="ops-distribution-row__track">
                                        <span class="ops-distribution-row__fill" style="--ops-fill-width: {{ $percent }}%"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="ops-empty-state ops-empty-state--compact">
                            <div class="ops-empty-state__icon" aria-hidden="true">{!! $icon('reports') !!}</div>
                            <strong class="ops-empty-state__title">{{ $card['empty_title'] ?? 'لا توجد عناصر معروضة' }}</strong>
                            <p class="ops-empty-state__body">{{ $card['empty_body'] ?? 'سيمتلئ هذا القسم تلقائيًا عند توفر بيانات للنطاق الحالي.' }}</p>
                        </div>
                    @endif
                </x-card>
            @endforeach

            @foreach($secondaryStreamCards as $card)
                @php $rows = array_values($card['rows'] ?? []); @endphp
                <x-card :title="$card['title']" class="ops-card ops-card--stack">
                    <p class="ops-card__copy">{{ $card['summary'] ?? '' }}</p>

                    @if(!empty($rows))
                        <div class="ops-stream-list ops-stream-list--compact">
                            @foreach($rows as $row)
                                <div class="ops-stream-row ops-stream-row--compact {{ $toneClass($row['tone'] ?? 'neutral') }}">
                                    <div class="ops-stream-row__body">
                                        <div class="ops-stream-row__title">{{ $row['title'] }}</div>
                                        @if(!empty($row['meta']))
                                            <div class="ops-stream-row__meta">{{ $row['meta'] }}</div>
                                        @endif
                                        @if(!empty($row['support']))
                                            <div class="ops-stream-row__detail">{{ $row['support'] }}</div>
                                        @endif
                                    </div>
                                    @if(!empty($row['value']))
                                        <span class="{{ $statusClass($row['tone'] ?? 'neutral') }}">{{ $row['value'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="ops-empty-state ops-empty-state--compact">
                            <div class="ops-empty-state__icon" aria-hidden="true">{!! $icon('activity') !!}</div>
                            <strong class="ops-empty-state__title">{{ $card['empty_title'] ?? 'لا توجد عناصر حالية' }}</strong>
                            <p class="ops-empty-state__body">{{ $card['empty_body'] ?? 'عند ظهور نشاط جديد سيظهر هنا ضمن هذا القسم.' }}</p>
                        </div>
                    @endif
                </x-card>
            @endforeach

            @foreach($noteCards as $card)
                <x-card :title="$card['title']" class="ops-card ops-note-card">
                    <div class="ops-message-card">
                        <span class="ops-message-card__icon" aria-hidden="true">{!! $icon('billing') !!}</span>
                        <p class="ops-message-card__body">{{ $card['body'] ?? '' }}</p>
                    </div>
                </x-card>
            @endforeach

            @foreach($warningCards as $card)
                <x-card :title="$card['title']" class="ops-card ops-warning-card">
                    <div class="ops-message-card">
                        <span class="ops-message-card__icon" aria-hidden="true">{!! $icon('alert') !!}</span>
                        <p class="ops-message-card__body">{{ $card['body'] ?? '' }}</p>
                    </div>
                </x-card>
            @endforeach
        </aside>
    </div>
</div>
