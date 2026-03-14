@extends('layouts.app')
@section('title', 'بوابة الأعمال | الطلبات')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2b.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأعمال</a>
            <span style="margin:0 6px">/</span>
            <span>الطلبات</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">مركز الطلبات التجارية</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            اعرض الطلبات القادمة من متاجرك، وراجع الحالات الحالية قبل الانتقال إلى شاشة الطلبات الكاملة للمزامنة أو الشحن.
        </p>
    </div>
    <a href="{{ route('orders.index') }}" class="btn btn-pr">فتح إدارة الطلبات</a>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    @foreach($stats as $stat)
        <x-stat-card :icon="$stat['icon']" :label="$stat['label']" :value="$stat['value']" />
    @endforeach
</div>

<x-card title="آخر الطلبات">
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>رقم الطلب</th>
                <th>المتجر</th>
                <th>الحالة</th>
                <th>المبلغ</th>
            </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td class="td-mono">{{ $order->external_order_number ?? $order->external_order_id ?? $order->id }}</td>
                    <td>{{ $order->store->name ?? '—' }}</td>
                    <td>{{ $order->status ?? '—' }}</td>
                    <td>{{ number_format((float) ($order->total_amount ?? 0), 2) }} {{ $order->currency ?? 'SAR' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty-state">لا توجد طلبات حديثة. افتح إدارة الطلبات لمزامنة المتاجر أو متابعة التنفيذ.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
