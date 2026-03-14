@extends('layouts.app')
@section('title', 'الدعوات')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0">📨 الدعوات</h1>
    <button type="button" class="btn btn-pr" data-modal-open="invite">+ دعوة جديدة</button>
</div>

<x-card>
    <div class="table-wrap">
        <table>
            <thead><tr><th>البريد الإلكتروني</th><th>الدور</th><th>الحالة</th><th>تاريخ الإرسال</th><th></th></tr></thead>
            <tbody>
                @forelse($invitations as $inv)
                    <tr>
                        <td style="font-size:13px">{{ $inv->email }}</td>
                        <td><span class="badge badge-in">{{ $inv->role_name }}</span></td>
                        <td><x-badge :status="$inv->status" /></td>
                        <td style="font-size:12px;color:var(--tm)">{{ $inv->created_at->format('Y-m-d') }}</td>
                        <td>
                            @if($inv->status === 'pending')
                                <button class="btn btn-dg" style="font-size:12px;padding:5px 14px">إلغاء</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">لا توجد دعوات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invitations->hasPages())
        <div style="margin-top:14px">{{ $invitations->links() }}</div>
    @endif
</x-card>

<x-modal id="invite" title="دعوة مستخدم جديد">
    <form method="POST" action="{{ route('invitations.store') }}">
        @csrf
        <div style="margin-bottom:16px"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" class="form-input" required></div>
        <div style="margin-bottom:16px"><label class="form-label">الاسم</label><input type="text" name="name" class="form-input"></div>
        <div style="margin-bottom:16px"><label class="form-label">الدور</label>
            <select name="role_name" class="form-input"><option>مشغّل</option><option>مشرف</option><option>مُطلع</option></select>
        </div>
        <button type="submit" class="btn btn-pr" style="width:100%">إرسال الدعوة</button>
    </form>
</x-modal>
@endsection
