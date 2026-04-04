@extends('layouts.app')
@section('title', 'التقارير')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0">📊 التقارير</h1>
    <a href="{{ route('reports.export', 'pdf') }}" class="btn btn-s">📥 تصدير PDF</a>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="📦" label="إجمالي الشحنات" :value="number_format($totalShipments)" />
    <x-stat-card icon="✅" label="نسبة التسليم" :value="$deliveryRate . '%'" />
    <x-stat-card icon="⏱️" label="متوسط التوصيل" :value="round($avgDeliveryDays, 1) . ' يوم'" />
    <x-stat-card icon="💰" label="إجمالي التكاليف" :value="number_format($totalCost)" />
</div>

<div class="grid-2">
    <x-card title="🚚 توزيع الناقلين">
        @php
            $carriers = \App\Models\Shipment::where('account_id', auth()->user()->account_id)
                ->select('carrier_name', \DB::raw('count(*) as total'))
                ->whereNotNull('carrier_name')
                ->groupBy('carrier_name')->orderByDesc('total')->take(5)->get();
            $cTotal = max($carriers->sum('total'), 1);
            $colors = ['#EF4444', '#3B82F6', '#F59E0B', '#8B5CF6', '#10B981'];
        @endphp
        @foreach($carriers as $i => $c)
            @php $pct = round($c->total / $cTotal * 100); @endphp
            <div style="margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                    <span style="color:var(--tx);font-weight:600">{{ $c->carrier_name }}</span>
                    <span style="color:var(--tm)">{{ $pct }}%</span>
                </div>
                <div style="height:8px;background:var(--bg);border-radius:4px">
                    <div style="height:100%;width:{{ $pct }}%;background:{{ $colors[$i] ?? '#94A3B8' }};border-radius:4px"></div>
                </div>
            </div>
        @endforeach
    </x-card>

    <x-card title="🏆 أكثر المدن شحناً">
        @php
            $cities = \App\Models\Shipment::where('account_id', auth()->user()->account_id)
                ->select('recipient_city', \DB::raw('count(*) as total'))
                ->whereNotNull('recipient_city')
                ->groupBy('recipient_city')->orderByDesc('total')->take(5)->get();
        @endphp
        <table style="width:100%">
            <tbody>
                @foreach($cities as $i => $city)
                    <tr style="border-bottom:1px solid var(--sf)">
                        <td style="padding:10px 8px;font-size:13px;font-weight:600">{{ $i + 1 }}. {{ $city->recipient_city }}</td>
                        <td style="padding:10px 8px;font-size:13px;color:var(--pr);font-weight:700;text-align:left">{{ $city->total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
</div>
@endsection
