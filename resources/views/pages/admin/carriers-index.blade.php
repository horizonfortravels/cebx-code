@extends('layouts.app')
@section('title', 'تكاملات شركات الشحن')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>تكاملات شركات الشحن</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تكاملات شركات الشحن</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            عرض تشغيلي للقراءة فقط لتكاملات شركات الشحن المتصلة، مع الحالة الآمنة ووضع الاتصال والصحة وملخصات حسابات الشحن ومؤشرات بيانات الاعتماد المقنّعة.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.carriers.index') }}" class="btn btn-s">تحديث</a>
        <a href="{{ route('internal.integrations.index') }}" class="btn btn-s">فتح مركز التكاملات الكامل</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">العودة إلى الصفحة الداخلية الرئيسية</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="CAR" label="شركات الشحن المتصلة" :value="number_format($stats['total'])" />
    <x-stat-card icon="ON" label="المفعّلة" :value="number_format($stats['enabled'])" />
    <x-stat-card icon="CFG" label="المهيأة" :value="number_format($stats['configured'])" />
    <x-stat-card icon="ALT" label="بحاجة إلى متابعة" :value="number_format($stats['attention'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">بحث وفلاتر أساسية</div>
    <form method="GET" action="{{ route('internal.carriers.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="carrier-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بحث</label>
            <input id="carrier-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="ابحث باسم الناقل أو مفتاح المزود أو ملخص الخطأ">
        </div>
        <div>
            <label for="carrier-state" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحالة</label>
            <select id="carrier-state" name="state" class="input">
                <option value="">كل الحالات</option>
                @foreach($stateOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ ['enabled' => 'مفعلة', 'configured' => 'مهيأة', 'attention' => 'بحاجة إلى متابعة', 'disabled' => 'معطلة'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="carrier-health" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الصحة التشغيلية</label>
            <select id="carrier-health" name="health" class="input">
                <option value="">كل الحالات</option>
                @foreach($healthOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['health'] === $key)>{{ ['healthy' => 'سليمة', 'degraded' => 'متدهورة', 'down' => 'متوقفة', 'unknown' => 'غير معروفة'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">تطبيق الفلاتر</button>
            <a href="{{ route('internal.carriers.index') }}" class="btn btn-s">إعادة الضبط</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-carriers-table">
    <div class="card-title">تكاملات شركات الشحن الظاهرة</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>شركة الشحن</th>
                <th>الحالة</th>
                <th>حالة الاتصال والاختبار</th>
                <th>حساب الشاحن</th>
                <th>بيانات الاعتماد المقنّعة</th>
                <th>ملخص آخر خطأ</th>
            </tr>
            </thead>
            <tbody>
            @forelse($carriers as $row)
                <tr data-testid="internal-carriers-row">
                    <td>
                        <a href="{{ route('internal.carriers.show', $row['provider_key']) }}" data-testid="internal-carriers-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['name'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['provider_name'] }} • {{ $row['provider_key'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['enabled_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['configuration_label'] }} • {{ $row['mode_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['state_badge'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['connection_test_summary']['headline'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['connection_test_summary']['detail'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['shipper_account_summary']['summary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['masked_api_summary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['last_error_summary']['headline'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['last_error_summary']['detail'] }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">لا توجد تكاملات لشركات الشحن تطابق الفلاتر الحالية.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $carriers->links() }}</div>
</div>
@endsection
