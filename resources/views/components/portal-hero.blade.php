{{--
    The hero shell every public page opens with.

    Holds the two stacked layers the cursor spotlight works on: a dark ground,
    and the same ground lit, masked to a soft circle by
    resources/js/modules/immersive.js. Both are built in CSS — see
    `_immersive.scss` for why there is no photograph here.

    The shell is a component rather than eight copies of the same four divs,
    because four divs repeated eight times is four divs that will drift apart.

    @param bool $full  Full viewport height. The front page only; every other
                       page opens with content, not with a pitch.
--}}
@props(['full' => false])

<header {{ $attributes->class(['immersive', 'immersive--compact' => ! $full]) }} data-immersive>
    <div class="immersive-base hero-zoom" aria-hidden="true"></div>
    <div class="immersive-reveal" data-immersive-reveal aria-hidden="true"></div>
    <canvas class="immersive-canvas" data-immersive-canvas aria-hidden="true"></canvas>
    <div class="immersive-veil" aria-hidden="true"></div>

    <div class="immersive-body">
        {{ $slot }}
    </div>
</header>
