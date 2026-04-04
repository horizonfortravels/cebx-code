@extends('layouts.app')
@section('title', 'أعلام الميزات الداخلية')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>أعلام الميزات</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">أعلام الميزات الداخلية</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            رؤية تشغيلية لسجل أعلام الميزات المعتمد على قاعدة البيانات. يعرض هذا المركز السجلات وسجل التدقيق الخاص بها دون أن يعمل كلوحة تجاوز خفية لإعدادات البيئة.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.feature-flags.index') }}" class="btn btn-s">تحديث</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">العودة إلى الرئيسية الداخلية</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="FLG" label="إجمالي الأعلام" :value="number_format($stats['total'])" />
    <x-stat-card icon="ON" label="المفعلة" :value="number_format($stats['enabled'])" />
    <x-stat-card icon="CFG" label="المعتمدة على الإعدادات" :value="number_format($stats['config_backed'])" />
    <x-stat-card icon="TGT" label="المستهدفة" :value="number_format($stats['targeted'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">بحث وفلاتر أساسية</div>
    <form method="GET" action="{{ route('internal.feature-flags.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="feature-flag-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بحث</label>
            <input id="feature-flag-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="ابحث باسم العلم أو المفتاح أو الوصف أو المصدر">
        </div>
        <div>
            <label for="feature-flag-state" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحالة</label>
            <select id="feature-flag-state" name="state" class="input">
                <option value="">كل الحالات</option>
                @foreach($stateOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ ['enabled' => 'مفعّل', 'disabled' => 'معطّل'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="feature-flag-source" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">مصدر التشغيل</label>
            <select id="feature-flag-source" name="source" class="input">
                <option value="">كل المصادر</option>
                @foreach($sourceOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ ['config_backed' => 'معتمد على الإعدادات', 'database_only' => 'من قاعدة البيانات فقط'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">تطبيق الفلاتر</button>
            <a href="{{ route('internal.feature-flags.index') }}" class="btn btn-s">إعادة الضبط</a>
        </div>
    </form>
</div>

<section class="card" data-testid="internal-feature-flags-note-card" style="margin-bottom:24px">
    <div class="card-title">ملاحظة تشغيلية</div>
    <p style="margin:0;color:var(--td);font-size:13px">
        ما زالت بعض خدمات المنصة تقرأ القيم الافتراضية المعتمدة على البيئة من <code>config/features.php</code>. لذلك يظل هذا المركز محصورًا عمدًا في سجل أعلام الميزات المعتمد على قاعدة البيانات حتى يتمكن المشغلون من فحص السجلات الحالية وتدقيق أي تبديل داخلي صريح بأمان.
    </p>
</section>

<div class="card" data-testid="internal-feature-flags-table">
    <div class="card-title">أعلام الميزات الظاهرة</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>العلم</th>
                <th>الحالة</th>
                <th>الإطلاق</th>
                <th>الاستهداف</th>
                <th>مصدر التشغيل</th>
                <th>أحدث تدقيق</th>
            </tr>
            </thead>
            <tbody>
            @forelse($flags as $row)
                <tr data-testid="internal-feature-flags-row">
                    <td>
                        <a href="{{ route('internal.feature-flags.show', $row['route_key']) }}" data-testid="internal-feature-flag-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['name'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['key'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['description'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['state_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">آخر تحديث {{ $row['updated_at'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['rollout_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ number_format($row['rollout_percentage']) }}%</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['targeting_summary'] }}</div>
                        <div style="font-size:12px;color:var(--td)">
                            حسابات {{ number_format($row['target_account_count']) }} • خطط {{ number_format($row['target_plan_count']) }}
                        </div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['source_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['config_default_label'] }}</div>
                    </td>
                    <td>
                        @if($row['latest_audit'])
                            <div style="font-size:13px;color:var(--tx)">{{ $row['latest_audit']['headline'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['latest_audit']['created_at'] }}</div>
                        @else
                            <div class="empty-state" style="padding:0;border:none;background:none;color:var(--td)">لا يوجد تدقيق على التبديل الداخلي بعد.</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">لا توجد أعلام ميزات مطابقة للفلاتر الحالية.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $flags->links() }}</div>
</div>
@endsection
