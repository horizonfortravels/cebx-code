@extends('layouts.app')
@section('title', 'السائقين')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">🚛 السائقين</h1>
    <button class="btn btn-pr" data-modal-open="add-driver">+ سائق جديد</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="🚛" label="إجمالي السائقين" :value="$totalDrivers ?? 0" />
    <x-stat-card icon="🟢" label="متاح" :value="$availableCount ?? 0" />
    <x-stat-card icon="🔵" label="في مهمة" :value="$onDutyCount ?? 0" />
    <x-stat-card icon="🔴" label="غير متاح" :value="$offDutyCount ?? 0" />
</div>

<x-card>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>السائق</th><th>رقم الهاتف</th><th>رقم الرخصة</th><th>المركبة</th><th>المنطقة</th><th>التوصيلات</th><th>التقييم</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($drivers ?? [] as $driver)
                    @php
                        $stMap = ['available' => ['متاح', 'var(--ac)'], 'on_duty' => ['في مهمة', 'var(--pr)'], 'off_duty' => ['غير متاح', 'var(--dg)'], 'on_leave' => ['إجازة', 'var(--wn)']];
                        $st = $stMap[$driver->status] ?? ['—', 'var(--td)'];
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="user-avatar" style="background:rgba(124,58,237,0.15);color:#7C3AED">{{ mb_substr($driver->name, 0, 1) }}</div>
                                <div>
                                    <div style="font-weight:600;font-size:13px">{{ $driver->name }}</div>
                                    <div style="font-size:11px;color:var(--td)">{{ $driver->employee_id ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="td-mono">{{ $driver->phone }}</td>
                        <td class="td-mono">{{ $driver->license_number }}</td>
                        <td>{{ $driver->vehicle_plate ?? '—' }}</td>
                        <td>{{ $driver->region ?? '—' }}</td>
                        <td style="font-weight:600">{{ number_format($driver->deliveries_count ?? 0) }}</td>
                        <td>
                            @php $rating = $driver->rating ?? 0; @endphp
                            <span style="color:#F59E0B">{{ str_repeat('★', (int)$rating) }}{{ str_repeat('☆', 5 - (int)$rating) }}</span>
                            <span style="font-size:11px;color:var(--td)">({{ number_format($rating, 1) }})</span>
                        </td>
                        <td><span style="color:{{ $st[1] }}">● {{ $st[0] }}</span></td>
                        <td><button class="btn btn-s" style="font-size:12px">تعديل</button></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">لا يوجد سائقون</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($drivers ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $drivers->links() }}</div>
    @endif
</x-card>

<x-modal id="add-driver" title="إضافة سائق جديد" wide>
    <form method="POST" action="{{ route('drivers.index') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">الاسم الكامل</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">رقم الهاتف</label><input type="text" name="phone" class="form-input" required></div>
            <div><label class="form-label">رقم الرخصة</label><input type="text" name="license_number" class="form-input" required></div>
            <div><label class="form-label">لوحة المركبة</label><input type="text" name="vehicle_plate" class="form-input"></div>
            <div><label class="form-label">المنطقة</label><input type="text" name="region" class="form-input" placeholder="مثال: الرياض"></div>
            <div><label class="form-label">الرقم الوظيفي</label><input type="text" name="employee_id" class="form-input"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">إضافة</button>
        </div>
    </form>
</x-modal>
@endsection
