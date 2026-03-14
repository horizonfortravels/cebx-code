@extends('layouts.app')
@section('title', 'الإشعارات')
@section('content')
<x-page-header title="الإشعارات" :subtitle="$subtitle ?? null">
    {{-- FIX #2: زر "قراءة الكل" كان مفقود من الواجهة --}}
    <form action="{{ route('notifications.readAll') }}" method="POST" style="display:inline">
        @csrf
        <button class="btn btn-s" type="submit">✓ قراءة الكل</button>
    </form>
</x-page-header>

@if(isset($stats) && count($stats ?? []))
<div class="stats-grid">
    @foreach($stats as $st)
        <x-stat-card :icon="$st['icon']" :label="$st['label']" :value="$st['value']" :trend="$st['trend'] ?? null" :up="$st['up'] ?? true" />
    @endforeach
</div>
@endif

@if(isset($columns) && isset($rows))
<div class="table-wrap"><table>
    <thead><tr>@foreach($columns as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
    <tbody>
        @forelse($rows as $row)
            <tr>@foreach($row as $cell)<td>{!! $cell !!}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" class="empty-state">لا توجد إشعارات</td></tr>
        @endforelse
    </tbody>
</table></div>
@if(isset($pagination)) <div style="margin-top:14px">{{ $pagination->links() }}</div> @endif
@endif
@endsection
