@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? __('portal_addresses.common.address_book')) . ' | ' . __('portal_addresses.common.address_book'))

@section('content')
@php
    $typeLabel = static function (?string $type): string {
        $resolved = (string) ($type ?: 'both');
        $key = 'portal_addresses.types.' . $resolved;
        $translated = __($key);

        return $translated === $key ? $resolved : $translated;
    };
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route($portalConfig['dashboard_route']) }}" style="color:inherit;text-decoration:none">{{ $portalConfig['label'] }}</a>
            <span style="margin:0 6px">/</span>
            <span>{{ __('portal_addresses.common.address_book') }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">{{ $copy['title'] }}</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">{{ $copy['description'] }}</p>
    </div>
    @if($canManageAddresses)
        <a href="{{ route($portalConfig['create_route']) }}" class="btn btn-pr" data-testid="address-book-create-link">{{ $copy['create_cta'] }}</a>
    @endif
</div>

<div class="stats-grid" style="margin-bottom:24px">
    @foreach($stats as $stat)
        <x-stat-card :icon="$stat['icon']" :label="$stat['label']" :value="$stat['value']" />
    @endforeach
</div>

<div class="grid-2">
    <x-card :title="__('portal_addresses.common.address_book')">
        <div style="overflow:auto">
            <table class="table">
                <thead>
                <tr>
                    <th>{{ __('portal_addresses.common.label') }}</th>
                    <th>{{ __('portal_addresses.common.contact_name') }}</th>
                    <th>{{ __('portal_addresses.common.type') }}</th>
                    <th>{{ __('portal_addresses.common.location') }}</th>
                    <th>{{ __('portal_addresses.common.updated_at') }}</th>
                    <th>{{ __('portal_addresses.common.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($addresses as $address)
                    @php
                        $location = collect([$address->city, $address->country])
                            ->filter(fn ($value) => filled($value))
                            ->implode(' / ');
                    @endphp
                    <tr>
                        <td style="font-weight:700;color:var(--tx)">{{ $address->label ?: $address->contact_name }}</td>
                        <td>
                            <div>{{ $address->contact_name }}</div>
                            <div style="font-size:12px;color:var(--tm)">{{ $address->phone ?? __('portal_addresses.common.not_available') }}</div>
                        </td>
                        <td>{{ $typeLabel($address->type) }}</td>
                        <td>{{ $location !== '' ? $location : __('portal_addresses.common.not_available') }}</td>
                        <td>{{ optional($address->updated_at)->format('Y-m-d H:i') ?? __('portal_addresses.common.not_available') }}</td>
                        <td>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                @if(in_array((string) $address->type, ['sender', 'both'], true))
                                    <a
                                        href="{{ route($portalConfig['shipment_create_route'], ['sender_address' => (string) $address->id]) }}"
                                        class="btn btn-s"
                                        data-testid="address-use-sender-{{ $address->id }}"
                                    >{{ __('portal_addresses.common.use_as_sender') }}</a>
                                @endif
                                @if(in_array((string) $address->type, ['recipient', 'both'], true))
                                    <a
                                        href="{{ route($portalConfig['shipment_create_route'], ['recipient_address' => (string) $address->id]) }}"
                                        class="btn btn-s"
                                        data-testid="address-use-recipient-{{ $address->id }}"
                                    >{{ __('portal_addresses.common.use_as_recipient') }}</a>
                                @endif
                                @if($canManageAddresses)
                                    <a href="{{ route($portalConfig['edit_route'], ['id' => (string) $address->id]) }}" class="btn btn-s" data-testid="address-edit-{{ $address->id }}">{{ __('portal_addresses.common.edit') }}</a>
                                    <form method="POST" action="{{ route($portalConfig['destroy_route'], ['id' => (string) $address->id]) }}" style="display:inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-s" data-testid="address-delete-{{ $address->id }}">{{ __('portal_addresses.common.delete') }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-state">{{ $copy['empty_state'] }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card :title="$copy['guidance_title']">
        <div style="display:flex;flex-direction:column;gap:12px">
            @foreach($copy['guidance_cards'] as $card)
                <div style="padding:14px;border:1px solid var(--bd);border-radius:14px;background:{{ $loop->last ? 'rgba(37,99,235,.06)' : 'transparent' }}">
                    <div style="font-weight:700;color:var(--tx)">{{ $card['title'] }}</div>
                    <div style="color:var(--td);font-size:13px;margin-top:4px">{{ $card['body'] }}</div>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
@endsection
