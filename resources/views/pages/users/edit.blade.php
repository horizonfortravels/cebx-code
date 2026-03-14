@extends('layouts.app')
@section('title', 'تعديل مستخدم — ' . ($user->name ?? ''))

@section('content')
<div style="margin-bottom:24px">
    <a href="{{ route('users.index') }}" style="font-size:13px;color:var(--td);text-decoration:none">← العودة للمستخدمين</a>
    <h1 style="font-size:22px;font-weight:700;color:var(--tx);margin:8px 0 0">✏️ تعديل مستخدم — {{ $user->name ?? '' }}</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px">
    {{-- Main Form --}}
    <div>
        <x-card title="👤 المعلومات الأساسية">
            <form method="POST" action="{{ route('users.update', $user) }}">
                @csrf @method('PATCH')

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                    <div>
                        <label class="form-label">الاسم الكامل</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-input" required>
                        @error('name') <span style="color:var(--dg);font-size:12px">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-input" required>
                        @error('email') <span style="color:var(--dg);font-size:12px">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">المسمى الوظيفي</label>
                        <input type="text" name="job_title" value="{{ old('job_title', $user->job_title) }}" class="form-input">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
                    <div>
                        <label class="form-label">الدور</label>
                        <select name="role_name" class="form-input">
                            @foreach(['مدير', 'مشرف', 'مشغّل', 'مُطلع'] as $role)
                                <option {{ ($user->role_name ?? '') === $role ? 'selected' : '' }}>{{ $role }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">الحالة</label>
                        <select name="is_active" class="form-input">
                            <option value="1" {{ $user->is_active ? 'selected' : '' }}>نشط</option>
                            <option value="0" {{ !$user->is_active ? 'selected' : '' }}>معطّل</option>
                        </select>
                    </div>
                </div>

                @if($portalType === 'admin')
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
                        <div>
                            <label class="form-label">المنظمة</label>
                            <select name="organization_id" class="form-input">
                                @foreach($organizations ?? [] as $org)
                                    <option value="{{ $org->id }}" {{ ($user->organization_id ?? '') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">الفرع</label>
                            <select name="branch_id" class="form-input">
                                <option value="">— بدون فرع —</option>
                                @foreach($branches ?? [] as $branch)
                                    <option value="{{ $branch->id }}" {{ ($user->branch_id ?? '') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid var(--bd)">
                    <a href="{{ route('users.index') }}" class="btn btn-s">إلغاء</a>
                    <button type="submit" class="btn btn-pr">حفظ التغييرات</button>
                </div>
            </form>
        </x-card>

        {{-- Password Reset --}}
        <x-card title="🔑 إعادة تعيين كلمة المرور">
            <form method="POST" action="{{ route('users.update', $user) }}">
                @csrf @method('PATCH')
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                    <div>
                        <label class="form-label">كلمة المرور الجديدة</label>
                        <input type="password" name="password" class="form-input" placeholder="••••••••">
                        @error('password') <span style="color:var(--dg);font-size:12px">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="form-label">تأكيد كلمة المرور</label>
                        <input type="password" name="password_confirmation" class="form-input" placeholder="••••••••">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-pr">تحديث كلمة المرور</button>
                </div>
            </form>
        </x-card>
    </div>

    {{-- Sidebar --}}
    <div style="display:flex;flex-direction:column;gap:16px">
        <x-card title="📋 معلومات الحساب">
            <div style="text-align:center;margin-bottom:16px">
                <div style="width:64px;height:64px;border-radius:50%;background:rgba(124,58,237,0.15);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;color:#7C3AED;margin:0 auto 10px">
                    {{ mb_substr($user->name ?? 'م', 0, 1) }}
                </div>
                <div style="font-weight:700;font-size:16px;color:var(--tx)">{{ $user->name }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $user->email }}</div>
            </div>
            <x-info-row label="المعرّف" :value="'#' . $user->id" />
            <x-info-row label="الحالة" :value="$user->is_active ? '🟢 نشط' : '🔴 معطّل'" />
            <x-info-row label="الدور" :value="$user->role_name ?? '—'" />
            <x-info-row label="تاريخ التسجيل" :value="$user->created_at->format('Y-m-d')" />
            <x-info-row label="آخر دخول" :value="$user->last_login_at?->diffForHumans() ?? 'لم يسجل دخول'" />
        </x-card>

        <x-card title="📊 الإحصائيات">
            <x-info-row label="الشحنات" :value="number_format($user->shipments_count ?? 0)" />
            <x-info-row label="الطلبات" :value="number_format($user->orders_count ?? 0)" />
            <x-info-row label="التذاكر" :value="$user->tickets_count ?? 0" />
        </x-card>

        {{-- Danger Zone --}}
        <x-card>
            <div style="padding:4px 0">
                <div style="font-weight:600;color:var(--dg);font-size:14px;margin-bottom:8px">⚠️ منطقة الخطر</div>
                <p style="font-size:12px;color:var(--td);margin:0 0 12px">هذه الإجراءات لا يمكن التراجع عنها.</p>
                <form method="POST" action="{{ route('users.update', $user) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                    @csrf @method('PATCH')
                    <input type="hidden" name="is_active" value="{{ $user->is_active ? '0' : '1' }}">
                    <button type="submit" class="btn btn-s" style="width:100%;color:var(--dg)">
                        {{ $user->is_active ? '🚫 تعطيل الحساب' : '✅ تفعيل الحساب' }}
                    </button>
                </form>
            </div>
        </x-card>
    </div>
</div>
@endsection
