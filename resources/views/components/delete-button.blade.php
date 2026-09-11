@props([
    'action',
    'label' => 'Hapus',
    'confirm' => 'Data yang dihapus tidak bisa dikembalikan.',
    'title' => 'Hapus data ini?',
    'icon' => 'trash',
    'iconOnly' => false,
])

{{-- The dialog is rendered by initConfirmations() in app.js, which reads these
     data attributes; nothing here submits without a confirmation. --}}
<form method="POST"
      action="{{ $action }}"
      class="d-inline"
      data-confirm="{{ $confirm }}"
      data-confirm-title="{{ $title }}"
      data-confirm-button="Ya, hapus">
    @csrf
    @method('DELETE')

    <button type="submit"
            {{ $attributes->merge(['class' => 'btn btn-sm btn-light text-danger']) }}
            @if ($iconOnly) title="{{ $label }}" aria-label="{{ $label }}" @endif>
        <i class="bi bi-{{ $icon }}"></i>
        @unless ($iconOnly)
            <span class="ms-1">{{ $label }}</span>
        @endunless
    </button>
</form>
