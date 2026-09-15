@props(['session'])

{{--
    "Dijadwal ulang" on any session moved from the time it was first set for.

    The original time sits in the tooltip, not the badge: the badge is for
    noticing, the tooltip for checking. The reason is left out on purpose —
    this component also appears on the public page, and why a meeting moved is
    the board's business.
--}}
@if ($session->isRescheduled())
    <span {{ $attributes->merge(['class' => 'badge text-bg-warning']) }}
          title="Semula {{ \App\Support\Helpers\DateHelper::formatDateTime($session->rescheduled_from) }}">
        <i class="bi bi-arrow-repeat me-1"></i>Dijadwal ulang
    </span>
@endif
