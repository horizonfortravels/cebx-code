@extends('layouts.app')
@section('title', 'KYC - التحقق من الهوية')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">🪪 KYC — التحقق من الهوية</h1>
    <div style="display:flex;gap:10px">
        <select class="form-input" style="width:auto" onchange="window.location=this.value">
            <option value="{{ route('kyc.index') }}" {{ request('status') ? '' : 'selected' }}>جميع الحالات</option>
            <option value="{{ route('kyc.index', ['status' => 'pending']) }}" {{ request('status') === 'pending' ? 'selected' : '' }}>بانتظار المراجعة</option>
            <option value="{{ route('kyc.index', ['status' => 'approved']) }}" {{ request('status') === 'approved' ? 'selected' : '' }}>مقبول</option>
            <option value="{{ route('kyc.index', ['status' => 'rejected']) }}" {{ request('status') === 'rejected' ? 'selected' : '' }}>مرفوض</option>
        </select>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="📋" label="إجمالي الطلبات" :value="$totalRequests ?? 0" />
    <x-stat-card icon="⏳" label="بانتظار المراجعة" :value="$pendingCount ?? 0" />
    <x-stat-card icon="✅" label="مقبول" :value="$approvedCount ?? 0" />
    <x-stat-card icon="❌" label="مرفوض" :value="$rejectedCount ?? 0" />
</div>

<x-card>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>المنظمة</th><th>نوع الطلب</th><th>المستندات</th><th>تاريخ التقديم</th><th>الحالة</th><th>المراجع</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($kycRequests ?? [] as $kyc)
                    @php
                        $statusMap = ['pending' => ['⏳ بانتظار المراجعة', 'badge-wn'], 'approved' => ['✅ مقبول', 'badge-ac'], 'rejected' => ['❌ مرفوض', 'badge-dg'], 'under_review' => ['🔍 قيد المراجعة', 'badge-in']];
                        $st = $statusMap[$kyc->status] ?? ['—', 'badge-td'];
                        $docCount = $kyc->documents_count ?? 0;
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="width:32px;height:32px;border-radius:8px;background:rgba(124,58,237,0.1);display:flex;align-items:center;justify-content:center;font-size:14px">🏢</div>
                                <div>
                                    <div style="font-weight:600;font-size:13px">{{ $kyc->organization->name ?? '—' }}</div>
                                    <div style="font-size:11px;color:var(--td)">{{ $kyc->organization->cr_number ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $kyc->type === 'individual' ? '👤 فرد' : '🏢 شركة' }}</td>
                        <td><span class="badge badge-in">{{ $docCount }} مستند</span></td>
                        <td>{{ $kyc->created_at->format('Y-m-d') }}</td>
                        <td><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></td>
                        <td>{{ $kyc->reviewer->name ?? '—' }}</td>
                        <td>
                            <div style="display:flex;gap:6px">
                                @if($kyc->status === 'pending' || $kyc->status === 'under_review')
                                    <button class="btn btn-s" style="font-size:12px;color:var(--ac)">قبول</button>
                                    <button class="btn btn-s" style="font-size:12px;color:var(--dg)">رفض</button>
                                @endif
                                <button class="btn btn-s" style="font-size:12px">عرض</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">لا توجد طلبات KYC</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($kycRequests ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $kycRequests->links() }}</div>
    @endif
</x-card>
@endsection
