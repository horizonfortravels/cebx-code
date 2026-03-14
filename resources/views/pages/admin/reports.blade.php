@extends('layouts.app')
@section('title', 'تقارير الحساب')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('admin.index') }}" style="color:inherit;text-decoration:none">الإدارة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('admin.tenant-context') }}" style="color:inherit;text-decoration:none">اختيار الحساب</a>
            <span style="margin:0 6px">/</span>
            <span>تقارير الحساب</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تقارير الحساب</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:720px">
            هذه الصفحة تلخص أداء الحساب <strong>{{ $selectedAccount->name }}</strong> عبر الشحنات والطلبات والمتاجر والمستخدمين، وتمنح الإدارة الداخلية نقطة دخول أوضح قبل التحقيق أو المراجعة.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('admin.tenant-context') }}" class="btn btn-s">تبديل الحساب</a>
        <a href="{{ route('admin.reports') }}" class="btn btn-pr">تحديث التقرير</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    @foreach($stats as $stat)
        <x-stat-card :icon="$stat['icon']" :label="$stat['label']" :value="$stat['value']" />
    @endforeach
</div>

<div class="grid-2">
    <x-card title="أحدث الشحنات">
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($recentShipments as $shipment)
                <div style="padding-bottom:12px;border-bottom:1px solid var(--bd)">
                    <div style="font-weight:600;color:var(--tx)">{{ $shipment->tracking_number ?? $shipment->id }}</div>
                    <div style="font-size:12px;color:var(--td)">{{ $shipment->status ?? '—' }} • {{ optional($shipment->created_at)->format('Y-m-d H:i') ?? '—' }}</div>
                </div>
            @empty
                <div class="empty-state">لا توجد شحنات حديثة لهذا الحساب.</div>
            @endforelse
        </div>
    </x-card>

    <x-card title="أحدث الطلبات">
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($recentOrders as $order)
                <div style="padding-bottom:12px;border-bottom:1px solid var(--bd)">
                    <div style="font-weight:600;color:var(--tx)">{{ $order->external_order_id ?? $order->id }}</div>
                    <div style="font-size:12px;color:var(--td)">{{ $order->status ?? '—' }} • {{ optional($order->created_at)->format('Y-m-d H:i') ?? '—' }}</div>
                </div>
            @empty
                <div class="empty-state">لا توجد طلبات حديثة لهذا الحساب.</div>
            @endforelse
        </div>
    </x-card>
</div>
@endsection
