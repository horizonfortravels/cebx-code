@extends('layouts.app')
@section('title', 'بوابة الأفراد | الإعدادات')

@section('content')
@php
    $verificationLabel = match (true) {
        $kycVerification?->isApproved() => 'مقبول',
        $kycVerification?->isPending() => 'قيد المراجعة',
        $kycVerification?->isRejected() => 'يحتاج تصحيحًا',
        $kycVerification?->isExpired() => 'منتهي الصلاحية',
        $kycVerification?->isUnverified() => 'غير مكتمل',
        default => 'غير متاح',
    };
@endphp

<x-page-header
    eyebrow="بوابة الأفراد"
    title="إعدادات الحساب"
    subtitle="هذه الصفحة تعرض لك صورة صادقة عن بيانات الحساب الحالية وما سنضيفه لاحقًا من تفضيلات وإعدادات شخصية داخل البوابة."
    :meta="'التحقق الحالي: ' . $verificationLabel"
>
    <a href="{{ route('b2c.addresses.index') }}" class="btn btn-s">العناوين</a>
    <a href="{{ route('b2c.wallet.index') }}" class="btn btn-pr">المحفظة</a>
</x-page-header>

<div class="b2c-workspace-grid">
    <x-card title="بيانات الحساب الحالية">
        <div class="b2c-placeholder-summary">
            <div class="b2c-placeholder-summary__item">
                <span>اسم الحساب</span>
                <strong>{{ $account->name ?? 'الحساب الفردي' }}</strong>
            </div>
            <div class="b2c-placeholder-summary__item">
                <span>اسم المستخدم</span>
                <strong>{{ $currentUser->name ?? 'غير محدد' }}</strong>
            </div>
            <div class="b2c-placeholder-summary__item">
                <span>البريد الإلكتروني</span>
                <strong>{{ $currentUser->email ?? 'غير محدد' }}</strong>
            </div>
            <div class="b2c-placeholder-summary__item">
                <span>حالة التحقق</span>
                <strong>{{ $verificationLabel }}</strong>
            </div>
            <div class="b2c-placeholder-summary__item">
                <span>حالة المحفظة</span>
                <strong>{{ $wallet ? ($wallet->isFrozen() ? 'متوقفة مؤقتًا' : 'نشطة') : 'غير مفعلة' }}</strong>
            </div>
        </div>
    </x-card>

    <x-card title="ما الذي سيظهر هنا لاحقًا؟">
        <div class="b2c-guidance-stack">
            <div class="b2c-guidance-card">
                <div class="b2c-guidance-card__title">تفضيلات الإشعارات</div>
                <div class="b2c-guidance-card__body">سنضيف لاحقًا إعدادات الإشعارات والتنبيهات الشخصية داخل هذه الصفحة بدل تركها مبعثرة بين الشاشات المختلفة.</div>
            </div>
            <div class="b2c-guidance-card b2c-guidance-card--accent">
                <div class="b2c-guidance-card__title">صيانة البيانات الأساسية</div>
                <div class="b2c-guidance-card__body">إلى أن نفتح تحرير الإعدادات الشخصية هنا، يمكنك الاستفادة من العناوين والمحفظة والشحنات من الصفحات المتخصصة الحالية دون تعارض أو التباس.</div>
            </div>
        </div>

        <div class="b2c-inline-actions">
            <a href="{{ route('b2c.addresses.index') }}" class="btn btn-pr">إدارة العناوين</a>
            <a href="{{ route('b2c.support.index') }}" class="btn btn-s">الدعم</a>
        </div>
    </x-card>
</div>
@endsection
