@extends('layouts.app')
@section('title', 'تذكرة الدعم')

@section('content')
<div class="header-wrap" style="margin-bottom:24px;align-items:center">
    <a href="{{ route('support.index') }}" class="btn btn-s">العودة</a>
    <h1 style="font-size:20px;font-weight:800;color:var(--tx);margin:0">{{ $ticket->subject }}</h1>
    <x-badge :status="$ticket->status" />
</div>

<div class="grid-main-sidebar-tight">
    <div>
        <div class="card" style="margin-bottom:12px" data-testid="external-ticket-request-card">
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                    <div class="user-avatar" style="background:var(--pr)20;color:var(--pr)">{{ mb_substr($ticket->user->name ?? 'U', 0, 1) }}</div>
                    <div>
                        <div style="font-weight:600;font-size:13px">{{ $ticket->user->name ?? 'مقدم الطلب' }}</div>
                        <div style="font-size:11px;color:var(--tm)">{{ $ticket->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
                <p style="font-size:14px;line-height:1.8;color:var(--tx)">{{ $ticketBody }}</p>
            </div>
        </div>

        <div data-testid="external-ticket-thread-card">
            @foreach($threadItems as $threadItem)
                <div class="card" style="margin-bottom:12px;{{ !empty($threadItem['is_support_reply']) ? 'border-right:3px solid var(--pr)' : '' }}" data-testid="external-ticket-thread-entry">
                    <div class="card-body">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                            <div class="user-avatar" style="background:{{ !empty($threadItem['is_support_reply']) ? 'var(--pr)' : 'var(--ac)' }}20;color:{{ !empty($threadItem['is_support_reply']) ? 'var(--pr)' : 'var(--ac)' }}">
                                {{ mb_substr($threadItem['actor_name'] ?? 'U', 0, 1) }}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13px">
                                    {{ $threadItem['actor_name'] }}
                                    @if(!empty($threadItem['is_support_reply']))
                                        <span class="badge badge-in" style="margin-right:6px">الدعم</span>
                                    @endif
                                </div>
                                <div style="font-size:11px;color:var(--tm)">{{ $threadItem['created_at_label'] }}</div>
                            </div>
                        </div>
                        <p style="font-size:14px;line-height:1.8">{{ $threadItem['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        @if(!in_array($ticket->status, ['resolved', 'closed'], true))
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('support.reply', $ticket) }}" data-testid="external-ticket-reply-form">
                        @csrf
                        <label class="form-label">أضف ردًا</label>
                        <textarea name="body" class="form-input" rows="3" required data-testid="external-ticket-reply-body" placeholder="شارك مزيدًا من التفاصيل مع فريق الدعم."></textarea>
                        <div style="display:flex;gap:10px;margin-top:12px">
                            <button type="submit" class="btn btn-pr" data-testid="external-ticket-reply-submit">إرسال الرد</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <div>
        <x-card title="معلومات التذكرة">
            <x-info-row label="التذكرة" :value="($ticket->ticket_number ?? $ticket->reference_number ?? '—')" />
            <x-info-row label="الفئة" :value="['general' => 'عامة', 'shipment' => 'شحنة', 'shipping' => 'شحنة', 'billing' => 'الفوترة', 'technical' => 'تقنية', 'account' => 'الحساب', 'carrier' => 'شركة الشحن'][$ticket->category] ?? $ticket->category" />
            <x-info-row label="الأولوية" :value="['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'مرتفعة', 'urgent' => 'عاجلة'][$ticket->priority] ?? $ticket->priority" />
            <x-info-row label="تاريخ الإنشاء" :value="$ticket->created_at?->format('d/m/Y')" />
            @if($ticket->assignee)
                <x-info-row label="مسندة إلى" :value="$ticket->assignee->name" />
            @endif
        </x-card>

        @if($ticket->status !== 'resolved')
            <form method="POST" action="{{ route('support.resolve', $ticket) }}" style="margin-top:12px">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-ac" style="width:100%;background:var(--ac);color:#fff;border-color:var(--ac)">وضعها كمحلولة</button>
            </form>
        @endif
    </div>
</div>
@endsection
