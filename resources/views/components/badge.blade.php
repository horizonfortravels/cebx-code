@props(['status' => 'pending'])
@php
$map = [
    'delivered'        => ['تم التسليم', 'badge-ac'],
    'in_transit'       => ['في الطريق', 'badge-in'],
    'out_for_delivery' => ['خرج للتوصيل', 'badge-pp'],
    'pending'          => ['قيد الانتظار', 'badge-wn'],
    'processing'       => ['قيد المعالجة', 'badge-wn'],
    'shipped'          => ['تم الشحن', 'badge-in'],
    'cancelled'        => ['ملغي', 'badge-dg'],
    'returned'         => ['مرتجع', 'badge-dg'],
    'new'              => ['جديد', 'badge-in'],
    'open'             => ['مفتوحة', 'badge-ac'],
    'resolved'         => ['تم الحل', 'badge-pp'],
    'closed'           => ['مغلقة', 'badge-td'],
    'in_progress'      => ['قيد المعالجة', 'badge-in'],
    'connected'        => ['متصل', 'badge-ac'],
    'disconnected'     => ['غير متصل', 'badge-dg'],
    'accepted'         => ['مقبولة', 'badge-ac'],
    'expired'          => ['منتهية', 'badge-td'],
    'active'           => ['نشط', 'badge-ac'],
    'completed'        => ['مكتمل', 'badge-ac'],
    'failed'           => ['فشل', 'badge-dg'],
    'verified'         => ['موثق', 'badge-ac'],
    'rejected'         => ['مرفوض', 'badge-dg'],
    'under_review'     => ['قيد المراجعة', 'badge-in'],
    'cleared'          => ['تم التخليص', 'badge-ac'],
    'held'             => ['محتجزة', 'badge-dg'],
    'scheduled'        => ['مجدول', 'badge-in'],
    'departed'         => ['انطلقت', 'badge-pp'],
    'arrived'          => ['وصلت', 'badge-ac'],
    'delayed'          => ['متأخرة', 'badge-dg'],
];
$s = $map[$status] ?? [$status, 'badge-td'];
@endphp
<span class="badge {{ $s[1] }}">{{ $s[0] }}</span>
