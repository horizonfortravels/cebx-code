@extends('layouts.app')
@section('title', 'الفروع')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">🏬 الفروع</h1>
    <button class="btn btn-pr" data-modal-open="add-branch">+ فرع جديد</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="🏬" label="إجمالي الفروع" :value="$totalBranches ?? 0" />
    <x-stat-card icon="✅" label="نشط" :value="$activeCount ?? 0" />
    <x-stat-card icon="🔴" label="معطّل" :value="$inactiveCount ?? 0" />
</div>

<x-card>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>اسم الفرع</th><th>الكود</th><th>المدينة</th><th>المنطقة</th><th>المدير</th><th>الموظفون</th><th>الهاتف</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($branches ?? [] as $branch)
                    <tr>
                        <td style="font-weight:600">{{ $branch->name }}</td>
                        <td class="td-mono">{{ $branch->code }}</td>
                        <td>{{ $branch->city }}</td>
                        <td>{{ $branch->region }}</td>
                        <td>{{ $branch->manager_name ?? '—' }}</td>
                        <td>{{ $branch->employees_count ?? 0 }}</td>
                        <td class="td-mono">{{ $branch->phone ?? '—' }}</td>
                        <td><span style="color:{{ $branch->is_active ? 'var(--ac)' : 'var(--dg)' }}">● {{ $branch->is_active ? 'نشط' : 'معطّل' }}</span></td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <button class="btn btn-s" style="font-size:12px">تعديل</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">لا توجد فروع</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

<x-modal id="add-branch" title="إضافة فرع جديد" wide>
    <form method="POST" action="{{ route('branches.index') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">اسم الفرع</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">الكود</label><input type="text" name="code" class="form-input" placeholder="مثال: RUH-01" required></div>
            <div><label class="form-label">المدينة</label><input type="text" name="city" class="form-input" required></div>
            <div><label class="form-label">المنطقة</label><input type="text" name="region" class="form-input"></div>
            <div><label class="form-label">العنوان</label><input type="text" name="address" class="form-input"></div>
            <div><label class="form-label">رقم الهاتف</label><input type="text" name="phone" class="form-input"></div>
            <div><label class="form-label">اسم المدير</label><input type="text" name="manager_name" class="form-input"></div>
            <div><label class="form-label">بريد المدير</label><input type="email" name="manager_email" class="form-input"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">إضافة</button>
        </div>
    </form>
</x-modal>
@endsection
