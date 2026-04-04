@extends('layouts.app')

@section('title', $dashboard['page_title'] ?? 'المساحة الداخلية')

@section('content')
    @include('pages.admin.partials.internal-dashboard-shell', ['dashboard' => $dashboard])
@endsection
