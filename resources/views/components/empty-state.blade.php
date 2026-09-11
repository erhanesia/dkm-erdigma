@props([
    'icon' => 'inbox',
    'title' => 'Belum ada data',
    'text' => null,
])

<div class="empty-state">
    <div class="empty-state__icon">
        <i class="bi bi-{{ $icon }}"></i>
    </div>

    <div class="empty-state__title">{{ $title }}</div>

    @if ($text)
        <p class="empty-state__text">{{ $text }}</p>
    @endif

    @if (isset($action))
        {{ $action }}
    @endif
</div>
