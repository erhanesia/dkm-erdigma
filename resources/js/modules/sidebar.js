/**
 * Collapsible sidebar.
 *
 * On desktop it is always visible; below the lg breakpoint it slides in over a
 * backdrop. The open state is not persisted — a drawer that reopens itself on
 * every page is more annoying than helpful.
 */
export function initSidebar() {
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');

    if (!sidebar) {
        return;
    }

    const close = () => {
        sidebar.classList.remove('is-open');
        backdrop?.classList.remove('is-visible');
        document.body.style.removeProperty('overflow');
    };

    const open = () => {
        sidebar.classList.add('is-open');
        backdrop?.classList.add('is-visible');
        document.body.style.overflow = 'hidden';
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            sidebar.classList.contains('is-open') ? close() : open();
        });
    });

    backdrop?.addEventListener('click', close);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    // Returning to desktop width should clear the mobile overlay state.
    window.matchMedia('(min-width: 992px)').addEventListener('change', (event) => {
        if (event.matches) {
            close();
        }
    });
}
