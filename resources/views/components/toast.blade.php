@props(['type' => 'success', 'message'])
<div class="toast-container">
    <div class="toast toast-{{ $type }}">
        {{ $message }}
        <button style="background:none;border:none;color:#fff;cursor:pointer" onclick="this.parentElement.remove()">✕</button>
    </div>
</div>
