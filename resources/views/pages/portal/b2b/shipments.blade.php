@extends('layouts.app')
@section('title', ($copy['portal_label'] ?? __('portal_shipments.common.portal_b2b')) . ' | ' . __('portal_shipments.common.shipments'))

@section('content')
@php
    $showRoute = $showRoute ?? 'b2b.shipments.show';
    $createRouteName = $createRouteName ?? 'b2b.shipments.create';
    $indexRoute = $indexRoute ?? route('b2b.shipments.index');
    $exportRoute = $exportRoute ?? route('b2b.shipments.export');
    $emptyValue = __('portal_shipments.common.not_available');
    $filters = $filters ?? ['search' => null, 'status' => null, 'carrier' => null, 'from' => null, 'to' => null];
    $hasActiveFilters = $hasActiveFilters ?? false;
    $statusOptions = $statusOptions ?? [];
    $carrierOptions = $carrierOptions ?? [];
    $canExportShipments = $canExportShipments ?? false;
    $hasPagination = method_exists($shipments, 'hasPages') && $shipments->hasPages();
    $currentPage = method_exists($shipments, 'currentPage') ? $shipments->currentPage() : 1;
    $lastPage = method_exists($shipments, 'lastPage') ? $shipments->lastPage() : 1;
    $statIconMap = ['SH' => 'shipments', 'PD' => 'alert', 'TR' => 'trend'];
    $resolveStatus = static function (?string $status) use ($emptyValue): string {
        if (! $status) {
            return $emptyValue;
        }

        $key = 'portal_shipments.statuses.' . $status;
        $translated = __($key);

        return $translated === $key ? $status : $translated;
    };
    $statusTone = static function (?string $status): string {
        return match ((string) $status) {
            \App\Models\Shipment::STATUS_DELIVERED => 'success',
            \App\Models\Shipment::STATUS_IN_TRANSIT,
            \App\Models\Shipment::STATUS_OUT_FOR_DELIVERY,
            \App\Models\Shipment::STATUS_READY_FOR_PICKUP,
            \App\Models\Shipment::STATUS_PICKED_UP,
            \App\Models\Shipment::STATUS_PURCHASED => 'info',
            \App\Models\Shipment::STATUS_EXCEPTION,
            \App\Models\Shipment::STATUS_FAILED,
            \App\Models\Shipment::STATUS_REQUIRES_ACTION,
            \App\Models\Shipment::STATUS_KYC_BLOCKED,
            \App\Models\Shipment::STATUS_RETURNED,
            \App\Models\Shipment::STATUS_CANCELLED => 'danger',
            default => 'warning',
        };
    };
@endphp

