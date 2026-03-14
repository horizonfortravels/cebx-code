@extends('layouts.app')
@section('title', 'جداول الرحلات')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">📅 جداول الرحلات</h1>
    <button class="btn btn-pr" data-modal-open="add-schedule">+ جدول جديد</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="📅" label="إجمالي الرحلات" :value="$totalSchedules ?? 0" />
    <x-stat-card icon="🟢" label="نشطة" :value="$activeCount ?? 0" />
    <x-stat-card icon="⏰" label="القادمة (7 أيام)" :value="$upcomingCount ?? 0" />
    <x-stat-card icon="⚠️" label="متأخرة" :value="$delayedCount ?? 0" />
</div>

{{-- Filters --}}
<x-card>
    <form method="GET" action="{{ route('schedules.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:160px">
            <label class="form-label">ميناء المغادرة</label>
            <select name="origin" class="form-input"><option value="">الكل</option>
                @foreach($ports ?? [] as $port)
                    <option value="{{ $port->code }}" {{ request('origin') === $port->code ? 'selected' : '' }}>{{ $port->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1;min-width:160px">
            <label class="form-label">ميناء الوصول</label>
            <select name="destination" class="form-input"><option value="">الكل</option>
                @foreach($ports ?? [] as $port)
                    <option value="{{ $port->code }}" {{ request('destination') === $port->code ? 'selected' : '' }}>{{ $port->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1;min-width:140px">
            <label class="form-label">من تاريخ</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-input">
        </div>
        <div style="flex:1;min-width:140px">
            <label class="form-label">إلى تاريخ</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-input">
        </div>
        <button type="submit" class="btn btn-pr" style="height:42px">بحث</button>
    </form>
</x-card>

<x-card>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>رقم الرحلة</th><th>السفينة</th><th>ميناء المغادرة</th><th>ميناء الوصول</th><th>تاريخ المغادرة</th><th>تاريخ الوصول</th><th>المدة</th><th>الحالة</th></tr>
            </thead>
            <tbody>
                @forelse($schedules ?? [] as $schedule)
                    @php
                        $stMap = ['scheduled' => ['📅 مجدول', 'badge-in'], 'departed' => ['🚢 انطلقت', 'badge-pp'], 'arrived' => ['✅ وصلت', 'badge-ac'], 'delayed' => ['⚠️ متأخرة', 'badge-dg'], 'cancelled' => ['❌ ملغاة', 'badge-td']];
                        $st = $stMap[$schedule->status] ?? ['—', 'badge-td'];
                        $duration = $schedule->departure_date && $schedule->arrival_date
                            ? $schedule->departure_date->diffInDays($schedule->arrival_date) . ' يوم'
                            : '—';
                    @endphp
                    <tr>
                        <td class="td-mono" style="font-weight:600">{{ $schedule->voyage_number }}</td>
                        <td>{{ $schedule->vessel->name ?? '—' }}</td>
                        <td>{{ $schedule->origin_port }}</td>
                        <td>{{ $schedule->destination_port }}</td>
                        <td>{{ $schedule->departure_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $schedule->arrival_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $duration }}</td>
                        <td><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">لا توجد رحلات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($schedules ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $schedules->links() }}</div>
    @endif
</x-card>

<x-modal id="add-schedule" title="إضافة جدول رحلة" wide>
    <form method="POST" action="{{ route('schedules.index') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">رقم الرحلة</label><input type="text" name="voyage_number" class="form-input" required></div>
            <div><label class="form-label">السفينة</label><select name="vessel_id" class="form-input"><option value="">— اختر —</option></select></div>
            <div><label class="form-label">ميناء المغادرة</label><input type="text" name="origin_port" class="form-input" required></div>
            <div><label class="form-label">ميناء الوصول</label><input type="text" name="destination_port" class="form-input" required></div>
            <div><label class="form-label">تاريخ المغادرة</label><input type="datetime-local" name="departure_date" class="form-input" required></div>
            <div><label class="form-label">تاريخ الوصول المتوقع</label><input type="datetime-local" name="arrival_date" class="form-input" required></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">إضافة</button>
        </div>
    </form>
</x-modal>
@endsection
