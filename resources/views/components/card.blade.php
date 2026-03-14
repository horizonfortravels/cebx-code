@props(['title' => null])
<div class="card">
    @if($title)
        <div class="card-title">
            <span>{{ $title }}</span>
            @if(isset($action))
                <span>{{ $action }}</span>
            @endif
        </div>
    @endif
    <div class="card-body">{{ $slot }}</div>
</div>
