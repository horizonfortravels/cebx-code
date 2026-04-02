@extends('layouts.app')
@section('title', 'إضافة موظف داخلي')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.staff.index') }}" style="color:inherit;text-decoration:none">فريق المنصة</a>
            <span style="margin:0 6px">/</span>
            <span>إضافة موظف</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">إضافة أو دعوة موظف داخلي</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            أنشئ حسابًا داخليًا جديدًا مع دور معتمد واحد فقط. يمكنك إنشاء كلمة المرور مباشرة، أو دعوة الموظف عبر رابط إعداد كلمة المرور باستخدام المسار الآمن نفسه المستخدم في المنصة.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.staff.index') }}" class="btn btn-s">العودة إلى الدليل</a>
    </div>
</div>

@if($errors->any())
    <x-toast type="error" :message="$errors->first()" />
@endif

<form method="POST" action="{{ route('internal.staff.store') }}" class="grid-2" data-testid="internal-staff-create-form">
    @csrf

    <x-card title="الملف الأساسي">
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
            <div>
                <label for="name" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الاسم</label>
                <input id="name" name="name" type="text" class="input" value="{{ old('name') }}" required>
            </div>
            <div>
                <label for="email" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required>
            </div>
            <div>
                <label for="locale" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">اللغة</label>
                <input id="locale" name="locale" type="text" class="input" value="{{ old('locale', $defaults['locale']) }}">
            </div>
            <div>
                <label for="timezone" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">المنطقة الزمنية</label>
                <input id="timezone" name="timezone" type="text" class="input" value="{{ old('timezone', $defaults['timezone']) }}">
            </div>
        </div>
    </x-card>

    <x-card title="الدور المعتمد">
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
            <div style="grid-column:1 / -1">
                <label for="role" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الدور الداخلي</label>
                <select id="role" name="role" class="input" data-testid="staff-role-select">
                    @foreach($roleOptions as $roleKey => $roleLabel)
                        <option value="{{ $roleKey }}" @selected(old('role', $defaults['role']) === $roleKey)>{{ $roleLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div style="grid-column:1 / -1;font-size:13px;color:var(--td);line-height:1.7">
                يقتصر هذا التدفق على الأدوار الداخلية المعتمدة فقط، ولا يسمح بإظهار أو تعيين أي مسميات داخلية قديمة.
            </div>
        </div>
    </x-card>

    <x-card title="إنشاء مباشر بكلمة مرور" style="grid-column:1 / -1">
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
            <div>
                <label for="password" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">كلمة المرور</label>
                <input id="password" name="password" type="password" class="input" autocomplete="new-password">
            </div>
            <div>
                <label for="password_confirmation" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">تأكيد كلمة المرور</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password">
            </div>
            <div style="grid-column:1 / -1;font-size:13px;color:var(--td);line-height:1.7">
                استخدم هذا الخيار عندما تريد تسليم الحساب جاهزًا الآن. إذا كنت تفضّل دعوة الموظف ليعيّن كلمة المرور بنفسه، فاترك هذين الحقلين فارغين واضغط زر الدعوة أدناه.
            </div>
        </div>
    </x-card>

    <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap">
        <a href="{{ route('internal.staff.index') }}" class="btn btn-s">إلغاء</a>
        <button type="submit" name="provisioning_mode" value="invite" class="btn btn-s" data-testid="staff-invite-submit">دعوة عبر رابط إعداد كلمة المرور</button>
        <button type="submit" name="provisioning_mode" value="create" class="btn btn-pr" data-testid="staff-create-submit">إنشاء الموظف الآن</button>
    </div>
</form>
@endsection
