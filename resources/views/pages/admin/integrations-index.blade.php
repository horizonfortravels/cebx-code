@extends('layouts.app')
@section('title', 'التكاملات الداخلية')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>التكاملات</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">التكاملات الداخلية</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            رؤية تشغيلية للقراءة فقط لمزوّدي شركات الشحن وموصلات المتاجر وبوابات الدفع، مع ملخصات الاعتمادات المقنّعة وإشارات الصحة والنشاط الحديثة وحالة أعلام الميزات.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.integrations.index') }}" class="btn btn-s">تحديث</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">العودة إلى الرئيسية الداخلية</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="INT" label="إجمالي التكاملات" :value="number_format($stats['total'])" />
    <x-stat-card icon="ON" label="المفعلة" :value="number_format($stats['enabled'])" />
    <x-stat-card icon="ALT" label="بحاجة إلى متابعة" :value="number_format($stats['attention'])" />
    <x-stat-card icon="CAR" label="المواجهة للناقل" :value="number_format($stats['carrier'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">بحث وفلاتر أساسية</div>
    <form method="GET" action="{{ route('internal.integrations.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="integration-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بحث</label>
            <input id="integration-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="ابحث بالمزوّد أو المتجر أو البوابة أو الحساب">
        </div>
        <div>
            <label for="integration-type" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">النوع</label>
            <select id="integration-type" name="type" class="input">
                <option value="">كل الأنواع</option>
                @foreach($typeOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ ['carrier' => 'ناقل', 'store' => 'متجر', 'gateway' => 'بوابة دفع'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="integration-state" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحالة</label>
            <select id="integration-state" name="state" class="input">
                <option value="">كل الحالات</option>
                @foreach($stateOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ ['enabled' => 'مفعلة', 'configured' => 'مهيأة', 'attention' => 'بحاجة إلى متابعة', 'disabled' => 'معطلة'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="integration-health" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الصحة التشغيلية</label>
            <select id="integration-health" name="health" class="input">
                <option value="">كل الحالات</option>
                @foreach($healthOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['health'] === $key)>{{ ['healthy' => 'سليمة', 'degraded' => 'متدهورة', 'down' => 'متوقفة', 'unknown' => 'غير معروفة'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">تطبيق الفلاتر</button>
            <a href="{{ route('internal.integrations.index') }}" class="btn btn-s">إعادة الضبط</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-integrations-table">
    <div class="card-title">التكاملات الظاهرة</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>التكاملة</th>
                <th>النوع والحالة</th>
                <th>الصحة</th>
                <th>النشاط الحديث</th>
                <th>الاعتمادات المقنّعة</th>
                <th>أعلام الميزات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($integrations as $row)
                <tr data-testid="internal-integrations-row">
                    <td>
                        <a href="{{ route('internal.integrations.show', $row['route_key']) }}" data-testid="internal-integrations-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['name'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['provider_name'] }} • {{ $row['provider_key'] }}</div>
                        @if($row['account_summary'])
                            <div style="font-size:12px;color:var(--td)">{{ $row['account_summary']['name'] }} • {{ $row['account_summary']['type_label'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['type_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['enabled_label'] }} • {{ $row['configuration_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['state_badge'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['health_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['health_summary']['checked_at'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['health_summary']['request_summary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['activity_summary']['headline'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['activity_summary']['detail'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['masked_api_summary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['feature_flag_summary'] }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">لا توجد تكاملات مطابقة للفلاتر الحالية.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $integrations->links() }}</div>
</div>
@endsection
