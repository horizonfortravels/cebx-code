@extends('layouts.app')
@section('title', 'الويبهوكات الداخلية')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>الويبهوكات</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">عمليات الويبهوكات الداخلية</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            رؤية للقراءة فقط لعمليات التسليم الخاصة بنقاط ويبهوك المتاجر والتتبع الواردة، بما في ذلك الإخفاقات الحديثة ومسار الإعادة الآمن المحدود لتسليمات المتاجر الفاشلة.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.webhooks.index') }}" class="btn btn-s">تحديث</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">العودة إلى الرئيسية الداخلية</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="WH" label="إجمالي النقاط الطرفية" :value="number_format($stats['total'])" />
    <x-stat-card icon="ALT" label="بحاجة إلى متابعة" :value="number_format($stats['attention'])" />
    <x-stat-card icon="RET" label="إخفاقات قابلة لإعادة المحاولة" :value="number_format($stats['retryable'])" />
    <x-stat-card icon="TRK" label="نقاط التتبع" :value="number_format($stats['tracking'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">بحث وفلاتر أساسية</div>
    <form method="GET" action="{{ route('internal.webhooks.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="webhook-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بحث</label>
            <input id="webhook-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="ابحث بالمزوّد أو النقطة أو الحساب أو المتجر">
        </div>
        <div>
            <label for="webhook-type" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">نوع النقطة الطرفية</label>
            <select id="webhook-type" name="type" class="input">
                <option value="">كل الأنواع</option>
                @foreach($typeOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ ['store' => 'نقاط المتاجر', 'tracking' => 'نقاط التتبع'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="webhook-state" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحالة</label>
            <select id="webhook-state" name="state" class="input">
                <option value="">كل الحالات</option>
                @foreach($stateOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ ['operational' => 'تشغيلية', 'attention' => 'بحاجة إلى متابعة', 'retryable' => 'إخفاقات قابلة لإعادة المحاولة'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">تطبيق الفلاتر</button>
            <a href="{{ route('internal.webhooks.index') }}" class="btn btn-s">إعادة الضبط</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-webhooks-table">
    <div class="card-title">نقاط الويبهوك الظاهرة</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>النقطة الطرفية</th>
                <th>النوع والسياق</th>
                <th>الحالة</th>
                <th>أحدث تسليم</th>
                <th>الإخفاقات</th>
                <th>ملخص الأمان</th>
            </tr>
            </thead>
            <tbody>
            @forelse($endpoints as $row)
                <tr data-testid="internal-webhooks-row">
                    <td>
                        <a href="{{ route('internal.webhooks.show', $row['route_key']) }}" data-testid="internal-webhooks-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['name'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['provider_name'] }} • {{ $row['provider_key'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['endpoint_label'] }}</div>
                        @if($row['account_summary'])
                            <div style="font-size:12px;color:var(--td)">{{ $row['account_summary']['name'] }} • {{ $row['account_summary']['type_label'] }}</div>
                        @else
                            <div style="font-size:12px;color:var(--td)">نقطة واردة على مستوى المنصة</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['state_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['enabled_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">محاولات {{ number_format($row['attempts_count']) }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['recent_summary'] }}</div>
                        <div style="font-size:12px;color:var(--td)">آخر محاولة {{ $row['last_attempt_at'] }}</div>
                        <div style="font-size:12px;color:var(--td)">آخر نجاح {{ $row['last_success_at'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ number_format($row['failures_count']) }} إخفاقات حديثة</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['failure_summary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['security_summary'] }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">لا توجد نقاط ويبهوك مطابقة للفلاتر الحالية.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $endpoints->links() }}</div>
</div>
@endsection
