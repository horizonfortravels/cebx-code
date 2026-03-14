@extends('layouts.app')
@section('title', 'الحاويات')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">🚢 الحاويات</h1>
    <button class="btn btn-pr" data-modal-open="add-container">+ حاوية جديدة</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="🚢" label="إجمالي الحاويات" :value="$totalContainers ?? 0" />
    <x-stat-card icon="🟢" label="متاحة" :value="$availableCount ?? 0" />
    <x-stat-card icon="🚚" label="في الطريق" :value="$inTransitCount ?? 0" />
    <x-stat-card icon="📍" label="في الميناء" :value="$atPortCount ?? 0" />
</div>

<x-card>
    <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
        <input type="text" placeholder="بحث برقم الحاوية..." class="form-input" style="flex:1;min-width:200px">
        <select class="form-input" style="width:auto">
            <option value="">جميع الأنواع</option>
            <option>20ft Standard</option>
            <option>40ft Standard</option>
            <option>40ft High Cube</option>
            <option>Reefer</option>
        </select>
        <select class="form-input" style="width:auto">
            <option value="">جميع الحالات</option>
            <option>متاحة</option>
            <option>محملة</option>
            <option>في الطريق</option>
            <option>في الميناء</option>
        </select>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>رقم الحاوية</th><th>النوع</th><th>الحجم</th><th>السفينة</th><th>ميناء المغادرة</th><th>ميناء الوصول</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($containers ?? [] as $container)
                    @php
                        $stMap = ['available' => ['متاحة', 'badge-ac'], 'loaded' => ['محملة', 'badge-in'], 'in_transit' => ['في الطريق', 'badge-pp'], 'at_port' => ['في الميناء', 'badge-wn'], 'customs_hold' => ['احتجاز جمركي', 'badge-dg']];
                        $st = $stMap[$container->status] ?? ['—', 'badge-td'];
                    @endphp
                    <tr>
                        <td class="td-mono" style="font-weight:600">{{ $container->container_number }}</td>
                        <td>{{ $container->type }}</td>
                        <td>{{ $container->size }}</td>
                        <td>{{ $container->vessel->name ?? '—' }}</td>
                        <td>{{ $container->origin_port ?? '—' }}</td>
                        <td>{{ $container->destination_port ?? '—' }}</td>
                        <td><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></td>
                        <td><button class="btn btn-s" style="font-size:12px">تفاصيل</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">لا توجد حاويات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($containers ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $containers->links() }}</div>
    @endif
</x-card>

<x-modal id="add-container" title="إضافة حاوية">
    <form method="POST" action="{{ route('containers.index') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">رقم الحاوية</label><input type="text" name="container_number" class="form-input" placeholder="ABCD1234567" required></div>
            <div><label class="form-label">النوع</label><select name="type" class="form-input"><option>Standard</option><option>High Cube</option><option>Reefer</option><option>Open Top</option><option>Flat Rack</option></select></div>
            <div><label class="form-label">الحجم</label><select name="size" class="form-input"><option>20ft</option><option>40ft</option><option>45ft</option></select></div>
            <div><label class="form-label">السفينة</label><select name="vessel_id" class="form-input"><option value="">— اختر السفينة —</option></select></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">إضافة</button>
        </div>
    </form>
</x-modal>
@endsection
