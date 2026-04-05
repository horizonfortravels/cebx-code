@extends('layouts.app')
@section('title', ($copy['portal_label'] ?? __('portal_shipments.common.portal_b2c')) . ' | ' . __('portal_shipments.common.shipments'))

@section('content')
@php
    $showRoute = $showRoute ?? 'b2c.shipments.show';
    $createRouteName = $createRouteName ?? 'b2c.shipments.create';
    $indexRoute = $indexRoute ?? route('b2c.shipments.index');
    $exportRoute = $exportRoute ?? route('b2c.shipments.export');
    $emptyValue = __('portal_shipments.common.not_available');
    $filters = $filters ?? ['search' => null, 'status' => null, 'carrier' => null, 'from' => null, 'to' => null];
    $hasActiveFilters = $hasActiveFilters ?? false;
    $statusOptions = $statusOptions ?? [];
    $carrierOptions = $carrierOptions ?? [];
    $canExportShipments = $canExportShipments ?? false;
    $hasPagination = method_exists($shipments, 'hasPages') && $shipments->hasPages();
    $currentPage = method_exists($shipments, 'currentPage') ? $shipments->currentPage() : 1;
    $lastPage = method_exists($shipments, 'lastPage') ? $shipments->lastPage() : 1;
    $summaryGroups = $summaryGroups ?? [];
    $recentActivity = $recentActivity ?? collect();
    $continueShipment = $continueShipment ?? null;
    $continueAction = $continueAction ?? null;
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
            \App\Models\Shipment::STATUS_EXCEPTION,
            \App\Models\Shipment::STATUS_FAILED,
            \App\Models\Shipment::STATUS_REQUIRES_ACTION,
            \App\Models\Shipment::STATUS_KYC_BLOCKED => 'danger',
            \App\Models\Shipment::STATUS_IN_TRANSIT,
            \App\Models\Shipment::STATUS_OUT_FOR_DELIVERY,
            \App\Models\Shipment::STATUS_READY_FOR_PICKUP,
            \App\Models\Shipment::STATUS_PICKED_UP,
            \App\Models\Shipment::STATUS_PURCHASED => 'info',
            default => 'warning',
        };
    };
@endphp

<x-page-header
    :eyebrow="$copy['title']"
    title="سجل الشحنات"
    :subtitle="$copy['description']"
    meta="مساحة يومية أخف لمتابعة الشحنات الفردية خطوة بخطوة."
>
    @if($canCreateShipment)
        <a href="{{ $createRoute }}" class="btn btn-pr">{{ $copy['create_cta'] }}</a>
    @endif
    <a href="{{ route('b2c.tracking.index') }}" class="btn btn-s">تتبع شحنة</a>
</x-page-header>

<div class="b2c-summary-ribbon">
    @foreach($summaryGroups as $group)
        <div class="b2c-summary-ribbon__item b2c-summary-ribbon__item--{{ $group['tone'] }}">
            <span class="b2c-summary-ribbon__label">{{ $group['label'] }}</span>
            <strong class="b2c-summary-ribbon__value">{{ $group['value'] }}</strong>
        </div>
    @endforeach
</div>

