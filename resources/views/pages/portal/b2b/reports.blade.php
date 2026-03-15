@extends('layouts.app')
@section('title', 'بوابة الأعمال | التقارير')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2b.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأعمال</a>
            <span style="margin:0 6px">/</span>
            <span>التقارير</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">التقارير التنفيذية للمنظمة</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            هذه الصفحة مصممة لقراءة سريعة قبل الانتقال إلى مركز التقارير الكامل. استخدمها كملخص تنفيذي لحساب المنظمة الحالي.
        </p>
    </div>
    <a href="{{ route('reports.index') }}" class="btn btn-pr">فتح مركز التقارير</a>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    @foreach($stats as $stat)
        <x-stat-card :icon="$stat['icon']" :label="$stat['label']" :value="$stat['value']" />
    @endforeach
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px">
    @foreach($reportCards as $card)
        <div class="card">
            <div class="card-title">{{ $card['title'] }}</div>
            <p style="color:var(--td);font-size:13px;line-height:1.8;margin:0">{{ $card['description'] }}</p>
        </div>
    @endforeach
</div>
@endsection
