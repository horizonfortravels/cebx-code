@extends('layouts.app')
@section('title', 'طابور حالات التحقق')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>حالات التحقق</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">طابور حالات التحقق</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:780px">
            مركز قراءة تشغيلي لمراجعة حالات التحقق الحالية على الحسابات الخارجية، مع إبراز الحالة، اكتمال المستندات، القيود الفعالة، وأثرها على التشغيل دون عرض محتوى الوثائق الخام أو أي بيانات حساسة غير لازمة.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.kyc.index') }}" class="btn btn-s">تحديث الطابور</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">العودة إلى المساحة الداخلية</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="KYC" label="إجمالي الحالات" :value="number_format($stats['total'])" />
    <x-stat-card icon="PND" label="قيد المراجعة" :value="number_format($stats['pending'])" />
    <x-stat-card icon="REJ" label="بحاجة إلى متابعة" :value="number_format($stats['rejected'])" />
    <x-stat-card icon="RST" label="حسابات مقيدة" :value="number_format($stats['restricted'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">بحث وفلاتر أساسية</div>
    <form method="GET" action="{{ route('internal.kyc.index') }}" style="display:grid;grid-template-columns:2fr repeat(3,minmax(0,1fr)) auto;gap:12px;align-items:end">
        <div>
            <label for="kyc-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بحث</label>
            <input id="kyc-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="اسم الحساب أو البريد أو السجل التجاري">
        </div>

        <div>
            <label for="kyc-type" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">نوع الحساب</label>
            <select id="kyc-type" name="type" class="input">
                <option value="">الكل</option>
                <option value="individual" @selected($filters['type'] === 'individual')>فردي</option>
                <option value="organization" @selected($filters['type'] === 'organization')>منظمة</option>
            </select>
        </div>

        <div>
            <label for="kyc-status" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">حالة التحقق</label>
            <select id="kyc-status" name="status" class="input">
                <option value="">الكل</option>
                @foreach($statusOptions as $statusKey => $statusLabel)
                    <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="kyc-restriction" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">القيود</label>
            <select id="kyc-restriction" name="restriction" class="input">
                <option value="">الكل</option>
                <option value="restricted" @selected($filters['restriction'] === 'restricted')>مقيد الآن</option>
                <option value="clear" @selected($filters['restriction'] === 'clear')>بدون قيود</option>
            </select>
        </div>

        <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-pr">تطبيق</button>
            <a href="{{ route('internal.kyc.index') }}" class="btn btn-s">إعادة ضبط</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-kyc-table">
    <div class="card-title">الحالات الحالية</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>الحساب / الملف</th>
                <th>النوع والحالة</th>
                <th>المالك / المؤسسة</th>
                <th>المستندات</th>
                <th>القيود والحدود</th>
                <th>المراجعة</th>
            </tr>
            </thead>
            <tbody>
            @forelse($cases as $row)
                @php
                    $account = $row['account'];
                    $kyc = $row['kyc'];
                    $documents = $row['documentCounts'];
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('internal.kyc.show', $account) }}" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $account->name }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $account->slug ?? '—' }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['typeLabel'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $kyc['label'] }}</div>
                    </td>
                    <td>
                        @if($account->isOrganization())
                            <div style="font-weight:700;color:var(--tx)">{{ $row['organizationSummary'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['owner']?->name ?? 'لا يوجد مالك ظاهر' }} @if($row['owner']?->email) • {{ $row['owner']->email }} @endif</div>
                        @else
                            <div style="font-weight:700;color:var(--tx)">{{ $row['owner']?->name ?? 'لا يوجد مالك ظاهر' }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['owner']?->email ?? 'لا يوجد بريد ظاهر' }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ number_format($documents['submitted']) }} / {{ number_format($documents['required']) }}</div>
                        <div style="font-size:12px;color:var(--td)">وثائق مستلمة مقابل المطلوب</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['isRestricted'] ? 'يوجد قيد نشط' : 'بدون قيد نشط' }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['shipmentLimitSummary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['reviewSummary'] }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">لا توجد حالات تحقق تطابق الفلاتر الحالية.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $cases->links() }}</div>
</div>
@endsection
