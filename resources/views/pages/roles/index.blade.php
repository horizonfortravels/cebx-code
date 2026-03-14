@extends('layouts.app')
@section('title', 'الأدوار والصلاحيات')

@section('content')
<h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0 0 24px">🔐 الأدوار والصلاحيات</h1>

@php
$permissions = ['عرض الشحنات','إنشاء شحنة','إلغاء شحنة','عرض الطلبات','إدارة المتاجر','عرض المحفظة','شحن الرصيد','عرض التقارير','إدارة المستخدمين','إدارة الأدوار','الإعدادات'];
$roles = [
    ['name' => 'مدير',   'perms' => [1,1,1,1,1,1,1,1,1,1,1]],
    ['name' => 'مشرف',   'perms' => [1,1,1,1,1,1,1,1,0,0,0]],
    ['name' => 'مشغّل',  'perms' => [1,1,1,1,0,0,0,0,0,0,0]],
    ['name' => 'مُطلع',  'perms' => [1,0,0,1,0,0,0,1,0,0,0]],
];
@endphp

<x-card>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th style="font-weight:700;color:var(--tx);font-size:13px;position:sticky;right:0;background:#fff">الصلاحية</th>
                @foreach($roles as $role)
                    <th style="text-align:center;color:var(--pr);font-weight:700;font-size:13px">{{ $role['name'] }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach($permissions as $pi => $perm)
                    <tr style="background:{{ $pi % 2 === 0 ? '#FAFBFE' : '#fff' }}">
                        <td style="padding:12px;font-size:13px;font-weight:500">{{ $perm }}</td>
                        @foreach($roles as $role)
                            <td style="text-align:center;padding:12px">
                                <span style="font-size:18px">{{ $role['perms'][$pi] ? '✅' : '❌' }}</span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
@endsection