<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / الشحنات"
        title="لوحة تشغيل الشحنات"
        subtitle="سجل موحد للشحنات الجارية والاستثناءات وحركة الفريق، مع اختصارات واضحة للفلترة والعودة إلى رحلة الإصدار."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        @if($canCreateShipment)
            <a href="{{ $createRoute }}" class="btn btn-pr">بدء طلب شحنة لفريقك</a>
        @endif
    </x-page-header>

    <div class="b2b-inline-metrics b2b-inline-metrics--spaced">
        @foreach($summaryGroups as $group)
            <div class="b2b-inline-metric b2b-inline-metric--{{ $group['tone'] }}">
                <span class="b2b-inline-metric__label">{{ $group['label'] }}</span>
                <strong class="b2b-inline-metric__value">{{ $group['value'] }}</strong>
            </div>
        @endforeach
    </div>

    <div class="stats-grid b2b-metrics-grid">
        @foreach($stats as $stat)
            <x-stat-card
                :iconName="$statIconMap[$stat['icon']] ?? 'shipments'"
                :label="$stat['label']"
                :value="$stat['value']"
            />
        @endforeach
    </div>

    <div class="b2b-workspace-grid">
        <section class="b2b-panel-stack">
            <x-card title="سجل الشحنات">
                <form method="GET" action="{{ $indexRoute }}" class="b2b-filter-grid">
                    <div class="b2b-filter-grid__wide">
                        <label for="shipment-search-b2b" class="form-label">{{ __('portal_shipments.common.search') }}</label>
                        <input
                            id="shipment-search-b2b"
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="{{ $copy['search_placeholder'] }}"
                            class="form-input"
                            data-testid="shipment-search-input"
                        >
                    </div>

                    <div>
                        <label for="shipment-status-b2b" class="form-label">{{ __('portal_shipments.common.status') }}</label>
                        <select id="shipment-status-b2b" name="status" class="form-input" data-testid="shipment-status-filter">
                            <option value="">{{ __('portal_shipments.common.all_statuses') }}</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="shipment-carrier-b2b" class="form-label">{{ __('portal_shipments.common.carrier') }}</label>
                        <select id="shipment-carrier-b2b" name="carrier" class="form-input" data-testid="shipment-carrier-filter">
                            <option value="">{{ __('portal_shipments.common.all_carriers') }}</option>
                            @foreach($carrierOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['carrier'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="shipment-from-b2b" class="form-label">{{ __('portal_shipments.common.from_date') }}</label>
                        <input id="shipment-from-b2b" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input">
                    </div>

                    <div>
                        <label for="shipment-to-b2b" class="form-label">{{ __('portal_shipments.common.to_date') }}</label>
                        <input id="shipment-to-b2b" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input">
                    </div>

                    <div class="b2b-filter-grid__actions">
                        <button type="submit" class="btn btn-s" data-testid="shipment-filter-submit">{{ __('portal_shipments.common.apply_filters') }}</button>
                        @if($hasActiveFilters)
                            <a href="{{ $indexRoute }}" class="btn btn-s" data-testid="shipment-filter-reset">{{ __('portal_shipments.common.clear_filters') }}</a>
                        @endif
                        @if($canExportShipments)
                            <a href="{{ $exportRoute }}" class="btn btn-s" data-testid="shipment-export-csv">{{ __('portal_shipments.common.export_csv') }}</a>
                        @endif
                    </div>
                </form>

                <div class="b2b-table-shell">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>{{ __('portal_shipments.common.reference') }}</th>
                            <th>{{ __('portal_shipments.index.b2b.recipient') }}</th>
                            <th>{{ __('portal_shipments.common.status') }}</th>
                            <th>{{ __('portal_shipments.index.b2b.total_charge') }}</th>
                            <th>{{ __('portal_shipments.common.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($shipments as $shipment)
                            <tr>
                                <td class="td-mono">
                                    <a href="{{ route($showRoute, ['id' => $shipment->id]) }}" class="b2b-table-link">
                                        {{ $shipment->reference_number ?? $shipment->tracking_number ?? $shipment->id }}
                                    </a>
                                </td>
                                <td>{{ $shipment->recipient_name ?? __('portal_shipments.common.not_specified') }}</td>
                                <td>
                                    <span class="b2b-status-pill b2b-status-pill--{{ $statusTone($shipment->status) }}">{{ $resolveStatus($shipment->status) }}</span>
                                </td>
                                <td>{{ number_format((float) ($shipment->total_charge ?? 0), 2) }} {{ $shipment->currency ?? 'USD' }}</td>
                                <td>
                                    <div class="b2b-table-actions">
                                        <a href="{{ route($showRoute, ['id' => $shipment->id]) }}" class="btn btn-s">{{ __('portal_shipments.common.view') }}</a>
                                        @if($canCreateShipment)
                                            <a
                                                href="{{ route($createRouteName, ['clone' => $shipment->id]) }}"
                                                class="btn btn-s"
                                                data-testid="shipment-clone-link-{{ $shipment->id }}"
                                            >{{ __('portal_shipments.common.clone_short') }}</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">{{ $copy['empty_state'] }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($hasPagination)
                    <div class="b2b-table-footer">
                        <div class="b2b-table-footer__meta">
                            {{ __('portal_shipments.common.page') }} {{ $currentPage }} {{ __('portal_shipments.common.of') }} {{ $lastPage }}
                        </div>
                        <div class="b2b-table-footer__actions">
                            @if($shipments->onFirstPage())
                                <span class="btn btn-s is-disabled">{{ __('portal_shipments.common.previous') }}</span>
                            @else
                                <a href="{{ $shipments->previousPageUrl() }}" class="btn btn-s">{{ __('portal_shipments.common.previous') }}</a>
                            @endif

                            @if($shipments->hasMorePages())
                                <a href="{{ $shipments->nextPageUrl() }}" class="btn btn-s">{{ __('portal_shipments.common.next') }}</a>
                            @else
                                <span class="btn btn-s is-disabled">{{ __('portal_shipments.common.next') }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="إيقاع التنفيذ">
                <div class="b2b-trend-panel">
                    <div class="b2b-panel-kicker">آخر سبعة أيام</div>
                    <div class="b2b-trend-bars">
                        @foreach($shipmentTrend as $point)
                            <div class="b2b-trend-bar">
                                <span class="b2b-trend-bar__fill" style="height: {{ max(12, $point['height']) }}%"></span>
                                <span class="b2b-trend-bar__label">{{ $point['label'] }}</span>
                                <span class="b2b-trend-bar__value">{{ $point['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-card>

            <x-card title="مزيج الشحنات">
                <div class="b2b-mix-list">
                    @foreach($statusMix as $item)
                        <div class="b2b-mix-item">
                            <div class="b2b-mix-item__head">
                                <span>{{ $item['label'] }}</span>
                                <strong>{{ $item['value'] }}</strong>
                            </div>
                            <div class="b2b-mix-item__meter">
                                <span class="b2b-mix-item__meter-fill b2b-mix-item__meter-fill--{{ $item['tone'] }}" style="width: {{ max(6, $item['percentage']) }}%"></span>
                            </div>
                            <div class="b2b-mix-item__meta">{{ $item['percentage'] }}%</div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="نشاط حديث">
                <div class="b2b-stream-list">
                    @forelse($recentActivity as $shipment)
                        <a href="{{ route($showRoute, ['id' => $shipment->id]) }}" class="b2b-stream-row b2b-stream-row--compact">
                            <div class="b2b-stream-row__main">
                                <div class="b2b-stream-row__title">{{ $shipment->reference_number ?? $shipment->tracking_number ?? $shipment->id }}</div>
                                <div class="b2b-stream-row__meta">{{ $shipment->recipient_name ?? 'مستلم غير محدد' }}</div>
                            </div>
                            <div class="b2b-stream-row__side">
                                <span class="b2b-status-pill b2b-status-pill--{{ $statusTone($shipment->status) }}">{{ $resolveStatus($shipment->status) }}</span>
                                <span class="b2b-stream-row__date">{{ optional($shipment->updated_at)->format('Y-m-d H:i') ?? 'غير محدد' }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="b2b-inline-empty">عند بدء حركة الشحن على الحساب ستظهر هنا آخر العناصر التي تحتاج نظر الفريق سريعاً.</div>
                    @endforelse
                </div>
            </x-card>
        </aside>
    </div>
</div>
@endsection
