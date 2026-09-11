{{--
    One public after-hours entry.

    Deliberately narrow: title, topic, time, place and who is leading it. Never
    the attendee list, the attendance count or the QR token — the repository does
    not even load the memberships, so a mistake here cannot leak them.

    Expects: $session
--}}
<a href="{{ route('portal.sessions.show', ['session' => $session->id]) }}"
   class="landing-session">
    <div class="landing-session-time">
        <span class="landing-session-hour">{{ $session->starts_at->format('H:i') }}</span>
        <span class="landing-session-end">s/d {{ $session->ends_at->format('H:i') }}</span>
    </div>

    <div class="flex-grow-1 min-w-0">
        <div class="landing-session-title">{{ $session->topic }}</div>

        {{-- The second line is the description, not the topic again: the topic
             is already the heading directly above it. --}}
        @if ($session->description)
            <p class="landing-session-topic mb-0">
                {{ \Illuminate\Support\Str::limit($session->description, 100) }}
            </p>
        @endif

        <div class="landing-session-meta">
            @if ($session->mentor)
                <span>
                    <i class="bi bi-person-badge me-1"></i>
                    {{ $session->mentor->name }}
                </span>
            @endif

            @if ($session->location)
                <span>
                    <i class="bi bi-geo-alt me-1"></i>
                    {{ $session->location }}
                </span>
            @endif
        </div>
    </div>

    <i class="bi bi-chevron-right landing-chevron align-self-center"></i>
</a>
