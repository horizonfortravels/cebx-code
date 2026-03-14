@extends('layouts.app')
@section('title', 'ط¨ظˆط§ط¨ط© ط§ظ„ط£ط¹ظ…ط§ظ„ | ط§ظ„ط´ط­ظ†ط§طھ')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2b.dashboard') }}" style="color:inherit;text-decoration:none">ط¨ظˆط§ط¨ط© ط§ظ„ط£ط¹ظ…ط§ظ„</a>
            <span style="margin:0 6px">/</span>
            <span>ط§ظ„ط´ط­ظ†ط§طھ</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">ظ„ظˆط­ط© طھط´ط؛ظٹظ„ ط§ظ„ط´ط­ظ†ط§طھ</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            ظ‡ط°ظ‡ ط§ظ„طµظپط­ط© ظ…ط®طµطµط© ظ„ظ„ظپط±ظٹظ‚ ط§ظ„طھط´ط؛ظٹظ„ظٹ ظپظٹ <strong>{{ $account->name }}</strong>. ط±ط§ط¬ط¹ ط§ظ„ط­ط¬ظ… ط§ظ„ط­ط§ظ„ظٹ ظˆط§ظ„ظ…ظ‡ط§ظ… ط§ظ„ظ…ظپطھظˆط­ط© ظ‚ط¨ظ„ ط§ظ„ط§ظ†طھظ‚ط§ظ„ ط¥ظ„ظ‰ ط¥ط¯ط§ط±ط© ط§ظ„ط´ط­ظ†ط§طھ ط§ظ„ظƒط§ظ…ظ„ط©.
        </p>
    </div>
    @if($canCreateShipment)
        <a href="{{ $createRoute }}" class="btn btn-pr">بدء طلب شحنة لفريقك</a>
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
                    <th>ط§ظ„ظ…ط³طھظ„ظ…</th>
                    <th>ط§ظ„ط­ط§ظ„ط©</th>
                    <th>ط§ظ„طھظƒظ„ظپط©</th>
                </tr>
                </thead>
                <tbody>
                @forelse($shipments as $shipment)
                    <tr>
                        <td class="td-mono">{{ $shipment->reference_number ?? $shipment->tracking_number ?? $shipment->id }}</td>
                        <td>{{ $shipment->recipient_name ?? 'â€”' }}</td>
                        <td>{{ $shipment->status ?? 'â€”' }}</td>
                        <td>{{ number_format((float) ($shipment->total_charge ?? 0), 2) }} {{ $shipment->currency ?? 'SAR' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">ظ„ط§ طھظˆط¬ط¯ ط´ط­ظ†ط§طھ ط¨ط¹ط¯. ط§ط³طھط®ط¯ظ… ط¥ط¯ط§ط±ط© ط§ظ„ط´ط­ظ†ط§طھ ط§ظ„ظƒط§ظ…ظ„ط© ظ„ط¨ط¯ط، ط§ظ„طھط´ط؛ظٹظ„.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="طھط±ظƒظٹط² ط§ظ„ظپط±ظٹظ‚ ط§ظ„ظٹظˆظ…">
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="padding:14px;border:1px solid var(--bd);border-radius:14px">
                <div style="font-weight:700;color:var(--tx)">ظ…ط±ط§ط¬ط¹ط© ط§ظ„ط·ظˆط§ط¨ظٹط± ط§ظ„ظ…ظپطھظˆط­ط©</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">طھط­ظ‚ظ‚ ظ…ظ† ط§ظ„ط´ط­ظ†ط§طھ ط¨ط§ظ†طھط¸ط§ط± ط§ظ„ط´ط±ط§ط، ط£ظˆ ط§ظ„ط§ظ„طھظ‚ط§ط· ظ‚ط¨ظ„ ظ†ظ‡ط§ظٹط© ط§ظ„ظٹظˆظ… ط§ظ„طھط´ط؛ظٹظ„ظٹ.</div>
            </div>
            <div style="padding:14px;border:1px solid var(--bd);border-radius:14px">
                <div style="font-weight:700;color:var(--tx)">ط§ظ„ط§ظ†طھظ‚ط§ظ„ ظ„ظ„طھظپط§طµظٹظ„</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">ط²ط± "ط¥ط¯ط§ط±ط© ط§ظ„ط´ط­ظ†ط§طھ ط¨ط§ظ„ظƒط§ظ…ظ„" ظٹظ†ظ‚ظ„ظƒ ط¥ظ„ظ‰ ط§ظ„ط£ط¯ظˆط§طھ ط§ظ„ظƒط§ظ…ظ„ط© ظ„ظ„طھطµظپظٹط© ظˆط§ظ„طھطµط¯ظٹط± ظˆط§ظ„ط¹ظ…ظ„ظٹط§طھ ط§ظ„ظٹظˆظ…ظٹط©.</div>
            </div>
        </div>
    </x-card>
</div>
@endsection

