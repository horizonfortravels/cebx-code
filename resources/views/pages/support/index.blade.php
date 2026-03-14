@extends('layouts.app')
@section('title', 'الدعم والمساعدة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0">🎧 الدعم والمساعدة</h1>
    <button type="button" class="btn btn-pr" data-modal-open="newTicket">+ تذكرة جديدة</button>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <x-stat-card icon="🎫" label="إجمالي التذاكر" :value="($tickets ?? collect())->total() ?? 0" />
    <x-stat-card icon="🟢" label="مفتوحة" :value="$openCount ?? 0" />
    <x-stat-card icon="✅" label="تم الحل" :value="$resolvedCount ?? 0" />
</div>

<x-card>
    <div class="table-wrap">
        <table>
            <thead><tr><th>الرقم</th><th>الموضوع</th><th>الفئة</th><th>الأولوية</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
                @forelse($tickets as $ticket)
                    @php
                        $prColors = ['low' => 'badge-td', 'medium' => 'badge-wn', 'high' => 'badge-dg', 'urgent' => 'badge-dg'];
                        $prLabels = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة'];
                        $catLabels = ['general' => 'عامة', 'shipment' => 'شحنات', 'billing' => 'مالية', 'technical' => 'تقنية'];
                    @endphp
                    <tr>
                        <td class="td-mono" style="color:var(--pr);font-weight:600">{{ $ticket->reference_number }}</td>
                        <td style="font-weight:600">{{ $ticket->subject }}</td>
                        <td><span class="badge badge-in">{{ $catLabels[$ticket->category] ?? $ticket->category }}</span></td>
                        <td><span class="badge {{ $prColors[$ticket->priority] ?? 'badge-td' }}">{{ $prLabels[$ticket->priority] ?? $ticket->priority }}</span></td>
                        <td><x-badge :status="$ticket->status" /></td>
                        <td style="font-size:12px;color:var(--tm)">{{ $ticket->created_at->format('Y-m-d') }}</td>
                        <td><a href="{{ route('support.show', $ticket) }}" class="btn btn-s" style="font-size:12px;padding:5px 14px">عرض</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">لا توجد تذاكر</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($tickets, 'hasPages') && $tickets->hasPages())
        <div style="margin-top:14px">{{ $tickets->links() }}</div>
    @endif
</x-card>

<x-modal id="newTicket" title="تذكرة دعم جديدة">
    <form method="POST" action="{{ route('support.store') }}">
        @csrf
        <div style="margin-bottom:14px"><label class="form-label">الموضوع</label><input type="text" name="subject" class="form-input" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
            <div><label class="form-label">الفئة</label><select name="category" class="form-input"><option value="general">عامة</option><option value="shipment">شحنات</option><option value="billing">مالية</option><option value="technical">تقنية</option></select></div>
            <div><label class="form-label">الأولوية</label><select name="priority" class="form-input"><option value="low">منخفضة</option><option value="medium" selected>متوسطة</option><option value="high">عالية</option><option value="urgent">عاجلة</option></select></div>
        </div>
        <div style="margin-bottom:16px"><label class="form-label">التفاصيل</label><textarea name="body" class="form-input" rows="4" required></textarea></div>
        <button type="submit" class="btn btn-pr" style="width:100%">إرسال التذكرة</button>
    </form>
</x-modal>
@endsection
