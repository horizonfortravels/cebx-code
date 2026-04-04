@extends('layouts.app')
@section('title', 'إدارة الشحنات')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0">📦 الشحنات</h1>
    <div style="display:flex;gap:10px">
        <a href="{{ route('shipments.export') }}" class="btn btn-s">📥 تصدير</a>
        <a href="{{ route('shipments.create') }}" class="btn btn-pr">+ شحنة جديدة</a>
    </div>
</div>

{{-- Stats --}}
<div class="stats-grid" style="margin-bottom:20px">
    <x-stat-card icon="📦" label="الكل" :value="$allCount ?? 0" />
    <x-stat-card icon="🚚" label="في الطريق" :value="$inTransitCount ?? 0" />
    <x-stat-card icon="✅" label="تم التسليم" :value="$deliveredCount ?? 0" />
    <x-stat-card icon="⏳" label="قيد الانتظار" :value="$pendingCount ?? 0" />
</div>

{{-- Filters + Table --}}
<x-card>
    <form method="GET" action="{{ route('shipments.index') }}" class="quick-search-row" style="margin-bottom:18px">
        <div class="quick-search-actions">
            @foreach([
                ['' , 'الكل'],
                ['pending', 'قيد الانتظار'],
                ['in_transit', 'في الطريق'],
                ['delivered', 'تم التسليم'],
                ['cancelled', 'ملغي'],
            ] as [$val, $label])
                <button type="submit" name="status" value="{{ $val }}"
                    class="btn {{ request('status', '') === $val ? 'btn-pr' : 'btn-s' }}" style="font-size:13px">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث برقم التتبع..."
            class="form-input quick-search-input">
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>رقم التتبع</th><th>المستلم</th><th>الناقل</th><th>الوجهة</th><th>الحالة</th><th>التاريخ</th><th></th>
            </tr></thead>
            <tbody>
                @forelse($shipments as $s)
                    <tr>
                        <td><a href="{{ route('shipments.show', $s) }}" class="td-link td-mono">{{ $s->reference_number }}</a></td>
                        <td>{{ $s->recipient_name }}</td>
                        <td><span class="badge badge-in">{{ $s->carrier_name ?? '—' }}</span></td>
                        <td style="color:var(--td)">{{ $s->recipient_city }}</td>
                        <td><x-badge :status="$s->status" /></td>
                        <td style="font-size:12px;color:var(--tm)">{{ $s->created_at->format('Y-m-d') }}</td>
                        <td><a href="{{ route('shipments.show', $s) }}" class="btn btn-s" style="font-size:12px;padding:5px 14px">عرض</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">لا توجد شحنات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($shipments->hasPages())
        <div style="margin-top:14px">{{ $shipments->links() }}</div>
    @endif
</x-card>
@endsection
