@props(['id', 'title' => '', 'wide' => false])
<div class="modal-backdrop" id="modal-{{ $id }}">
    <div class="modal {{ $wide ? 'wide' : '' }}">
        <div class="modal-header">
            <h3>{{ $title }}</h3>
            <button type="button" data-modal-close style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--td)">✕</button>
        </div>
        <div class="modal-body">{{ $slot }}</div>
    </div>
</div>
