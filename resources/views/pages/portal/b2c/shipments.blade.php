@extends('layouts.app')
@section('title', 'ط¨ظˆط§ط¨ط© ط§ظ„ط£ظپط±ط§ط¯ | ط§ظ„ط´ط­ظ†ط§طھ')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2c.dashboard') }}" style="color:inherit;text-decoration:none">ط¨ظˆط§ط¨ط© ط§ظ„ط£ظپط±ط§ط¯</a>
            <span style="margin:0 6px">/</span>
            <span>ط§ظ„ط´ط­ظ†ط§طھ</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">ظ…ط³ط§ط­ط© ط§ظ„ط´ط­ظ†ط§طھ ط§ظ„ظپط±ط¯ظٹط©</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:720px">
            ط±ط§ط¬ط¹ ط¢ط®ط± ط´ط­ظ†ط§طھظƒ ظ…ظ† ط­ط³ط§ط¨ <strong>{{ $account->name }}</strong> ط¨ط³ط±ط¹ط©طŒ ط«ظ… ط§ظپطھط­ ظ…ط±ظƒط² ط§ظ„ط´ط­ظ†ط§طھ ط§ظ„ظƒط§ظ…ظ„ ط¹ظ†ط¯ظ…ط§ طھط­طھط§ط¬ ط¥ظ„ظ‰ ط¥ظ†ط´ط§ط، ط´ط­ظ†ط© ط£ظˆ ظ…طھط§ط¨ط¹ط© ط§ظ„طھظپط§طµظٹظ„ ط§ظ„طھط´ط؛ظٹظ„ظٹط©.
        </p>
    </div>
    @if($canCreateShipment)
        <a href="{{ $createRoute }}" class="btn btn-pr">بدء طلب شحنة</a>
    @endif
</div>

<div class="stats-grid" style="margin-bottom:24px">
    @foreach($stats as $stat)
        <x-stat-card :icon="$stat['icon']" :label="$stat['label']" :value="$stat['value']" />
    @endforeach
</div>

<div class="grid-2">
    <x-card title="ط¢ط®ط± ط§ظ„ط´ط­ظ†ط§طھ">
        <div style="overflow:auto">
            <table class="table">
                <thead>
                <tr>
                    <th>ط§ظ„ظ…ط±ط¬ط¹</th>
                    <th>ط§ظ„ظˆط¬ظ‡ط©</th>
                    <th>ط§ظ„ط­ط§ظ„ط©</th>
                    <th>ط§ظ„طھط§ط±ظٹط®</th>
                </tr>
                </thead>
                <tbody>
                @forelse($shipments as $shipment)
                    <tr>
                        <td class="td-mono">{{ $shipment->reference_number ?? $shipment->tracking_number ?? $shipment->id }}</td>
                        <td>{{ $shipment->recipient_city ?? 'ط؛ظٹط± ظ…ط­ط¯ط¯ط©' }}</td>
                        <td>{{ $shipment->status ?? 'â€”' }}</td>
                        <td>{{ optional($shipment->created_at)->format('Y-m-d') ?? 'â€”' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">ظ„ط§ طھظˆط¬ط¯ ط´ط­ظ†ط§طھ ط­طھظ‰ ط§ظ„ط¢ظ†. ط§ظپطھط­ ظ…ط±ظƒط² ط§ظ„ط´ط­ظ†ط§طھ ظ„ط¨ط¯ط، ط£ظˆظ„ ط·ظ„ط¨ ط´ط­ظ†.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="ظ…ط§ ط§ظ„ط°ظٹ ظٹظ…ظƒظ† ط¹ظ…ظ„ظ‡ ظ‡ظ†ط§طں">
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="padding:14px;border:1px solid var(--bd);border-radius:14px">
                <div style="font-weight:700;color:var(--tx)">ظ…طھط§ط¨ط¹ط© ط³ط±ظٹط¹ط©</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">طھط­ظ‚ظ‚ ظ…ظ† ط£ط­ط¯ط« ط´ط­ظ†ط§طھظƒ ظ‚ط¨ظ„ ط§ظ„ط§ظ†طھظ‚ط§ظ„ ط¥ظ„ظ‰ طµظپط­ط© ط§ظ„طھظپط§طµظٹظ„ ط§ظ„ظƒط§ظ…ظ„ط©.</div>
            </div>
            <div style="padding:14px;border:1px solid var(--bd);border-radius:14px">
                <div style="font-weight:700;color:var(--tx)">ط¥ط¬ط±ط§ط، ظˆط§ط¶ط­</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">ط§ط³طھط®ط¯ظ… ط²ط± "ظپطھط­ ظ…ط±ظƒط² ط§ظ„ط´ط­ظ†ط§طھ" ظ„ظ„ظˆطµظˆظ„ ط¥ظ„ظ‰ ط¥ظ†ط´ط§ط، ط§ظ„ط´ط­ظ†ط§طھ ظˆط§ظ„طھطµظپظٹط© ظˆط§ظ„طھطµط¯ظٹط±.</div>
            </div>
            <div style="padding:14px;border:1px solid var(--bd);border-radius:14px;background:rgba(59,130,246,.06)">
                <div style="font-weight:700;color:var(--tx)">ظ†طµظٹط­ط©</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">ط¥ط°ط§ ظƒظ†طھ طھطھط§ط¨ط¹ ط´ط­ظ†ط© ظ…ط­ط¯ط¯ط© ط§ظ„ط¢ظ†طŒ ط§ط³طھط®ط¯ظ… طµظپط­ط© ط§ظ„طھطھط¨ط¹ ظ…ظ† ط§ظ„ظ‚ط§ط¦ظ…ط© ط§ظ„ط¬ط§ظ†ط¨ظٹط© ظ„ظ„ظˆطµظˆظ„ ط§ظ„ط£ط³ط±ط¹.</div>
            </div>
        </div>
    </x-card>
</div>
@endsection


