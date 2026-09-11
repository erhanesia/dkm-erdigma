@props([
    'href',
    'icon' => 'circle',
    'active' => false,
    'badge' => null,
])

<a href="{{ $href }}"
   wire:navigate
   @class(['nav-link', 'active' => $active])
   @if ($active) aria-current="page" @endif>
    <i class="bi bi-{{ $icon }}"></i>
    <span>{{ $slot }}</span>

    @if ($badge)
        <span class="nav-badge">{{ $badge }}</span>
    @endif
</a>
