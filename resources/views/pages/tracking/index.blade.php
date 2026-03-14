@extends('layouts.app')
@section('title', 'التتبع')

@section('content')
<div style="text-align:center;padding:40px 0 32px">
    <div style="font-size:48px;margin-bottom:16px">🔍</div>
    <h1 style="font-size:28px;font-weight:700;color:var(--tx);margin:0 0 8px">تتبع شحنتك</h1>
    <p style="color:var(--td);font-size:15px">أدخل رقم التتبع لمعرفة حالة شحنتك</p>
</div>

<div style="max-width:600px;margin:0 auto 40px">
    <form action="{{ route('tracking.index') }}" method="GET" style="display:flex;gap:12px">
        <div style="flex:1">
            <input type="text" name="tracking_number" value="{{ request('tracking_number') }}"
                   placeholder="أدخل رقم التتبع... مثال: SHP-20261847"
                   class="form-input" style="width:100%;height:56px;font-size:18px">
        </div>
        <button type="submit" class="btn btn-pr" style="height:56px;padding:0 32px;border-radius:14px;font-size:16px">تتبع</button>
    </form>
</div>

@if($trackedShipment)
    <x-card>
        <div style="max-width:700px;margin:0 auto">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
                <div>
                    <div style="font-family:monospace;color:var(--pr);font-weight:700;font-size:20px">{{ $trackedShipment->reference_number }}</div>
                    <div style="font-size:13px;color:var(--td);margin-top:4px">
                        {{ $trackedShipment->carrier_name ?? '' }} •
                        {{ $trackedShipment->sender_city }} → {{ $trackedShipment->recipient_city }}
                    </div>
                </div>
                <x-badge :status="$trackedShipment->status" />
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
                @foreach([
                    ['الوزن', ($trackedShipment->weight ?? '—') . ' كغ'],
                    ['القطع', $trackedShipment->pieces ?? 1],
                    ['COD', $trackedShipment->is_cod ? number_format($trackedShipment->cod_amount) . ' ر.س' : '—'],
                    ['التكلفة', number_format($trackedShipment->total_cost, 2) . ' ر.س'],
                ] as $info)
                    <div style="padding:14px;background:var(--sf);border-radius:10px;text-align:center">
                        <div style="font-size:11px;color:var(--td)">{{ $info[0] }}</div>
                        <div style="font-weight:600;color:var(--tx);margin-top:4px">{{ $info[1] }}</div>
                    </div>
                @endforeach
            </div>

            <x-timeline :items="$trackingHistory" />

            <a href="{{ route('shipments.show', $trackedShipment) }}" class="btn btn-pr" style="width:100%;text-align:center;margin-top:16px;display:block">عرض التفاصيل الكاملة</a>
        </div>
    </x-card>
@endif

{{-- Active Shipments --}}
<x-card title="📦 شحناتي النشطة" style="margin-top:24px">
    @forelse($activeShipments as $shipment)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;{{ !$loop->last ? 'border-bottom:1px solid var(--bd)' : '' }};cursor:pointer"
             onclick="window.location='{{ route('tracking.index', ['tracking_number' => $shipment->reference_number]) }}'">
            <div>
                <span style="font-family:monospace;color:var(--pr);font-weight:600">{{ $shipment->reference_number }}</span>
                <span style="color:var(--td);font-size:13px;margin-right:12px">{{ $shipment->sender_city }} → {{ $shipment->recipient_city }}</span>
            </div>
            <x-badge :status="$shipment->status" />
        </div>
    @empty
        <div class="empty-state">لا توجد شحنات نشطة</div>
    @endforelse
</x-card>
@endsection
