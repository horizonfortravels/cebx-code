@extends('layouts.app')
@section('title', 'البضائع الخطرة (DG)')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">☣️ البضائع الخطرة (DG)</h1>
    <button class="btn btn-pr" data-modal-open="add-dg">+ تصنيف جديد</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="☣️" label="التصنيفات" :value="$classificationsCount ?? 0" />
    <x-stat-card icon="📦" label="شحنات DG نشطة" :value="$activeDgShipments ?? 0" />
    <x-stat-card icon="🚫" label="مرفوضة هذا الشهر" :value="$rejectedThisMonth ?? 0" />
    <x-stat-card icon="📋" label="بانتظار المراجعة" :value="$pendingReview ?? 0" />
</div>

{{-- DG Classifications --}}
<x-card title="📋 تصنيفات البضائع الخطرة">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>الفئة</th><th>الوصف</th><th>UN Number</th><th>مجموعة التعبئة</th><th>القيود</th><th>الحالة</th></tr>
            </thead>
            <tbody>
                @forelse($classifications ?? [] as $cls)
                    @php
                        $classIcons = [1 => '💥', 2 => '🔵', 3 => '🔥', 4 => '🟡', 5 => '🟠', 6 => '☠️', 7 => '☢️', 8 => '🧪', 9 => '⚠️'];
                        $icon = $classIcons[$cls->class_number] ?? '⚠️';
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <span style="font-size:18px">{{ $icon }}</span>
                                <span style="font-weight:600">Class {{ $cls->class_number }}{{ $cls->division ? '.' . $cls->division : '' }}</span>
                            </div>
                        </td>
                        <td>{{ $cls->description }}</td>
                        <td class="td-mono">{{ $cls->un_number ?? '—' }}</td>
                        <td>{{ $cls->packing_group ?? '—' }}</td>
                        <td style="font-size:12px;color:var(--td)">{{ $cls->restrictions ?? 'لا توجد' }}</td>
                        <td><span style="color:{{ $cls->is_allowed ? 'var(--ac)' : 'var(--dg)' }}">● {{ $cls->is_allowed ? 'مسموح' : 'محظور' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">لا توجد تصنيفات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

{{-- DG Shipments Pending Review --}}
<x-card title="⏳ شحنات DG بانتظار المراجعة">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>رقم الشحنة</th><th>المرسل</th><th>التصنيف</th><th>UN#</th><th>الوجهة</th><th>التاريخ</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($pendingDgShipments ?? [] as $shipment)
                    <tr>
                        <td class="td-mono">{{ $shipment->reference_number }}</td>
                        <td>{{ $shipment->sender_name }}</td>
                        <td><span class="badge badge-wn">Class {{ $shipment->dg_class }}</span></td>
                        <td class="td-mono">{{ $shipment->un_number ?? '—' }}</td>
                        <td>{{ $shipment->recipient_city }}, {{ $shipment->recipient_country }}</td>
                        <td>{{ $shipment->created_at->format('Y-m-d') }}</td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <button class="btn btn-s" style="font-size:12px;color:var(--ac)">قبول</button>
                                <button class="btn btn-s" style="font-size:12px;color:var(--dg)">رفض</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">لا توجد شحنات بانتظار المراجعة</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

<x-modal id="add-dg" title="إضافة تصنيف DG">
    <form method="POST" action="{{ route('dg.index') }}">
        @csrf
        <div class="form-grid-2">
            <div><label class="form-label">رقم الفئة</label><input type="number" name="class_number" class="form-input" min="1" max="9" required></div>
            <div><label class="form-label">القسم</label><input type="text" name="division" class="form-input" placeholder="مثال: 1"></div>
            <div style="grid-column:1 / -1"><label class="form-label">الوصف</label><input type="text" name="description" class="form-input" placeholder="وصف التصنيف"></div>
            <div><label class="form-label">UN Number</label><input type="text" name="un_number" class="form-input" placeholder="UN1234"></div>
            <div><label class="form-label">مجموعة التعبئة</label><select name="packing_group" class="form-input"><option value="">—</option><option>I</option><option>II</option><option>III</option></select></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">حفظ</button>
        </div>
    </form>
</x-modal>
@endsection
