@extends('layouts.app')
@section('title', 'التقارير والتحليلات الداخلية')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <div class="header-main">
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>التقارير والتحليلات</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">مركز التقارير الداخلي</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            ملخصات تشغيلية على شكل بطاقات عبر الشحنات وKYC والمحفظة والفوترة والامتثال وتكاملات شركات الشحن ومركز الدعم. يبقى هذا المركز للقراءة فقط ويعرض مؤشرات رئيسية آمنة قبل فتح المركز الداخلي المرتبط لمراجعة أعمق.
        </p>
    </div>
    <div class="header-actions">
        <a href="{{ route('internal.reports.index') }}" class="btn btn-s">تحديث</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">العودة إلى المساحة الداخلية</a>
    </div>
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">بحث وفلاتر أساسية</div>
    <form method="GET" action="{{ route('internal.reports.index') }}" data-testid="internal-reports-filter-form" class="filter-grid-fluid">
        <div>
            <label for="internal-reports-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بحث</label>
            <input id="internal-reports-search" data-testid="internal-reports-search-input" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="ابحث باسم المجال أو المؤشر أو الملخص التشغيلي">
        </div>
        <div>
            <label for="internal-reports-domain" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">المجال</label>
            <select id="internal-reports-domain" data-testid="internal-reports-domain-filter" name="domain" class="input">
                <option value="">كل المجالات</option>
                @foreach($domainOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['domain'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">تطبيق الفلاتر</button>
            <a href="{{ route('internal.reports.index') }}" class="btn btn-s">إعادة الضبط</a>
        </div>
    </form>
</div>

<div class="card" style="margin-bottom:24px;background:#f8fafc">
    <div class="card-title">ضابط النطاق</div>
    <p style="margin:0;color:var(--td);line-height:1.8">
        تُعرض هنا فقط المؤشرات التشغيلية عالية المستوى. تبقى الأسرار والحمولات الخام للويبهوك ومحتوى التصعيد الداخلي والبيانات المالية الخاصة والحمولات القانونية أو الامتثالية المخفية داخل مراكز القراءة المخصصة لها وتظل مقنّعة هناك أيضًا.
    </p>
</div>

<div data-testid="internal-reports-grid" class="grid-auto-240">
    @forelse($cards as $card)
        <article class="card" data-testid="internal-report-card-{{ $card['key'] }}" style="display:grid;gap:16px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
                <div>
                    <div style="font-size:12px;color:var(--tm);margin-bottom:6px">{{ $card['eyebrow'] }}</div>
                    <h2 style="margin:0;font-size:22px;color:var(--tx)">{{ $card['title'] }}</h2>
                </div>
                <span style="display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700">
                    {{ number_format(count($card['metrics'])) }} مؤشرات
                </span>
            </div>

            <p style="margin:0;color:var(--td);line-height:1.8">{{ $card['description'] }}</p>

            <div class="field-grid-compact">
                @foreach($card['metrics'] as $metric)
                    <div style="padding:14px;border:1px solid var(--bd);border-radius:16px;background:#fff">
                        <div style="font-size:12px;color:var(--tm);margin-bottom:6px">{{ $metric['label'] }}</div>
                        <div style="font-size:24px;font-weight:800;color:var(--tx)">{{ $metric['display'] ?? number_format($metric['value']) }}</div>
                    </div>
                @endforeach
            </div>

            <div style="font-size:13px;color:var(--td);line-height:1.8">{{ $card['summary'] }}</div>

            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    @if($card['can_open_dashboard'])
                        <a href="{{ route('internal.reports.' . $card['key']) }}" class="btn btn-pr" data-testid="internal-report-card-{{ $card['key'] }}-dashboard-link">فتح لوحة المعلومات</a>
                    @endif

                    @if($card['can_open'] && filled($card['route_name'] ?? null))
                        <a href="{{ route($card['route_name']) }}" class="btn btn-s" data-testid="internal-report-card-{{ $card['key'] }}-link">{{ $card['cta_label'] }}</a>
                    @elseif(filled($card['route_name'] ?? null))
                        <span class="btn btn-s" style="pointer-events:none;opacity:.8">المركز المرتبط غير متاح لهذا الدور</span>
                    @endif
                </div>
                <span style="font-size:12px;color:var(--tm)">ملخص للقراءة فقط</span>
            </div>
        </article>
    @empty
        <div class="card" data-testid="internal-reports-empty-state">
            <div class="card-title">لا توجد بطاقات تقارير تطابق الفلاتر الحالية</div>
            <p style="margin:0;color:var(--td);line-height:1.8">
                عدّل البحث أو فلتر المجال لاستعادة بطاقات التقارير التشغيلية المتاحة.
            </p>
        </div>
    @endforelse
</div>
@endsection
