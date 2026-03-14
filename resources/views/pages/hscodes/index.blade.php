@extends('layouts.app')
@section('title', 'أكواد HS')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">🔢 أكواد النظام المنسق (HS Codes)</h1>
    <button class="btn btn-pr" data-modal-open="add-hscode">+ كود جديد</button>
</div>

<x-card>
    <form method="GET" action="{{ route('hscodes.index') }}" style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالكود أو الوصف..." class="form-input" style="flex:2;min-width:250px">
        <select name="chapter" class="form-input" style="width:auto">
            <option value="">جميع الأقسام</option>
            @for($i = 1; $i <= 97; $i++)
                <option value="{{ $i }}" {{ request('chapter') == $i ? 'selected' : '' }}>القسم {{ $i }}</option>
            @endfor
        </select>
        <select name="restricted" class="form-input" style="width:auto">
            <option value="">الكل</option>
            <option value="1" {{ request('restricted') === '1' ? 'selected' : '' }}>مقيّد</option>
            <option value="0" {{ request('restricted') === '0' ? 'selected' : '' }}>غير مقيّد</option>
        </select>
        <button type="submit" class="btn btn-pr" style="height:42px">بحث</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>كود HS</th><th>الوصف (عربي)</th><th>الوصف (إنجليزي)</th><th>القسم</th><th>الرسوم %</th><th>القيود</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($hscodes ?? [] as $hs)
                    <tr>
                        <td class="td-mono" style="font-weight:600;font-size:14px">{{ $hs->code }}</td>
                        <td>{{ $hs->description_ar }}</td>
                        <td style="font-size:12px;color:var(--td)">{{ $hs->description_en }}</td>
                        <td><span class="badge badge-in">{{ $hs->chapter }}</span></td>
                        <td class="td-mono">{{ $hs->duty_rate ?? 0 }}%</td>
                        <td>
                            @if($hs->is_restricted)
                                <span class="badge badge-dg">🚫 مقيّد</span>
                            @elseif($hs->requires_license)
                                <span class="badge badge-wn">📄 يتطلب ترخيص</span>
                            @else
                                <span style="color:var(--ac)">✅ مسموح</span>
                            @endif
                        </td>
                        <td><button class="btn btn-s" style="font-size:12px">تعديل</button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">لا توجد أكواد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($hscodes ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $hscodes->links() }}</div>
    @endif
</x-card>

<x-modal id="add-hscode" title="إضافة كود HS" wide>
    <form method="POST" action="{{ route('hscodes.index') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">كود HS</label><input type="text" name="code" class="form-input" placeholder="مثال: 8471.30.00" required></div>
            <div><label class="form-label">القسم</label><input type="number" name="chapter" class="form-input" min="1" max="97"></div>
            <div><label class="form-label">الوصف (عربي)</label><input type="text" name="description_ar" class="form-input" required></div>
            <div><label class="form-label">الوصف (إنجليزي)</label><input type="text" name="description_en" class="form-input"></div>
            <div><label class="form-label">نسبة الرسوم %</label><input type="number" name="duty_rate" class="form-input" step="0.01" value="5"></div>
            <div><label class="form-label">القيود</label><select name="restriction" class="form-input"><option value="none">مسموح</option><option value="restricted">مقيّد</option><option value="license">يتطلب ترخيص</option></select></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">إضافة</button>
        </div>
    </form>
</x-modal>
@endsection
