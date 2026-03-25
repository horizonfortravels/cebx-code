@extends('layouts.app')
@section('title', ($copy['portal_label'] ?? __('portal_shipments.common.portal_b2c')) . ' | ' . __('portal_shipments.common.shipments'))

@section('content')
@php
    $showRoute = $showRoute ?? 'b2c.shipments.show';
    $emptyValue = __('portal_shipments.common.not_available');
    $hasPagination = method_exists($shipments, 'hasPages') && $shipments->hasPages();
    $currentPage = method_exists($shipments, 'currentPage') ? $shipments->currentPage() : 1;
    $lastPage = method_exists($shipments, 'lastPage') ? $shipments->lastPage() : 1;
    $resolveStatus = static function (?string $status) use ($emptyValue): string {
        if (! $status) {
            return $emptyValue;
        }

        $key = 'portal_shipments.statuses.' . $status;
        $translated = __($key);

        return $translated === $key ? $status : $translated;
    };
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2c.dashboard') }}" style="color:inherit;text-decoration:none">{{ $copy['portal_label'] }}</a>
            <span style="margin:0 6px">/</span>
            <span>{{ __('portal_shipments.common.shipments') }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">{{ $copy['title'] }}</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:720px">{{ $copy['description'] }}</p>
    </div>
    @if($canCreateShipment)
        <a href="{{ $createRoute }}" class="btn btn-pr">{{ $copy['create_cta'] }}</a>
    @endif
</div>

<div class="stats-grid" style="margin-bottom:24px">
    @foreach($stats as $stat)
        <x-stat-card :icon="$stat['icon']" :label="$stat['label']" :value="$stat['value']" />
    @endforeach
</div>

<div class="grid-2">
    <x-card :title="$copy['table_title']">
        <div style="overflow:auto">
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
                        <td class="td-mono">
                            <a href="{{ route($showRoute, ['id' => $shipment->id]) }}" style="color:var(--tx);font-weight:700;text-decoration:none">
                                {{ $shipment->reference_number ?? $shipment->tracking_number ?? $shipment->id }}
                            </a>
                        </td>
                        <td>{{ $destination !== '' ? $destination : __('portal_shipments.common.not_specified') }}</td>
                        <td>{{ $resolveStatus($shipment->status) }}</td>
                        <td>{{ optional($shipment->created_at)->format('Y-m-d') ?? $emptyValue }}</td>
                        <td>
                            <a href="{{ route($showRoute, ['id' => $shipment->id]) }}" class="btn btn-s">{{ __('portal_shipments.common.view') }}</a>
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
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px;padding-top:16px;border-top:1px solid var(--bd)">
                <div style="color:var(--td);font-size:13px">
                    {{ __('portal_shipments.common.page') }} {{ $currentPage }} {{ __('portal_shipments.common.of') }} {{ $lastPage }}
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @if($shipments->onFirstPage())
                        <span class="btn btn-s" style="pointer-events:none;opacity:.55">{{ __('portal_shipments.common.previous') }}</span>
                    @else
                        <a href="{{ $shipments->previousPageUrl() }}" class="btn btn-s">{{ __('portal_shipments.common.previous') }}</a>
                    @endif

                    @if($shipments->hasMorePages())
                        <a href="{{ $shipments->nextPageUrl() }}" class="btn btn-s">{{ __('portal_shipments.common.next') }}</a>
                    @else
                        <span class="btn btn-s" style="pointer-events:none;opacity:.55">{{ __('portal_shipments.common.next') }}</span>
                    @endif
                </div>
            </div>
        @endif
    </x-card>

    <x-card :title="$copy['guidance_title']">
        <div style="display:flex;flex-direction:column;gap:12px">
            @foreach($copy['guidance_cards'] as $card)
                <div style="padding:14px;border:1px solid var(--bd);border-radius:14px;background:{{ $loop->last ? 'rgba(59,130,246,.06)' : 'transparent' }}">
                    <div style="font-weight:700;color:var(--tx)">{{ $card['title'] }}</div>
                    <div style="color:var(--td);font-size:13px;margin-top:4px">{{ $card['body'] }}</div>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
@endsection
