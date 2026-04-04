@extends('layouts.app')

@php
    $defaultMailer = strtolower((string) ($settings['default_mailer'] ?? 'smtp'));
    $defaultMailerLabel = match ($defaultMailer) {
        'smtp' => 'خادم البريد',
        'sendmail' => 'الإرسال المحلي',
        'log' => 'سجل البريد',
        default => 'خدمة البريد الحالية',
    };
@endphp

@section('title', 'إعدادات خادم البريد الداخلية')

@section('content')
<div style="display:grid;gap:20px">
    <section>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">الداخلية / البريد / خادم البريد</div>
        <h1 style="margin:0;font-size:28px;font-weight:800;color:var(--tx)">إعدادات خادم البريد الداخلية</h1>
        <p style="margin:8px 0 0;color:var(--td);max-width:820px;line-height:1.9">
            هذه الصفحة مخصصة لفريق المنصة الداخلي فقط لحفظ إعدادات خادم البريد المشفرة، اختبار الاتصال، وإرسال رسالة تجريبية دون تعديل ملف البيئة.
        </p>
    </section>

    <div class="card" style="background:rgba(15,23,42,.03)">
        <div class="card-title">ملخص المصدر الحالي</div>
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
            <span class="badge {{ ($settings['using_stored_transport'] ?? false) ? 'badge-ok' : 'badge-muted' }}">
                {{ ($settings['using_stored_transport'] ?? false) ? 'يتم استخدام إعدادات خادم البريد المحفوظة' : 'الرجوع إلى خدمة البريد الحالية في البيئة' }}
            </span>
            <span style="font-size:13px;color:var(--td)">
                خدمة البريد الافتراضية الحالية:
                <strong style="color:var(--tx)">{{ $defaultMailerLabel }}</strong>
            </span>
            <span style="font-size:13px;color:var(--td)">
                {{ ($settings['stored_config_complete'] ?? false) ? 'الإعدادات المحفوظة مكتملة.' : 'الإعدادات المحفوظة غير مكتملة بعد.' }}
            </span>
        </div>
    </div>

    <form action="{{ route('internal.smtp-settings.update') }}" method="POST" class="card">
        @csrf
        @method('PUT')
        <div class="card-title">حفظ إعدادات خادم البريد</div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">تفعيل الإعدادات المحفوظة</span>
                <input type="checkbox" name="enabled" value="1" {{ old('enabled', $settings['enabled'] ?? false) ? 'checked' : '' }}>
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">المضيف</span>
                <input name="host" type="text" value="{{ old('host', $settings['host'] ?? '') }}" class="form-control" placeholder="smtp.example.com">
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">المنفذ</span>
                <input name="port" type="number" min="1" max="65535" value="{{ old('port', $settings['port'] ?? 587) }}" class="form-control">
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">التشفير</span>
                <select name="encryption" class="form-control">
                    @foreach(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'بدون تشفير'] as $value => $label)
                        <option value="{{ $value }}" {{ old('encryption', $settings['encryption'] ?? 'tls') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">اسم المستخدم</span>
                <input name="smtp_username" type="text" value="" class="form-control" placeholder="{{ $settings['username_masked'] ?? 'اتركه فارغًا للإبقاء على القيمة الحالية' }}">
                <span style="font-size:12px;color:var(--td)">
                    {{ ($settings['username_configured'] ?? false) ? 'اسم المستخدم الحالي مخفي. اترك الحقل فارغًا للإبقاء عليه، أو أدخل قيمة جديدة إذا كان الخادم يتطلب المصادقة.' : 'أدخل اسم المستخدم فقط إذا كان الخادم يتطلب المصادقة.' }}
                </span>
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">كلمة المرور / كلمة مرور التطبيق</span>
                <input name="smtp_password" type="password" value="" class="form-control" placeholder="{{ ($settings['password_configured'] ?? false) ? '********' : '' }}">
                <span style="font-size:12px;color:var(--td)">
                    {{ ($settings['password_configured'] ?? false) ? 'كلمة المرور الحالية محفوظة ومخفية. اترك الحقل فارغًا للإبقاء عليها.' : 'أدخل كلمة المرور فقط إذا كان الخادم يتطلب المصادقة.' }}
                </span>
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">اسم المرسل</span>
                <input name="from_name" type="text" value="{{ old('from_name', $settings['from_name'] ?? '') }}" class="form-control">
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">بريد المرسل</span>
                <input name="from_address" type="email" value="{{ old('from_address', $settings['from_address'] ?? '') }}" class="form-control">
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">اسم الردّ</span>
                <input name="reply_to_name" type="text" value="{{ old('reply_to_name', $settings['reply_to_name'] ?? '') }}" class="form-control">
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">بريد الردّ</span>
                <input name="reply_to_address" type="email" value="{{ old('reply_to_address', $settings['reply_to_address'] ?? '') }}" class="form-control">
            </label>

            <label style="display:grid;gap:8px">
                <span style="font-size:13px;color:var(--td)">مهلة الاتصال بالثواني</span>
                <input name="timeout" type="number" min="1" max="120" value="{{ old('timeout', $settings['timeout'] ?? 15) }}" class="form-control">
            </label>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
            <button type="submit" class="btn btn-s">حفظ إعدادات البريد</button>
            <span class="badge {{ ($settings['password_configured'] ?? false) ? 'badge-ok' : 'badge-muted' }}">
                {{ ($settings['password_configured'] ?? false) ? 'تم حفظ كلمة المرور' : 'لا توجد كلمة مرور محفوظة' }}
            </span>
        </div>
    </form>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
        <form action="{{ route('internal.smtp-settings.test-connection') }}" method="POST" class="card">
            @csrf
            <div class="card-title">اختبار الاتصال</div>
            <p style="margin:0 0 14px;color:var(--td);line-height:1.8">
                يتحقق هذا الاختبار من إمكانية الاتصال والمصادقة على خادم البريد المحفوظ دون إرسال رسالة فعلية إلى المستلمين.
            </p>
            <button type="submit" class="btn btn-ghost">اختبار الاتصال</button>
        </form>

        <form action="{{ route('internal.smtp-settings.test-email') }}" method="POST" class="card">
            @csrf
            <div class="card-title">إرسال رسالة تجريبية</div>
            <label style="display:grid;gap:8px;margin-bottom:14px">
                <span style="font-size:13px;color:var(--td)">وجهة الرسالة التجريبية</span>
                <input name="destination" type="email" value="{{ old('destination', auth()->user()->email ?? '') }}" class="form-control" placeholder="ops@example.com">
            </label>
            <button type="submit" class="btn btn-s">إرسال رسالة تجريبية</button>
        </form>
    </div>
</div>
@endsection
