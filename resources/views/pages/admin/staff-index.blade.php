@extends('layouts.app')
@section('title', 'دليل فريق المنصة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>فريق المنصة</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">دليل فريق المنصة</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            راجع حسابات فريق التشغيل والدعم الداخلي عبر دليل موحد يركز على الدور المعتمد، حالة الحساب، وآخر دخول دون إظهار أي أسرار أو بيانات تشغيلية غير لازمة.
        </p>
    </div>
    @if($canCreateStaff)
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="{{ route('internal.staff.create') }}" class="btn btn-pr" data-testid="internal-staff-create-cta">إضافة أو دعوة موظف</a>
        </div>
    @endif
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="STF" label="إجمالي الموظفين" :value="number_format($stats['total'])" />
    <x-stat-card icon="ACT" label="الحسابات النشطة" :value="number_format($stats['active'])" />
    <x-stat-card icon="LEG" label="تعيينات قديمة مخفية" :value="number_format($stats['deprecated'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">بحث وفلاتر أساسية</div>
    <form method="GET" action="{{ route('internal.staff.index') }}" class="filter-grid-fluid">
        <label class="filter-field filter-field-wide">
            <span class="form-label">بحث بالاسم أو البريد الإلكتروني</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="مثال: فريق الدعم أو ahmed@example.test" class="form-input">
        </label>

        <label class="filter-field">
            <span class="form-label">الدور المعتمد</span>
            <select name="role" class="form-input">
                <option value="">كل الأدوار</option>
                @foreach($roleOptions as $roleKey => $roleLabel)
                    <option value="{{ $roleKey }}" @selected($filters['role'] === $roleKey)>{{ $roleLabel }}</option>
                @endforeach
            </select>
        </label>

        <label class="filter-field">
            <span class="form-label">الحالة</span>
            <select name="status" class="form-input">
                <option value="">كل الحالات</option>
                @foreach($statusOptions as $statusKey => $statusLabel)
                    <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </label>

        <label class="filter-field">
            <span class="form-label">التعيينات القديمة</span>
            <select name="deprecated" class="form-input">
                <option value="">الكل</option>
                <option value="clean" @selected($filters['deprecated'] === 'clean')>متوافق فقط</option>
                <option value="flagged" @selected($filters['deprecated'] === 'flagged')>يحتوي تعيينات قديمة</option>
            </select>
        </label>

        <div class="filter-actions filter-actions-wide">
            <button type="submit" class="btn btn-pr">تطبيق الفلاتر</button>
            <a href="{{ route('internal.staff.index') }}" class="btn btn-s">إعادة الضبط</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-staff-table">
    <div class="card-title">قائمة فريق المنصة</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد</th>
                <th>الدور المعتمد</th>
                <th>الحالة</th>
                <th>آخر دخول</th>
                <th>التفاصيل</th>
            </tr>
            </thead>
            <tbody>
            @forelse($staff as $row)
                <tr>
                    <td>
                        <a href="{{ route('internal.staff.show', $row['user']) }}" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['user']->name }}
                        </a>
                        @if($row['hasDeprecatedAssignments'])
                            <div style="font-size:12px;color:var(--wa);margin-top:4px">توجد تعيينات قديمة مخفية من الواجهة النشطة</div>
                        @endif
                    </td>
                    <td>{{ $row['user']->email }}</td>
                    <td>{{ $row['roleLabel'] }}</td>
                    <td>{{ $row['statusLabel'] }}</td>
                    <td>{{ $row['lastLoginAt'] ?? 'لا يوجد دخول مسجل بعد' }}</td>
                    <td>
                        <a href="{{ route('internal.staff.show', $row['user']) }}" class="btn btn-s">عرض الملف</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">لا توجد حسابات فريق مطابقة للفلاتر الحالية.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $staff->links() }}</div>
</div>
@endsection
