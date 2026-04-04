@extends('layouts.app')
@section('title', 'تذكرة: ' . ($ticket->reference_number ?? ''))

@section('content')
<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
    <a href="{{ route('support.index') }}" class="btn btn-s">→ رجوع</a>
    <h1 style="font-size:20px;font-weight:800;color:var(--tx);margin:0">{{ $ticket->subject }}</h1>
    <x-badge :status="$ticket->status" />
</div>

<div class="grid-2-1">
    {{-- Messages --}}
    <div>
        {{-- Original --}}
        <div class="card" style="margin-bottom:12px">
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                    <div class="user-avatar" style="background:var(--pr)20;color:var(--pr)">{{ mb_substr($ticket->user->name ?? 'م', 0, 1) }}</div>
                    <div>
                        <div style="font-weight:600;font-size:13px">{{ $ticket->user->name ?? 'المستخدم' }}</div>
                        <div style="font-size:11px;color:var(--tm)">{{ $ticket->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
                <p style="font-size:14px;line-height:1.8;color:var(--tx)">{{ $ticket->body }}</p>
            </div>
        </div>

        {{-- Replies --}}
        @foreach($ticket->replies as $reply)
            <div class="card" style="margin-bottom:12px;{{ $reply->is_agent ? 'border-right:3px solid var(--pr)' : '' }}">
                <div class="card-body">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                        <div class="user-avatar" style="background:{{ $reply->is_agent ? 'var(--pr)' : 'var(--ac)' }}20;color:{{ $reply->is_agent ? 'var(--pr)' : 'var(--ac)' }}">{{ mb_substr($reply->user->name ?? 'م', 0, 1) }}</div>
                        <div>
                            <div style="font-weight:600;font-size:13px">{{ $reply->user->name ?? 'الدعم' }} @if($reply->is_agent)<span class="badge badge-in" style="margin-right:6px">فريق الدعم</span>@endif</div>
                            <div style="font-size:11px;color:var(--tm)">{{ $reply->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                    <p style="font-size:14px;line-height:1.8">{{ $reply->body }}</p>
                </div>
            </div>
        @endforeach

        {{-- Reply Form --}}
        @if($ticket->status !== 'resolved' && $ticket->status !== 'closed')
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('support.reply', $ticket) }}">
                        @csrf
                        <label class="form-label">إضافة رد</label>
                        <textarea name="body" class="form-input" rows="3" required placeholder="اكتب ردك هنا..."></textarea>
                        <div style="display:flex;gap:10px;margin-top:12px">
                            <button type="submit" class="btn btn-pr">إرسال الرد</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    {{-- Sidebar info --}}
    <div>
        <x-card title="📋 معلومات التذكرة">
            <x-info-row label="الرقم المرجعي" :value="$ticket->reference_number ?? '—'" />
            <x-info-row label="الفئة" :value="['general'=>'عامة','shipment'=>'شحنات','billing'=>'مالية','technical'=>'تقنية'][$ticket->category] ?? $ticket->category" />
            <x-info-row label="الأولوية" :value="['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'][$ticket->priority] ?? $ticket->priority" />
            <x-info-row label="تاريخ الإنشاء" :value="$ticket->created_at->format('d/m/Y')" />
            @if($ticket->assignee)
                <x-info-row label="المسؤول" :value="$ticket->assignee->name" />
            @endif
        </x-card>

        @if($ticket->status !== 'resolved')
            <form method="POST" action="{{ route('support.resolve', $ticket) }}" style="margin-top:12px">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-ac" style="width:100%;background:var(--ac);color:#fff;border-color:var(--ac)">✓ تحديد كمحلولة</button>
            </form>
        @endif
    </div>
</div>
@endsection
