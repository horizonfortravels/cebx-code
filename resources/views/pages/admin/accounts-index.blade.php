@extends('layouts.app')
@section('title', 'حسابات العملاء')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>حسابات العملاء</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">حسابات العملاء</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            مركز قراءة داخلي سريع لمراجعة حسابات العملاء الفردية وحسابات المنظمات دون الدخول في إجراءات تعديل أو إدارة أعضاء في هذه المرحلة.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($canCreateAccounts)
            <a href="{{ route('internal.accounts.create') }}" class="btn btn-pr">إضافة حساب عميل</a>
        @endif
        <a href="{{ route('internal.accounts.index') }}" class="btn btn-s">تحديث القائمة</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">العودة إلى المساحة الداخلية</a>
    </div>
</div>

<form method="GET" action="{{ route('internal.accounts.index') }}" class="card" style="margin-bottom:24px;display:grid;grid-template-columns:2fr repeat(4,minmax(0,1fr)) auto;gap:12px;align-items:end">
    <div>
        <label for="accounts-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بحث</label>
        <input id="accounts-search" type="text" name="q" value="{{ $filters['q'] }}" placeholder="ابحث بالاسم أو البريد أو السجل التجاري" class="input">
    </div>

    <div>
        <label for="accounts-type" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">نوع الحساب</label>
        <select id="accounts-type" name="type" class="input">
            <option value="">الكل</option>
            <option value="individual" @selected($filters['type'] === 'individual')>فردي</option>
            <option value="organization" @selected($filters['type'] === 'organization')>منظمة</option>
        </select>
    </div>

    <div>
        <label for="accounts-status" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحالة</label>
        <select id="accounts-status" name="status" class="input">
            <option value="">الكل</option>
            @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="accounts-kyc" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">التحقق</label>
        <select id="accounts-kyc" name="kyc" class="input">
            <option value="">الكل</option>
            @foreach($kycOptions as $value => $label)
                <option value="{{ $value }}" @selected($filters['kyc'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="accounts-restriction" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">القيود</label>
        <select id="accounts-restriction" name="restriction" class="input">
            <option value="">الكل</option>
            <option value="restricted" @selected($filters['restriction'] === 'restricted')>مقيد الآن</option>
            <option value="clear" @selected($filters['restriction'] === 'clear')>بدون قيود</option>
        </select>
    </div>

    <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-pr">تطبيق</button>
        <a href="{{ route('internal.accounts.index') }}" class="btn btn-s">إعادة ضبط</a>
    </div>
</form>

<div class="card">
    <div class="card-title">القائمة الحالية</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>الحساب</th>
                <th>النوع والحالة</th>
                <th>المالك / المؤسسة</th>
                <th>التحقق والقيود</th>
                <th>المحفظة</th>
                <th>الشحنات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($accounts as $row)
                @php
                    $account = $row['account'];
                    $owner = $row['owner'];
                    $wallet = $row['wallet'];
                    $kyc = $row['kyc'];
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('internal.accounts.show', $account) }}" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $account->name }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $account->slug ?? '—' }}</div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--tx)">{{ $row['typeLabel'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['statusLabel'] }}</div>
                    </td>
                    <td>
                        @if($account->isOrganization())
                            <div style="font-weight:600;color:var(--tx)">{{ $row['organizationSummary'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $owner?->name ?? 'لا يوجد مالك محدد' }} @if($owner?->email) • {{ $owner->email }} @endif</div>
                        @else
                            <div style="font-weight:600;color:var(--tx)">{{ $owner?->name ?? 'لا يوجد مالك محدد' }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $owner?->email ?? 'لا يوجد بريد موثق' }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--tx)">{{ $kyc['label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">
                            {{ $row['isRestricted'] ? 'توجد قيود تحقق نشطة' : 'لا توجد قيود تحقق نشطة' }}
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--tx)">{{ $wallet['headline'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $wallet['meta'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--tx)">{{ number_format($row['shipmentsCount']) }}</div>
                        <div style="font-size:12px;color:var(--td)">إجمالي الشحنات المسجلة</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">لا توجد حسابات تطابق شروط البحث الحالية.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $accounts->links() }}</div>
</div>
@endsection
