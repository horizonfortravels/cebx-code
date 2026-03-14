@extends('layouts.app')
@section('title', 'اختيار الحساب')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">اختيار حساب للتصفح الداخلي</h1>
        <p style="color:var(--td);font-size:14px;margin:6px 0 0">هذا الاختيار مؤقت داخل الجلسة فقط. لن يتم ربط المستخدم الداخلي بالحساب بشكل دائم.</p>
    </div>
    @if($selectedAccount)
        <form action="{{ route('admin.tenant-context.clear') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-ghost">مسح السياق الحالي</button>
        </form>
    @endif
</div>

<div class="card" style="margin-bottom:20px">
    <div class="card-title">الحساب الحالي</div>
    @if($selectedAccount)
        <div style="font-weight:700;color:var(--tx)">{{ $selectedAccount->name }}</div>
        <div style="color:var(--td);font-size:13px;margin-top:4px">{{ $selectedAccount->type === 'organization' ? 'منظمة' : 'فردي' }}</div>
    @else
        <div style="color:var(--td)">لا يوجد حساب محدد حاليًا.</div>
    @endif
</div>

<div class="card" style="margin-bottom:20px">
    <div class="card-title">بحث</div>
    <form method="GET" action="{{ route('admin.tenant-context') }}" style="display:flex;gap:12px;flex-wrap:wrap">
        <input type="text" name="q" value="{{ $search }}" placeholder="ابحث باسم الحساب أو slug" class="input" style="max-width:360px">
        <input type="hidden" name="redirect" value="{{ $redirectTo }}">
        <button type="submit" class="btn btn-s">بحث</button>
    </form>
</div>

<div class="card">
    <div class="card-title">الحسابات المتاحة</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px">
        @forelse($accounts as $account)
            <form action="{{ route('admin.tenant-context.store') }}" method="POST" style="padding:16px;border:1px solid var(--bd);border-radius:12px;background:var(--sf)">
                @csrf
                <input type="hidden" name="account_id" value="{{ $account->id }}">
                <input type="hidden" name="redirect" value="{{ $redirectTo }}">
                <div style="font-weight:700;color:var(--tx);margin-bottom:4px">{{ $account->name }}</div>
                <div style="font-size:13px;color:var(--td);margin-bottom:12px">{{ $account->type === 'organization' ? 'منظمة' : 'فردي' }}</div>
                @if($account->slug)
                    <div style="font-size:12px;color:var(--td);margin-bottom:12px">slug: {{ $account->slug }}</div>
                @endif
                <button type="submit" class="btn btn-s">اختيار هذا الحساب</button>
            </form>
        @empty
            <div class="empty-state">لا توجد حسابات مطابقة.</div>
        @endforelse
    </div>

    <div style="margin-top:16px">
        {{ $accounts->links() }}
    </div>
</div>
@endsection