<div class="b2c-workspace-grid">
    <div class="b2c-panel-stack">
        @if($continueShipment && $continueAction)
            <article class="b2c-journey-spotlight">
                <div class="b2c-journey-spotlight__copy">
                    <div class="b2c-journey-spotlight__eyebrow">استكمال الشحنة الأقرب لك</div>
                    <div class="b2c-journey-spotlight__title">{{ $continueShipment->reference_number ?? $continueShipment->tracking_number ?? $continueShipment->id }}</div>
                    <p class="b2c-journey-spotlight__body">{{ $continueAction['helper'] }}</p>
                </div>
                <div class="b2c-journey-spotlight__actions">
                    <span class="b2c-status-pill b2c-status-pill--{{ $statusTone($continueShipment->status) }}">{{ $resolveStatus($continueShipment->status) }}</span>
                    <a href="{{ $continueAction['url'] }}" class="btn btn-pr">{{ $continueAction['label'] }}</a>
                </div>
            </article>
        @endif

        <x-card :title="$copy['table_title']">
            <form method="GET" action="{{ $indexRoute }}" class="filter-grid-fluid b2c-filter-form">
                <div class="filter-field-wide">
                    <label for="shipment-search-b2c" class="b2c-form-label">{{ __('portal_shipments.common.search') }}</label>
                    <input
                        id="shipment-search-b2c"
                        type="text"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="{{ $copy['search_placeholder'] }}"
                        class="form-input"
                        data-testid="shipment-search-input"
                    >
                </div>

                <div>
                    <label for="shipment-status-b2c" class="b2c-form-label">{{ __('portal_shipments.common.status') }}</label>
                    <select id="shipment-status-b2c" name="status" class="form-input" data-testid="shipment-status-filter">
                        <option value="">{{ __('portal_shipments.common.all_statuses') }}</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="shipment-carrier-b2c" class="b2c-form-label">{{ __('portal_shipments.common.carrier') }}</label>
                    <select id="shipment-carrier-b2c" name="carrier" class="form-input" data-testid="shipment-carrier-filter">
                        <option value="">{{ __('portal_shipments.common.all_carriers') }}</option>
                        @foreach($carrierOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['carrier'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="shipment-from-b2c" class="b2c-form-label">{{ __('portal_shipments.common.from_date') }}</label>
                    <input id="shipment-from-b2c" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input">
                </div>

                <div>
                    <label for="shipment-to-b2c" class="b2c-form-label">{{ __('portal_shipments.common.to_date') }}</label>
                    <input id="shipment-to-b2c" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input">
                </div>

                <div class="filter-actions b2c-filter-actions">
                    <button type="submit" class="btn btn-s" data-testid="shipment-filter-submit">{{ __('portal_shipments.common.apply_filters') }}</button>
                    @if($hasActiveFilters)
                        <a href="{{ $indexRoute }}" class="btn btn-ghost" data-testid="shipment-filter-reset">{{ __('portal_shipments.common.clear_filters') }}</a>
                    @endif
                    @if($canExportShipments)
                        <a href="{{ $exportRoute }}" class="btn btn-s" data-testid="shipment-export-csv">{{ __('portal_shipments.common.export_csv') }}</a>
                    @endif
                </div>
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('portal_shipments.common.reference') }}</th>
                        <th>{{ __('portal_shipments.index.b2c.destination') }}</th>
                        <th>{{ __('portal_shipments.common.status') }}</th>
                        <th>{{ __('portal_shipments.common.created_at') }}</th>
                        <th>{{ __('portal_shipments.common.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($shipments as $shipment)
                        @php
                            $destination = collect([$shipment->recipient_city, $shipment->recipient_country])
                                ->filter(fn ($value) => filled($value))
                                ->implode(' / ');
                        @endphp
                        <tr>
                            <td>
                                <div class="b2c-table-title">
                                    <a href="{{ route($showRoute, ['id' => $shipment->id]) }}" class="b2c-table-link">
                                        {{ $shipment->reference_number ?? $shipment->tracking_number ?? $shipment->id }}
                                    </a>
                                </div>
                                <div class="b2c-table-meta">{{ $shipment->tracking_number ?: 'لم يصدر رقم تتبع بعد' }}</div>
                            </td>
                            <td>{{ $destination !== '' ? $destination : __('portal_shipments.common.not_specified') }}</td>
                            <td>
                                <span class="b2c-status-pill b2c-status-pill--{{ $statusTone($shipment->status) }}">{{ $resolveStatus($shipment->status) }}</span>
                            </td>
                            <td>{{ optional($shipment->created_at)->format('Y-m-d') ?? $emptyValue }}</td>
                            <td>
                                <div class="b2c-inline-actions">
                                    <a href="{{ route($showRoute, ['id' => $shipment->id]) }}" class="btn btn-s">{{ __('portal_shipments.common.view') }}</a>
                                    @if($canCreateShipment)
                                        <a
                                            href="{{ route($createRouteName, ['clone' => $shipment->id]) }}"
                                            class="btn btn-ghost"
                                            data-testid="shipment-clone-link-{{ $shipment->id }}"
                                        >{{ __('portal_shipments.common.clone_short') }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="b2c-empty-card b2c-empty-card--soft">
                                    <div class="b2c-empty-card__title">{{ $copy['empty_state'] }}</div>
                                    <p class="b2c-empty-card__body">ابدأ أول شحنة من الزر الرئيسي أعلاه، ثم عد إلى هذه الصفحة لمتابعة الحالات والمرجع والتتبع من مكان واحد.</p>
                                    @if($canCreateShipment)
                                        <a href="{{ $createRoute }}" class="btn btn-pr">{{ $copy['create_cta'] }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($hasPagination)
                <div class="b2c-pagination">
                    <div class="b2c-pagination__meta">
                        {{ __('portal_shipments.common.page') }} {{ $currentPage }} {{ __('portal_shipments.common.of') }} {{ $lastPage }}
                    </div>
                    <div class="b2c-inline-actions">
                        @if($shipments->onFirstPage())
                            <span class="btn btn-s b2c-button-disabled">{{ __('portal_shipments.common.previous') }}</span>
                        @else
                            <a href="{{ $shipments->previousPageUrl() }}" class="btn btn-s">{{ __('portal_shipments.common.previous') }}</a>
                        @endif

                        @if($shipments->hasMorePages())
                            <a href="{{ $shipments->nextPageUrl() }}" class="btn btn-s">{{ __('portal_shipments.common.next') }}</a>
                        @else
                            <span class="btn btn-s b2c-button-disabled">{{ __('portal_shipments.common.next') }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </x-card>
    </div>

    <div class="b2c-panel-stack">
        <x-card title="آخر النشاط">
            <div class="b2c-activity-list">
                @forelse($recentActivity as $shipment)
                    <div class="b2c-activity-list__item">
                        <div>
                            <div class="b2c-activity-list__title">{{ $shipment->reference_number ?? $shipment->tracking_number ?? $shipment->id }}</div>
                            <div class="b2c-activity-list__meta">
                                {{ collect([$shipment->recipient_city, $shipment->recipient_country])->filter()->implode(' / ') ?: 'وجهة غير محددة' }}
                            </div>
                        </div>
                        <div class="b2c-activity-list__side">
                            <span class="b2c-status-pill b2c-status-pill--{{ $statusTone($shipment->status) }}">{{ $resolveStatus($shipment->status) }}</span>
                            <span class="b2c-activity-list__date">{{ optional($shipment->updated_at)->diffForHumans() ?? 'غير محدد' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="b2c-inline-empty">لا توجد حركة حديثة بعد على هذا الحساب.</div>
                @endforelse
            </div>
        </x-card>

        <x-card :title="$copy['guidance_title']">
            <div class="b2c-guidance-stack">
                @foreach($copy['guidance_cards'] as $card)
                    <div class="b2c-guidance-card {{ $loop->last ? 'b2c-guidance-card--accent' : '' }}">
                        <div class="b2c-guidance-card__title">{{ $card['title'] }}</div>
                        <div class="b2c-guidance-card__body">{{ $card['body'] }}</div>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>
</div>
@endsection
