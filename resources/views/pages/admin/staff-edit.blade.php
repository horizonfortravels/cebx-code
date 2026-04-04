@extends('layouts.app')
@section('title', 'تحرير موظف داخلي')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <div class="header-main">
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.staff.index') }}" style="color:inherit;text-decoration:none">فريق المنصة</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.staff.show', $staffUser) }}" style="color:inherit;text-decoration:none">{{ $staffUser->name }}</a>
            <span style="margin:0 6px">/</span>
            <span>تحرير</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تحرير ملف موظف داخلي</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            عدّل البيانات الأساسية لموظف المنصة وأعد تعيين دوره المعتمد عند الحاجة، مع الإبقاء على دور واحد معتمد فقط في هذا التدفق.
        </p>
    </div>
    <div class="header-actions">
        <a href="{{ route('internal.staff.show', $staffUser) }}" class="btn btn-s">العودة إلى الملف</a>
    </div>
</div>

@if($errors->any())
    <x-toast type="error" :message="$errors->first()" />
@endif

@php
    $staffStatusLabel = match ((string) ($staffUser->status ?? 'active')) {
        'active' => 'نشط',
        'pending' => 'قيد التفعيل',
        'suspended' => 'معلّق',
        'disabled' => 'معطّل',
        default => (string) ($staffUser->status ?? 'نشط'),
    };
@endphp

<form method="POST" action="{{ route('internal.staff.update', $staffUser) }}" class="grid-main-sidebar" data-testid="internal-staff-edit-form">
    @csrf
    @method('PUT')

    <x-card title="الملف الأساسي">
        <div class="form-grid-2">
            <div>
                <label for="name" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الاسم</label>
                <input id="name" name="name" type="text" class="input" value="{{ old('name', $staffUser->name) }}" required>
            </div>
            <div>
                <label for="email" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" class="input" value="{{ old('email', $staffUser->email) }}" required>
            </div>
            <div>
                <label for="locale" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">اللغة</label>
                <input id="locale" name="locale" type="text" class="input" value="{{ old('locale', $staffUser->locale) }}">
            </div>
            <div>
                <label for="timezone" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">المنطقة الزمنية</label>
                <input id="timezone" name="timezone" type="text" class="input" value="{{ old('timezone', $staffUser->timezone) }}">
            </div>
        </div>
    </x-card>

    <x-card title="الحوكمة والدور">
        <div class="form-grid-2">
            <div>
                <label style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحالة الحالية</label>
                <div class="input" style="display:flex;align-items:center">{{ $staffStatusLabel }}</div>
            </div>
            <div>
                <label for="role" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الدور الداخلي المعتمد</label>
                <select id="role" name="role" class="input" data-testid="staff-role-select">
                    @foreach($roleOptions as $roleKey => $roleLabel)
                        <option value="{{ $roleKey }}" @selected(old('role', $currentRole) === $roleKey)>{{ $roleLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div style="grid-column:1 / -1;font-size:13px;color:var(--td);line-height:1.7">
                إعادة الحفظ من هذه الشاشة تزيل أي تعيينات داخلية قديمة أو إضافية وتبقي دورًا معتمدًا واحدًا فقط للمستخدم.
            </div>
        </div>
    </x-card>

    <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:12px">
        <a href="{{ route('internal.staff.show', $staffUser) }}" class="btn btn-s">إلغاء</a>
        <button type="submit" class="btn btn-pr" data-testid="staff-update-submit">حفظ التعديلات</button>
    </div>
</form>
@endsection
