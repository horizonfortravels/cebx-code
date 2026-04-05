@extends('layouts.app')
@section('title', 'بوابة الأعمال | مفاتيح API')

@section('content')
<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / واجهة المطور / مفاتيح API"
        title="مفاتيح API"
        subtitle="أنشئ مفاتيح المنظمة الخاصة بالتكامل مع المنصة وراجع حالتها وصلاحياتها من دون مغادرة الواجهة التشغيلية."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        <a href="{{ route('b2b.developer.index') }}" class="btn btn-s">العودة إلى واجهة المطور</a>
    </x-page-header>

    <div class="stats-grid b2b-metrics-grid">
        @foreach($workspaceStats as $stat)
            <x-stat-card
                :iconName="$stat['iconName']"
                :label="$stat['label']"
                :value="$stat['value']"
                :meta="$stat['meta']"
                :eyebrow="$stat['eyebrow']"
            />
        @endforeach
    </div>

    @if($newApiKey)
        <section class="b2b-signal-banner">
            <div class="b2b-signal-banner__title">تم إنشاء مفتاح جديد</div>
            <p class="b2b-signal-banner__body">هذه هي المرة الوحيدة التي ستظهر فيها القيمة الكاملة للمفتاح داخل المتصفح، لذا احفظها مباشرة في بيئة آمنة.</p>
            <code class="b2b-code-block">{{ $newApiKey }}</code>
        </section>
    @endif

    <div class="b2b-workspace-grid">
        <section class="b2b-panel-stack">
            <x-card title="المفاتيح الحالية">
                <div class="b2b-table-shell">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>البادئة</th>
                            <th>الصلاحيات</th>
                            <th>الحالة</th>
                            <th>إجراء</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($apiKeys as $key)
                            <tr>
                                <td>{{ $key->name }}</td>
                                <td class="td-mono">{{ $key->key_prefix }}...</td>
                                <td>{{ collect($key->permissions ?? [])->implode('، ') ?: 'بدون صلاحيات محددة' }}</td>
                                <td><span class="b2b-status-pill b2b-status-pill--{{ $key->is_active ? 'success' : 'neutral' }}">{{ $key->is_active ? 'نشط' : 'ملغي' }}</span></td>
                                <td>
                                    @if($key->is_active && auth()->user()->hasPermission('api_keys.manage'))
                                        <form method="POST" action="{{ route('b2b.developer.api-keys.revoke', $key->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-s">إلغاء</button>
                                        </form>
                                    @else
                                        <span class="b2b-table-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">لا توجد مفاتيح API حالية لهذا المستخدم. ابدأ بمفتاح جديد عندما تحتاج إلى ربط أنظمتك الداخلية بالمنصة.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="إنشاء مفتاح جديد">
                @if(auth()->user()->hasPermission('api_keys.manage'))
                    <form method="POST" action="{{ route('b2b.developer.api-keys.store') }}" class="b2b-form-stack">
                        @csrf
                        <div>
                            <label class="form-label">اسم المفتاح</label>
                            <input type="text" name="name" class="form-input" value="{{ old('name') }}" placeholder="مثال: مزامنة النظام الداخلي" required>
                        </div>

                        <div>
                            <label class="form-label">الصلاحيات</label>
                            <div class="b2b-checkbox-stack">
                                @foreach($scopeOptions as $scope => $meta)
                                    <label class="b2b-check-card">
                                        <input type="checkbox" name="permissions[]" value="{{ $scope }}" {{ in_array($scope, old('permissions', []), true) ? 'checked' : '' }}>
                                        <span>
                                            <strong>{{ $meta['label'] }}</strong>
                                            <small>{{ $meta['description'] }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="form-label">الحد الأقصى للطلبات في الدقيقة</label>
                            <input type="number" name="rate_limit_per_minute" class="form-input" min="10" max="300" value="{{ old('rate_limit_per_minute', 60) }}">
                        </div>

                        <div>
                            <label class="form-label">تاريخ الانتهاء</label>
                            <input type="date" name="expires_at" class="form-input" value="{{ old('expires_at') }}">
                        </div>

                        <button type="submit" class="btn btn-pr">إنشاء المفتاح</button>
                    </form>
                @else
                    <div class="b2b-inline-empty">لديك صلاحية عرض المفاتيح فقط. إنشاء أو إلغاء المفاتيح يحتاج دوراً يتضمن <code>api_keys.manage</code>.</div>
                @endif
            </x-card>
        </aside>
    </div>
</div>
@endsection
