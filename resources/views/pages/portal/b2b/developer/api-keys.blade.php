@extends('layouts.app')
@section('title', 'بوابة الأعمال | مفاتيح API')

@section('content')
<div style="display:grid;gap:24px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div>
            <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
                <a href="{{ route('b2b.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأعمال</a>
                <span style="margin:0 6px">/</span>
                <a href="{{ route('b2b.developer.index') }}" style="color:inherit;text-decoration:none">واجهة المطور</a>
                <span style="margin:0 6px">/</span>
                <span>مفاتيح API</span>
            </div>
            <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">مفاتيح API الخاصة بك</h1>
            <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
                أنشئ مفاتيحك الخاصة للتكامل مع أنظمة منظمتك عبر واجهات المنصة، وراجع المفاتيح النشطة لهذا المستخدم.
                المفتاح الكامل يظهر مرة واحدة فقط بعد الإنشاء، لذلك احفظه مباشرة في بيئة آمنة.
            </p>
        </div>
    </div>

    @if($newApiKey)
        <section class="card" style="border:1px solid #bfdbfe;background:#eff6ff">
            <div class="card-title">تم إنشاء مفتاح جديد</div>
            <p style="margin:0 0 12px;color:var(--td);line-height:1.8">
                هذه هي المرة الوحيدة التي ستظهر فيها قيمة المفتاح كاملة داخل المتصفح.
            </p>
            <code style="display:block;padding:14px;border-radius:14px;background:#0f172a;color:#fff;direction:ltr;text-align:left;overflow:auto">{{ $newApiKey }}</code>
        </section>
    @endif

    <section style="display:grid;grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);gap:18px">
        <article class="card">
            <div class="card-title">المفاتيح الحالية</div>
            <div style="overflow:auto">
                <table class="table">
                    <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>المقدمة</th>
                        <th>الصلاحيات</th>
                        <th>الحالة</th>
                        <th>إجراء</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($apiKeys as $key)
                        <tr>
                            <td>{{ $key->name }}</td>
                            <td class="td-mono">{{ $key->key_prefix }}…</td>
                            <td>{{ collect($key->permissions ?? [])->implode('، ') }}</td>
                            <td>{{ $key->is_active ? 'نشط' : 'ملغى' }}</td>
                            <td>
                                @if($key->is_active && auth()->user()->hasPermission('api_keys.manage'))
                                    <form method="POST" action="{{ route('b2b.developer.api-keys.revoke', $key->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost">إلغاء</button>
                                    </form>
                                @else
                                    <span style="color:var(--td)">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">لا توجد مفاتيح حالية لهذا المستخدم. ابدأ بإنشاء مفتاح جديد.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="card">
            <div class="card-title">إنشاء مفتاح جديد</div>
            @if(auth()->user()->hasPermission('api_keys.manage'))
                <form method="POST" action="{{ route('b2b.developer.api-keys.store') }}" style="display:grid;gap:14px">
                    @csrf
                    <div>
                        <label class="form-label">اسم المفتاح</label>
                        <input type="text" name="name" class="form-input" value="{{ old('name') }}" placeholder="مثال: ERP Sync Key" required>
                    </div>

                    <div>
                        <label class="form-label">الصلاحيات</label>
                        <div style="display:grid;gap:10px;margin-top:8px">
                            @foreach($scopeOptions as $scope => $meta)
                                <label style="display:block;padding:12px;border:1px solid var(--bd);border-radius:14px;background:#fff">
                                    <input type="checkbox" name="permissions[]" value="{{ $scope }}" {{ in_array($scope, old('permissions', []), true) ? 'checked' : '' }}>
                                    <strong style="margin-inline-start:6px">{{ $meta['label'] }}</strong>
                                    <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $meta['description'] }}</div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="form-label">الحد الأقصى للطلبات في الدقيقة</label>
                        <input type="number" name="rate_limit_per_minute" class="form-input" min="10" max="300" value="{{ old('rate_limit_per_minute', 60) }}">
                    </div>

                    <div>
                        <label class="form-label">تاريخ الانتهاء (اختياري)</label>
                        <input type="date" name="expires_at" class="form-input" value="{{ old('expires_at') }}">
                    </div>

                    <button type="submit" class="btn btn-pr">إنشاء المفتاح</button>
                </form>
            @else
                <div class="empty-state">
                    لديك صلاحية عرض المفاتيح فقط. طلب إنشاء أو إلغاء المفاتيح يحتاج دورًا يتضمن <code>api_keys.manage</code>.
                </div>
            @endif
        </article>
    </section>
</div>
@endsection
