/**
 * The cursor spotlight on the front page.
 *
 * Two layers are stacked: a dark one, and a lit one directly on top. The lit
 * layer is masked to nothing but a soft circle under the cursor, so moving the
 * mouse feels like carrying a lantern across the page.
 *
 * The mask is drawn on a canvas and handed to CSS as a data URL. That is the
 * technique the reference design uses, and it is the reason the edge can be a
 * genuine multi-stop falloff rather than the single hard circle a plain CSS
 * gradient mask would give.
 */

/** Radius of the lit circle, in pixels. */
const RADIUS = 260;

/**
 * How large the mask canvas is relative to the hero it covers.
 *
 * The mask is stretched back to full size by `mask-size: 100% 100%`, and what
 * it carries is one soft radial gradient — there is no detail in it to lose at
 * half resolution. Halving each axis quarters the pixels `toDataURL()` has to
 * encode, and it runs on every frame.
 */
const SCALE = 0.5;

/**
 * How much of the gap to the real cursor is closed each frame.
 *
 * Low enough that the light trails behind the pointer instead of snapping to
 * it — the lag is what makes it read as a physical thing being carried.
 */
const EASING = 0.1;

/**
 * Everything this module has bound to `window`.
 *
 * They are collected rather than torn down individually because the listeners
 * live on `window`, which survives the body swap `wire:navigate` performs —
 * leaving one behind means a scroll handler pointing at a detached section on
 * every page after this one.
 */
let disposers = [];

export function stopImmersive() {
    disposers.forEach((dispose) => dispose());
    disposers = [];
}

export function initImmersive() {
    stopImmersive();

    const section = document.querySelector('[data-immersive]');

    if (!section) {
        return;
    }

    disposers.push(bindNav(section));

    const reveal = section.querySelector('[data-immersive-reveal]');
    const canvas = section.querySelector('[data-immersive-canvas]');

    // No pointer to follow, or the user asked for less motion: the dark layer
    // alone is a complete design, so there is nothing to fall back to.
    const still = window.matchMedia('(hover: none)').matches
        || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!reveal || !canvas || still) {
        return;
    }

    const context = canvas.getContext('2d');

    // Start off-screen so nothing is lit until the pointer actually arrives.
    const mouse = { x: -9999, y: -9999 };
    const smooth = { x: -9999, y: -9999 };

    let frame = null;

    /*
     * Sized to the hero, not to the viewport.
     *
     * The front page's hero happens to be one screen tall, but every other page
     * opens with a short one — a viewport-sized mask stretched over a 240px
     * band would squash the circle into a flat ellipse.
     */
    const resize = () => {
        canvas.width = Math.max(1, Math.round(section.clientWidth * SCALE));
        canvas.height = Math.max(1, Math.round(section.clientHeight * SCALE));
    };

    const onMove = (event) => {
        mouse.x = event.clientX;
        mouse.y = event.clientY;
    };

    // Leaving the window takes the light with the pointer rather than freezing
    // it mid-page, which would look like a stuck highlight.
    const onLeave = () => {
        mouse.x = -9999;
        mouse.y = -9999;
    };

    const paint = () => {
        smooth.x += (mouse.x - smooth.x) * EASING;
        smooth.y += (mouse.y - smooth.y) * EASING;

        const bounds = section.getBoundingClientRect();

        // Scrolled past. Nothing to light, and the encode is the expensive part.
        if (bounds.bottom < 0 || bounds.top > window.innerHeight) {
            frame = window.requestAnimationFrame(paint);

            return;
        }

        // Viewport coordinates, expressed in the canvas's own space.
        const x = (smooth.x - bounds.left) * SCALE;
        const y = (smooth.y - bounds.top) * SCALE;
        const radius = RADIUS * SCALE;

        context.clearRect(0, 0, canvas.width, canvas.height);

        const glow = context.createRadialGradient(x, y, 0, x, y, radius);

        // Six stops rather than two: a linear falloff reads as a flashlight,
        // and the long soft tail is what makes it read as a glow instead.
        glow.addColorStop(0, 'rgba(255,255,255,1)');
        glow.addColorStop(0.4, 'rgba(255,255,255,1)');
        glow.addColorStop(0.6, 'rgba(255,255,255,0.75)');
        glow.addColorStop(0.75, 'rgba(255,255,255,0.4)');
        glow.addColorStop(0.88, 'rgba(255,255,255,0.12)');
        glow.addColorStop(1, 'rgba(255,255,255,0)');

        context.fillStyle = glow;
        context.beginPath();
        context.arc(x, y, radius, 0, Math.PI * 2);
        context.fill();

        const mask = `url(${canvas.toDataURL()})`;

        reveal.style.webkitMaskImage = mask;
        reveal.style.maskImage = mask;
        reveal.style.webkitMaskSize = '100% 100%';
        reveal.style.maskSize = '100% 100%';

        frame = window.requestAnimationFrame(paint);
    };

    resize();
    frame = window.requestAnimationFrame(paint);

    /*
     * A short hero's height follows its own contents — a surah title wrapping
     * onto a second line changes it without the window ever being resized.
     */
    const observer = new ResizeObserver(resize);

    observer.observe(section);

    window.addEventListener('mousemove', onMove);
    document.addEventListener('mouseleave', onLeave);

    disposers.push(() => {
        window.cancelAnimationFrame(frame);
        observer.disconnect();
        window.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseleave', onLeave);
    });
}

/**
 * The navigation floats transparently over the hero and takes on a background
 * once the hero has scrolled past — otherwise the links would sit on whatever
 * happened to be underneath them.
 *
 * @returns {() => void} Removes the listener again.
 */
function bindNav(section) {
    const nav = document.querySelector('[data-landing-nav]');

    if (!nav) {
        return () => {};
    }

    /*
     * The bar changes exactly when the hero's bottom edge passes under it, not
     * at some fraction of the way down.
     *
     * A fraction was close enough on the front page, whose hero is a whole
     * screen tall. On the short heroes of the inner pages 60% lands while the
     * dark ground is still behind the links, so the pills darkened and the
     * scrim washed white over a green hero for the next hundred pixels.
     */
    const onScroll = () => {
        nav.classList.toggle(
            'is-scrolled',
            section.getBoundingClientRect().bottom <= nav.offsetHeight,
        );
    };

    /*
     * Publish the bar's real height as `--landing-nav-h`.
     *
     * Four things are positioned against that number — the padding that clears
     * the hero past the bar, the sticky Al-Ma'tsurat toolbar, the sticky header
     * on the monthly schedule, and `scroll-margin-top` on every anchor. The
     * stylesheet carries a guess for each breakpoint, which was right until the
     * second row of links stopped being a fixed height, and a guess that is
     * 8px short puts a sticky header 8px under a floating pill on exactly the
     * screens nobody tests on.
     *
     * The CSS values stay as the value for the first paint; this corrects them
     * once there is a laid-out bar to measure.
     */
    const publishHeight = () => {
        document.documentElement.style.setProperty(
            '--landing-nav-h',
            `${Math.round(nav.getBoundingClientRect().height)}px`,
        );
    };

    const observer = new ResizeObserver(() => {
        publishHeight();
        onScroll();
    });

    observer.observe(nav);

    publishHeight();
    onScroll();

    window.addEventListener('scroll', onScroll, { passive: true });

    return () => {
        observer.disconnect();
        window.removeEventListener('scroll', onScroll);
    };
}
