@extends('layouts.app')
@section('title', 'قائمة الامتثال الداخلية')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>قائمة الامتثال</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">قائمة الامتثال الداخلية</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            عرض تشغيلي للقراءة فقط لتصاريح المواد الخطرة وحالة الإقرار القانوني وآخر نشاط مراجعة الامتثال. تبقى هذه القائمة منقاة: لا يتم كشف نصوص الإعفاء الخام أو البصمات أو عناوين IP أو وكلاء المستخدم أو حمولات التدقيق الخام هنا.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.compliance.index') }}" class="btn btn-s">تحديث القائمة</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">العودة إلى المساحة الداخلية</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="CMP" label="إجمالي الحالات" :value="number_format($stats['total'])" />
    <x-stat-card icon="ATT" label="بحاجة إلى متابعة" :value="number_format($stats['attention'])" />
    <x-stat-card icon="LGL" label="إقرار بانتظار الاعتماد" :value="number_format($stats['waiver_pending'])" />
    <x-stat-card icon="DG" label="معلمة بمواد خطرة" :value="number_format($stats['dg_flagged'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">بحث وفلاتر أساسية</div>
    <form method="GET" action="{{ route('internal.compliance.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="compliance-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بحث</label>
            <input id="compliance-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="ابحث برقم الشحنة أو الحساب أو المالك أو المنظمة">
        </div>

        <div>
            <label for="compliance-type" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">نوع الحساب</label>
            <select id="compliance-type" name="type" class="input">
                <option value="">كل الأنواع</option>
                <option value="individual" @selected($filters['type'] === 'individual')>فردي</option>
                <option value="organization" @selected($filters['type'] === 'organization')>منظمة</option>
            </select>
        </div>

        <div>
            <label for="compliance-status" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">حالة الامتثال</label>
            <select id="compliance-status" name="status" class="input">
                <option value="">كل الحالات</option>
                @foreach($statusOptions as $statusKey => $statusLabel)
                    <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ ['pending' => 'بانتظار الإقرار', 'completed' => 'مكتمل', 'hold_dg' => 'حجز مواد خطرة', 'requires_action' => 'يتطلب إجراء', 'expired' => 'منتهي'][$statusKey] ?? $statusLabel }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="compliance-review" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">مسار المراجعة</label>
            <select id="compliance-review" name="review" class="input">
                <option value="">كل المسارات</option>
                @foreach($reviewOptions as $reviewKey => $reviewLabel)
                    <option value="{{ $reviewKey }}" @selected($filters['review'] === $reviewKey)>{{ ['attention' => 'بحاجة إلى متابعة', 'open' => 'مراجعة مفتوحة', 'clear' => 'واضحة'][$reviewKey] ?? $reviewLabel }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">تطبيق الفلاتر</button>
            <a href="{{ route('internal.compliance.index') }}" class="btn btn-s">إعادة الضبط</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-compliance-table">
    <div class="card-title">حالات الامتثال الحالية</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>الشحنة</th>
                <th>الحساب</th>
                <th>الحالة الحالية</th>
                <th>ملخص التصريح</th>
                <th>الإقرار القانوني</th>
                <th>آخر مراجعة</th>
            </tr>
            </thead>
            <tbody>
            @forelse($cases as $row)
                <tr data-testid="internal-compliance-row">
                    <td>
                        <a href="{{ route('internal.compliance.show', $row['declaration']) }}" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['shipmentReference'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['shipmentStatus'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['accountLabel'] }}</div>
                        <div style="font-size:12px;color:var(--td)">
                            {{ $row['accountTypeLabel'] }}
                            @if($row['organizationSummary'])
                                • {{ $row['organizationSummary'] }}
                            @else
                                • {{ $row['ownerSummary'] }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['statusLabel'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['reviewLabel'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['declarationSummary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['legalSummary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['latestAuditSummary'] }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">لا توجد حالات امتثال تطابق الفلاتر الحالية.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $cases->links() }}</div>
</div>
@endsection
