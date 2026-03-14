@extends('layouts.app')
@section('title', 'المنظمات')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">🏢 المنظمات</h1>
    <button class="btn btn-pr" data-modal-open="add-org">+ منظمة جديدة</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="🏢" label="إجمالي المنظمات" :value="$totalOrgs ?? 0" />
    <x-stat-card icon="✅" label="نشطة" :value="$activeOrgs ?? 0" />
    <x-stat-card icon="⏳" label="بانتظار التفعيل" :value="$pendingOrgs ?? 0" />
    <x-stat-card icon="🚫" label="معلقة" :value="$suspendedOrgs ?? 0" />
</div>

{{-- Search --}}
<x-card>
    <form method="GET" action="{{ route('organizations.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:2;min-width:200px">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالاسم أو السجل التجاري..." class="form-input">
        </div>
        <div style="flex:1;min-width:140px">
            <select name="type" class="form-input">
                <option value="">جميع الأنواع</option>
                <option value="individual" {{ request('type') === 'individual' ? 'selected' : '' }}>فرد</option>
                <option value="business" {{ request('type') === 'business' ? 'selected' : '' }}>شركة</option>
            </select>
        </div>
        <div style="flex:1;min-width:140px">
            <select name="status" class="form-input">
                <option value="">جميع الحالات</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشطة</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>بانتظار التفعيل</option>
                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>معلقة</option>
            </select>
        </div>
        <button type="submit" class="btn btn-pr" style="height:42px">بحث</button>
    </form>
</x-card>

{{-- Organizations Table --}}
<x-card>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>المنظمة</th><th>النوع</th><th>السجل التجاري</th><th>المستخدمون</th><th>الشحنات</th><th>الرصيد</th><th>KYC</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($organizations ?? [] as $org)
                    @php
                        $kycMap = ['verified' => ['✅ موثق', 'badge-ac'], 'pending' => ['⏳ معلق', 'badge-wn'], 'not_submitted' => ['❌ غير مقدم', 'badge-dg']];
                        $kycSt = $kycMap[$org->kyc_status ?? 'not_submitted'] ?? ['—', 'badge-td'];
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:36px;height:36px;border-radius:10px;background:rgba(124,58,237,0.1);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#7C3AED">
                                    {{ mb_substr($org->name, 0, 1) }}
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:13px">{{ $org->name }}</div>
                                    <div style="font-size:11px;color:var(--td)">{{ $org->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $org->type === 'individual' ? '👤 فرد' : '🏢 شركة' }}</td>
                        <td class="td-mono">{{ $org->cr_number ?? '—' }}</td>
                        <td>{{ $org->users_count ?? 0 }}</td>
                        <td>{{ number_format($org->shipments_count ?? 0) }}</td>
                        <td style="font-weight:600">SAR {{ number_format($org->wallet_balance ?? 0) }}</td>
                        <td><span class="badge {{ $kycSt[1] }}">{{ $kycSt[0] }}</span></td>
                        <td><span style="color:{{ $org->status === 'active' ? 'var(--ac)' : ($org->status === 'pending' ? 'var(--wn)' : 'var(--dg)') }}">● {{ $org->status === 'active' ? 'نشطة' : ($org->status === 'pending' ? 'معلقة' : 'موقوفة') }}</span></td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <button class="btn btn-s" style="font-size:12px">عرض</button>
                                <button class="btn btn-s" style="font-size:12px">تعديل</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">لا توجد منظمات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($organizations ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $organizations->links() }}</div>
    @endif
</x-card>

<x-modal id="add-org" title="إنشاء منظمة جديدة" wide>
    <form method="POST" action="{{ route('organizations.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">اسم المنظمة</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">النوع</label><select name="type" class="form-input"><option value="business">شركة</option><option value="individual">فرد</option></select></div>
            <div><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" class="form-input" required></div>
            <div><label class="form-label">رقم الهاتف</label><input type="text" name="phone" class="form-input"></div>
            <div><label class="form-label">السجل التجاري</label><input type="text" name="cr_number" class="form-input"></div>
            <div><label class="form-label">الرقم الضريبي</label><input type="text" name="vat_number" class="form-input"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">إنشاء</button>
        </div>
    </form>
</x-modal>
@endsection
