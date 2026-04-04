@extends('layouts.app')
@section('title', 'السفن')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">⛴️ السفن</h1>
    <button class="btn btn-pr" data-modal-open="add-vessel">+ سفينة جديدة</button>
</div>

@php
    $vesselTypeLabels = [
        'container' => 'سفينة حاويات',
        'bulk' => 'ناقلة بضائع سائبة',
        'tanker' => 'ناقلة نفط',
        'roro' => 'رول أون/رول أوف',
        'سفينة حاويات' => 'سفينة حاويات',
        'ناقلة بضائع سائبة' => 'ناقلة بضائع سائبة',
        'ناقلة نفط' => 'ناقلة نفط',
        'رول أون/رول أوف' => 'رول أون/رول أوف',
    ];
@endphp

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="⛴️" label="إجمالي السفن" :value="$totalVessels ?? 0" />
    <x-stat-card icon="🌊" label="في البحر" :value="$atSeaCount ?? 0" />
    <x-stat-card icon="⚓" label="في الميناء" :value="$dockedCount ?? 0" />
    <x-stat-card icon="🔧" label="صيانة" :value="$maintenanceCount ?? 0" />
</div>

<x-card>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>اسم السفينة</th><th>الرقم الدولي</th><th>النوع</th><th>الحمولة بوحدة 20 قدم</th><th>العلم</th><th>الموقع الحالي</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($vessels ?? [] as $vessel)
                    @php
                        $vesselType = trim((string) $vessel->type);
                        $vesselTypeKey = mb_strtolower($vesselType);
                        $stMap = ['at_sea' => ['🌊 في البحر', 'badge-in'], 'docked' => ['⚓ في الميناء', 'badge-ac'], 'maintenance' => ['🔧 صيانة', 'badge-wn'], 'idle' => ['⏸️ متوقفة', 'badge-td']];
                        $st = $stMap[$vessel->status] ?? ['—', 'badge-td'];
                    @endphp
                    <tr>
                        <td style="font-weight:600">{{ $vessel->name }}</td>
                        <td class="td-mono">{{ $vessel->imo_number }}</td>
                        <td>{{ $vesselTypeLabels[$vesselTypeKey] ?? ($vesselType !== '' ? $vesselType : 'غير معروف') }}</td>
                        <td>{{ number_format($vessel->capacity_teu ?? 0) }} وحدة 20 قدم</td>
                        <td>{{ $vessel->flag ?? '—' }}</td>
                        <td>{{ $vessel->current_location ?? '—' }}</td>
                        <td><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></td>
                        <td><button class="btn btn-s" style="font-size:12px">تفاصيل</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">لا توجد سفن</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($vessels ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $vessels->links() }}</div>
    @endif
</x-card>

<x-modal id="add-vessel" title="إضافة سفينة" wide>
    <form method="POST" action="{{ route('vessels.index') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">اسم السفينة</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">الرقم الدولي للسفينة</label><input type="text" name="imo_number" class="form-input" required></div>
            <div><label class="form-label">النوع</label><select name="type" class="form-input"><option>سفينة حاويات</option><option>ناقلة بضائع سائبة</option><option>ناقلة نفط</option><option>رول أون/رول أوف</option></select></div>
            <div><label class="form-label">السعة بوحدة 20 قدم</label><input type="number" name="capacity_teu" class="form-input"></div>
            <div><label class="form-label">العلم</label><input type="text" name="flag" class="form-input" placeholder="مثال: SA"></div>
            <div><label class="form-label">الشركة المالكة</label><input type="text" name="owner_company" class="form-input"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">إضافة</button>
        </div>
    </form>
</x-modal>
@endsection
