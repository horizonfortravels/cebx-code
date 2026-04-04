@extends('layouts.app')
@section('title', 'الجمارك')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">🛃 الجمارك</h1>
    <div style="display:flex;gap:10px">
        <a href="{{ route('reports.export', 'customs') }}" class="btn btn-s">📥 تصدير</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="📋" label="إجمالي البيانات" :value="$totalDeclarations ?? 0" />
    <x-stat-card icon="⏳" label="قيد التخليص" :value="$pendingClearance ?? 0" />
    <x-stat-card icon="✅" label="تم التخليص" :value="$clearedCount ?? 0" />
    <x-stat-card icon="🚫" label="محتجزة" :value="$heldCount ?? 0" />
</div>

<x-card>
    <form method="GET" action="{{ route('customs.index') }}" class="filter-grid-fluid" style="margin-bottom:16px">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث برقم البيان الجمركي أو الشحنة..." class="form-input filter-field-wide">
        <select name="status" class="form-input">
            <option value="">جميع الحالات</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>قيد التخليص</option>
            <option value="cleared" {{ request('status') === 'cleared' ? 'selected' : '' }}>تم التخليص</option>
            <option value="held" {{ request('status') === 'held' ? 'selected' : '' }}>محتجزة</option>
            <option value="inspection" {{ request('status') === 'inspection' ? 'selected' : '' }}>قيد الفحص</option>
        </select>
        <div class="filter-actions filter-actions-wide">
            <button type="submit" class="btn btn-pr">بحث</button>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>رقم البيان</th><th>رقم الشحنة</th><th>النوع</th><th>HS Code</th><th>القيمة</th><th>الرسوم</th><th>الميناء</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($declarations ?? [] as $dec)
                    @php
                        $stMap = ['pending' => ['⏳ قيد التخليص', 'badge-wn'], 'cleared' => ['✅ تم التخليص', 'badge-ac'], 'held' => ['🚫 محتجزة', 'badge-dg'], 'inspection' => ['🔍 قيد الفحص', 'badge-in']];
                        $st = $stMap[$dec->status] ?? ['—', 'badge-td'];
                    @endphp
                    <tr>
                        <td class="td-mono" style="font-weight:600">{{ $dec->declaration_number }}</td>
                        <td><a href="{{ route('shipments.show', $dec->shipment_id ?? 0) }}" class="td-link td-mono">{{ $dec->shipment->reference_number ?? '—' }}</a></td>
                        <td>{{ $dec->type === 'import' ? '📥 استيراد' : '📤 تصدير' }}</td>
                        <td class="td-mono">{{ $dec->hs_code ?? '—' }}</td>
                        <td>SAR {{ number_format($dec->declared_value ?? 0) }}</td>
                        <td style="font-weight:600">SAR {{ number_format($dec->duty_amount ?? 0) }}</td>
                        <td>{{ $dec->port_name ?? '—' }}</td>
                        <td><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></td>
                        <td><button class="btn btn-s" style="font-size:12px">تفاصيل</button></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">لا توجد بيانات جمركية</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($declarations ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $declarations->links() }}</div>
    @endif
</x-card>
@endsection
