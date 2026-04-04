@extends('layouts.app')
@section('title', 'الشركات')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">🏭 الشركات</h1>
    <button class="btn btn-pr" data-modal-open="add-company">+ شركة جديدة</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="🏭" label="إجمالي الشركات" :value="$totalCompanies ?? 0" />
    <x-stat-card icon="🚚" label="ناقلين" :value="$carriersCount ?? 0" />
    <x-stat-card icon="🏪" label="وكلاء" :value="$agentsCount ?? 0" />
    <x-stat-card icon="✅" label="نشطة" :value="$activeCount ?? 0" />
</div>

<x-card>
    <form method="GET" action="{{ route('companies.index') }}" class="filter-grid-fluid" style="margin-bottom:16px">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالاسم أو الكود..." class="form-input filter-field-wide">
        <select name="type" class="form-input">
            <option value="">جميع الأنواع</option>
            <option value="carrier" {{ request('type') === 'carrier' ? 'selected' : '' }}>ناقل</option>
            <option value="agent" {{ request('type') === 'agent' ? 'selected' : '' }}>وكيل</option>
            <option value="partner" {{ request('type') === 'partner' ? 'selected' : '' }}>شريك</option>
        </select>
        <div class="filter-actions filter-actions-wide">
            <button type="submit" class="btn btn-pr">بحث</button>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>الشركة</th><th>الكود</th><th>النوع</th><th>البلد</th><th>التواصل</th><th>الشحنات</th><th>التقييم</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($companies ?? [] as $company)
                    @php
                        $typeMap = ['carrier' => ['🚚 ناقل', 'badge-in'], 'agent' => ['🏪 وكيل', 'badge-pp'], 'partner' => ['🤝 شريك', 'badge-ac']];
                        $tp = $typeMap[$company->type] ?? ['—', 'badge-td'];
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:36px;height:36px;border-radius:10px;background:rgba(124,58,237,0.1);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#7C3AED">
                                    {{ mb_substr($company->name, 0, 2) }}
                                </div>
                                <span style="font-weight:600;font-size:13px">{{ $company->name }}</span>
                            </div>
                        </td>
                        <td class="td-mono">{{ $company->code }}</td>
                        <td><span class="badge {{ $tp[1] }}">{{ $tp[0] }}</span></td>
                        <td>{{ $company->country ?? '—' }}</td>
                        <td style="font-size:12px">{{ $company->contact_email ?? '—' }}</td>
                        <td style="font-weight:600">{{ number_format($company->shipments_count ?? 0) }}</td>
                        <td>
                            @php $rating = $company->rating ?? 0; @endphp
                            <span style="color:#F59E0B">{{ str_repeat('★', (int)$rating) }}{{ str_repeat('☆', 5 - (int)$rating) }}</span>
                        </td>
                        <td><span style="color:{{ $company->is_active ? 'var(--ac)' : 'var(--dg)' }}">● {{ $company->is_active ? 'نشطة' : 'معطلة' }}</span></td>
                        <td><button class="btn btn-s" style="font-size:12px">تعديل</button></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">لا توجد شركات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($companies ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $companies->links() }}</div>
    @endif
</x-card>

<x-modal id="add-company" title="إضافة شركة جديدة" wide>
    <form method="POST" action="{{ route('companies.index') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">اسم الشركة</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">الكود</label><input type="text" name="code" class="form-input" placeholder="مثال: DHL" required></div>
            <div><label class="form-label">النوع</label><select name="type" class="form-input"><option value="carrier">ناقل</option><option value="agent">وكيل</option><option value="partner">شريك</option></select></div>
            <div><label class="form-label">البلد</label><input type="text" name="country" class="form-input"></div>
            <div><label class="form-label">البريد الإلكتروني</label><input type="email" name="contact_email" class="form-input"></div>
            <div><label class="form-label">رقم الهاتف</label><input type="text" name="contact_phone" class="form-input"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">إضافة</button>
        </div>
    </form>
</x-modal>
@endsection
