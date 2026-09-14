/**
 * Navigation polish for `wire:navigate`.
 *
 * The sidebar is persisted across page swaps (see `@persist` in the layout), so
 * its markup — including which item carries `.active` — is whatever the server
 * rendered on the *first* page load. Without the sync below, the highlight would
 * stay stuck on that first menu item forever.
 *
 * Updating the class on the existing element rather than replacing the markup is
 * also what makes the highlight animate: CSS transitions need the same element
 * to change state, not a new element to appear.
 */

/**
 * Marks the menu item matching the current URL as active.
 *
 * Matching is by path prefix so a detail page (`/zona/ruang-ceo`) keeps its
 * parent menu item (`/zona`) highlighted. The longest matching path wins, so
 * `/jadwal-jumat` does not also light up `/jadwal-sholat`.
 */
export function syncActiveNavLink() {
    const links = document.querySelectorAll('.app-nav .nav-link');

    if (links.length === 0) {
        return;
    }

    const current = window.location.pathname.replace(/\/+$/, '') || '/';
    let best = null;
    let bestLength = -1;

    links.forEach((link) => {
        const path = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
        const matches = current === path || current.startsWith(`${path}/`);

        if (matches && path.length > bestLength) {
            best = link;
            bestLength = path.length;
        }
    });

    links.forEach((link) => {
        const isActive = link === best;

        link.classList.toggle('active', isActive);

        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });

    best?.scrollIntoView({ block: 'nearest' });
}

/**
 * Carries the month being read from one schedule page to the other.
 *
 * The public prayer and Friday pages both keep their month in `?bulan=`, and
 * their links are marked `data-carry-month`. `#[Url(keep: true)]` does not do
 * this on its own — it only keeps the parameter in the address bar of the page
 * it is on — and the server-rendered links cannot know the month either, because
 * it changes after the page has loaded, as the visitor browses. So the month is
 * copied onto the link from the current address as the link is about to be used.
 *
 * Hover, press and keyboard focus each come before `wire:navigate` reads the
 * `href` — it prefetches 60ms after the pointer enters, and navigates on click —
 * and they are listened for in the capture phase on `document`, ahead of the
 * link's own listeners. Bound once: `document` survives the body swap.
 */
export function bindMonthCarry() {
    const carry = (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[data-carry-month]') : null;

        if (!link) {
            return;
        }

        const month = new URLSearchParams(window.location.search).get('bulan');
        const destination = new URL(link.getAttribute('href'), window.location.origin);

        if (month) {
            destination.searchParams.set('bulan', month);
        } else {
            destination.searchParams.delete('bulan');
        }

        if (link.href !== destination.href) {
            link.setAttribute('href', destination.href);
        }
    };

    ['mouseover', 'pointerdown', 'focusin'].forEach((type) => {
        document.addEventListener(type, carry, { capture: true, passive: true });
    });
}

/**
 * Dims the outgoing page while the next one is being fetched, and closes the
 * mobile drawer so the new page is not hidden behind it.
 */
export function markNavigating() {
    document.body.classList.add('is-navigating');
    document.querySelector('.app-sidebar')?.classList.remove('is-open');
    document.querySelector('.sidebar-backdrop')?.classList.remove('is-visible');
    document.body.style.removeProperty('overflow');
}

export function clearNavigating() {
    document.body.classList.remove('is-navigating');
}
