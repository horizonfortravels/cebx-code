@extends('layouts.app')
@section('title', 'المطالبات')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">📋 المطالبات</h1>
    <button class="btn btn-pr" data-modal-open="new-claim">+ مطالبة جديدة</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="📋" label="إجمالي المطالبات" :value="$totalClaims ?? 0" />
    <x-stat-card icon="⏳" label="قيد المراجعة" :value="$pendingCount ?? 0" />
    <x-stat-card icon="✅" label="تمت الموافقة" :value="$approvedCount ?? 0" />
    <x-stat-card icon="💰" label="إجمالي التعويضات" :value="'SAR ' . number_format($totalCompensation ?? 0)" />
</div>

<x-card>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>رقم المطالبة</th><th>رقم الشحنة</th><th>النوع</th><th>المبلغ</th><th>تاريخ التقديم</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($claims ?? [] as $claim)
                    @php
                        $typeMap = ['damage' => '💔 تلف', 'loss' => '❌ فقدان', 'delay' => '⏰ تأخير', 'overcharge' => '💲 مبالغة في الرسوم'];
                        $stMap = ['pending' => ['⏳ قيد المراجعة', 'badge-wn'], 'investigating' => ['🔍 قيد التحقيق', 'badge-in'], 'approved' => ['✅ موافق عليها', 'badge-ac'], 'rejected' => ['❌ مرفوضة', 'badge-dg'], 'paid' => ['💰 تم الدفع', 'badge-ac']];
                        $st = $stMap[$claim->status] ?? ['—', 'badge-td'];
                    @endphp
                    <tr>
                        <td class="td-mono" style="font-weight:600">#CLM-{{ str_pad($claim->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td><a href="{{ route('shipments.show', $claim->shipment_id ?? 0) }}" class="td-link td-mono">{{ $claim->shipment->reference_number ?? '—' }}</a></td>
                        <td>{{ $typeMap[$claim->type] ?? $claim->type }}</td>
                        <td style="font-weight:600">SAR {{ number_format($claim->amount, 2) }}</td>
                        <td>{{ $claim->created_at->format('Y-m-d') }}</td>
                        <td><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></td>
                        <td>
                            <div style="display:flex;gap:6px">
                                @if($claim->status === 'pending')
                                    <button class="btn btn-s" style="font-size:12px;color:var(--ac)">موافقة</button>
                                    <button class="btn btn-s" style="font-size:12px;color:var(--dg)">رفض</button>
                                @endif
                                <button class="btn btn-s" style="font-size:12px">عرض</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">لا توجد مطالبات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($claims ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $claims->links() }}</div>
    @endif
</x-card>

<x-modal id="new-claim" title="تقديم مطالبة جديدة" wide>
    <form method="POST" action="{{ route('claims.index') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">رقم الشحنة</label><input type="text" name="shipment_reference" class="form-input" placeholder="SHP-XXXXX" required></div>
            <div><label class="form-label">نوع المطالبة</label><select name="type" class="form-input"><option value="damage">تلف</option><option value="loss">فقدان</option><option value="delay">تأخير</option><option value="overcharge">مبالغة في الرسوم</option></select></div>
            <div><label class="form-label">المبلغ المطلوب (SAR)</label><input type="number" name="amount" class="form-input" step="0.01" required></div>
            <div><label class="form-label">المرفقات</label><input type="file" name="attachments[]" class="form-input" multiple></div>
            <div style="grid-column:span 2"><label class="form-label">الوصف</label><textarea name="description" class="form-input" rows="3" placeholder="وصف تفصيلي للمطالبة..."></textarea></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">تقديم</button>
        </div>
    </form>
</x-modal>
@endsection
