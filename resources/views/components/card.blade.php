@props(['title' => null])
<div {{ $attributes->class('card') }}>
    @if($title)
        <div class="card-title">
            <span class="card-title-text">{{ $title }}</span>
            @if(isset($action))
                <span class="card-title-action">{{ $action }}</span>
            @endif
        </div>
    @endif
    <div class="card-body">{{ $slot }}</div>
</div>
