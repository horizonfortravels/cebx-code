{{-- resources/views/components/timeline.blade.php --}}
@props(['items' => [], 'teal' => false])
<div class="timeline" style="position:relative;padding-right:24px">
    <div style="position:absolute;right:7px;top:0;bottom:0;width:2px;background:var(--bd)"></div>
    @foreach($items as $i => $item)
        <div style="display:flex;gap:16px;margin-bottom:{{ $loop->last ? '0' : '24px' }};position:relative">
            <div style="width:16px;height:16px;border-radius:50%;flex-shrink:0;margin-top:2px;
                background:{{ $loop->first ? ($teal ? '#0D9488' : 'var(--pr)') : 'var(--bd)' }};
                {{ $loop->first ? 'border:3px solid ' . ($teal ? 'rgba(13,148,136,0.2)' : 'rgba(59,130,246,0.2)') : '' }}">
            </div>
            <div style="flex:1">
                <div style="font-size:13px;font-weight:600;color:{{ $loop->first ? 'var(--tx)' : 'var(--tm)' }}">
                    {{ $item['title'] }}
                </div>
                <div style="font-size:12px;color:var(--td);margin-top:2px">{{ $item['date'] }}</div>
                @if(isset($item['location']))
                    <div style="font-size:12px;color:var(--td);margin-top:2px">📍 {{ $item['location'] }}</div>
                @endif
                @if(isset($item['desc']))
                    <div style="font-size:12px;color:var(--td);margin-top:2px">{{ $item['desc'] }}</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
