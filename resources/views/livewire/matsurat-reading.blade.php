{{--
    Everything below the hero: the toolbar and the reading itself.

    The toolbar sits inside the component rather than above it because it
    carries state too — which reading is active, and the running total. Leaving
    it outside would mean updating it separately and eventually getting the two
    out of step.
--}}
<div>
<div class="matsurat-bar"
     data-matsurat
     data-key="{{ $currentVariant->value }}-{{ $currentTime->value }}"
     data-total="{{ $reading['total_readings'] }}">

    <div class="container">
        <div class="matsurat-bar-row">
            {{-- Sughra / Kubra --}}
            <div class="matsurat-switch">
                @foreach ($variants as $option)
                    <button type="button"
                            wire:click="switchVariant('{{ $option->value }}')"
                            class="matsurat-switch-item {{ $option === $currentVariant ? 'is-on' : '' }}"
                            title="{{ $option->description() }}">
                        {{ $option->label() }}
                    </button>
                @endforeach
            </div>

            {{-- Pagi / Petang --}}
            <div class="matsurat-switch">
                @foreach ($times as $option)
                    <button type="button"
                            wire:click="switchTime('{{ $option->value }}')"
                            class="matsurat-switch-item {{ $option === $currentTime ? 'is-on' : '' }}">
                        <i class="bi bi-{{ $option->icon() }}"></i>
                        <span class="d-none d-sm-inline">{{ $option->label() }}</span>
                    </button>
                @endforeach
            </div>

            <span class="spinner-border spinner-border-sm text-secondary"
                  wire:loading
                  wire:target="switchVariant, switchTime"
                  aria-hidden="true"></span>

            <div class="matsurat-progress-wrap">
                <div class="matsurat-progress" role="progressbar"
                     aria-label="Kemajuan bacaan"
                     aria-valuemin="0" aria-valuemax="{{ $reading['total_readings'] }}"
                     aria-valuenow="0">
                    <span data-matsurat-fill></span>
                </div>
                <span class="matsurat-count">
                    <strong data-matsurat-done>0</strong>/{{ $reading['total_readings'] }}
                </span>

                {{--
                    Inside the progress group, not beside it.

                    As a sibling it wrapped on its own: at some widths the row
                    broke after the counter and left a lone reset icon on a line
                    by itself. It resets the count, so it belongs with the count.
                --}}
                <button type="button" class="matsurat-reset" data-matsurat-reset
                        title="Mulai ulang hitungan">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<section class="landing-section"
         wire:loading.class="is-switching"
         wire:target="switchVariant, switchTime">
    <div class="container">

        @if (empty($reading['items']))
            <div class="landing-empty" data-aos>
                <i class="bi bi-file-earmark-x"></i>
                <p class="mb-0">Teks belum tersedia.</p>
            </div>
        @else
            {{--
                Named from the enums, not from the file.
                The four documents spell their own titles differently
                ("Al-Matsurat Kubro Pagi" beside "Al - Matsurat Sugro Petang"),
                and the switch above already labels them consistently.
            --}}
            <p class="matsurat-heading" data-aos>
                Al-Ma'tsurat {{ $currentVariant->label() }} {{ $currentTime->label() }}
                <span>{{ $reading['total_readings'] }} bacaan</span>
            </p>

            <div class="matsurat-list" wire:key="list-{{ $currentVariant->value }}-{{ $currentTime->value }}">
                @foreach ($reading['items'] as $item)
                    <article class="matsurat-item"
                             id="dzikir-{{ $item['order'] }}"
                             data-matsurat-item="{{ $item['order'] }}"
                             data-repeat="{{ $item['repeat'] }}"
                             data-aos
                             data-aos-delay="{{ min(($loop->index % 6 + 1) * 40, 240) }}">

                        <header class="matsurat-item-head">
                            <span class="matsurat-number">{{ $item['order'] }}</span>

                            <div class="min-w-0">
                                <h2 class="matsurat-title">{{ $item['title'] }}</h2>

                                @if ($item['source'])
                                    <p class="matsurat-source mb-0">{{ $item['source'] }}</p>
                                @endif
                            </div>

                            @if ($item['repeat'] > 1)
                                <span class="matsurat-repeat">{{ $item['repeat'] }}×</span>
                            @endif
                        </header>

                        @if ($item['verses'])
                            {{--
                                A surah, verse by verse. Reading it as one block
                                of Arabic is how a printed copy has to set it;
                                a screen can keep each ayah beside its own
                                translation, which is the whole reason to have
                                the newer text.
                            --}}
                            @foreach ($item['verses'] as $verse)
                                <div class="matsurat-verse">
                                    @if ($verse['title'])
                                        <span class="matsurat-verse-label">{{ $verse['title'] }}</span>
                                    @endif

                                    <p class="quran-arabic" dir="rtl" lang="ar">{{ $verse['arabic'] }}</p>

                                    @if ($verse['latin'])
                                        <p class="quran-latin">{{ $verse['latin'] }}</p>
                                    @endif

                                    @if ($verse['translation'])
                                        <p class="quran-translation">{{ $verse['translation'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <p class="quran-arabic" dir="rtl" lang="ar">{{ $item['arabic'] }}</p>

                            @if ($item['latin'])
                                <p class="quran-latin">{{ $item['latin'] }}</p>
                            @endif

                            @if ($item['translation'])
                                <p class="quran-translation">{{ $item['translation'] }}</p>
                            @endif
                        @endif

                        {{-- The counter, only where it earns its place: an item
                             read once needs a tick, not a tally. --}}
                        <footer class="matsurat-count-row">
                            @if ($item['repeat'] > 1)
                                <button type="button" class="matsurat-tap" data-matsurat-tap>
                                    <i class="bi bi-hand-index-thumb"></i>
                                    <span data-matsurat-tap-label>Ketuk untuk menghitung</span>
                                </button>

                                <span class="matsurat-dots" data-matsurat-dots aria-hidden="true">
                                    @for ($i = 0; $i < min($item['repeat'], 10); $i++)
                                        <span></span>
                                    @endfor
                                </span>

                                <span class="matsurat-tally">
                                    <strong data-matsurat-item-done>0</strong>/{{ $item['repeat'] }}
                                </span>
                            @else
                                <button type="button" class="matsurat-tap is-single" data-matsurat-tap>
                                    <i class="bi bi-check2"></i>
                                    <span data-matsurat-tap-label>Tandai sudah dibaca</span>
                                </button>
                            @endif
                        </footer>
                    </article>
                @endforeach
            </div>

            <div class="matsurat-done" data-matsurat-done-card hidden>
                <i class="bi bi-check-circle-fill"></i>
                <p class="mb-0">
                    <strong>Alhamdulillah, selesai.</strong><br>
                    Al-Ma'tsurat {{ $currentVariant->label() }} {{ $currentTime->label() }} sudah dibaca lengkap.
                </p>
            </div>

            <p class="text-center text-body-tertiary small mt-4 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Hitungan tersimpan di perangkat ini saja dan mulai ulang setiap hari.
            </p>
        @endif
    </div>
</section>
</div>
