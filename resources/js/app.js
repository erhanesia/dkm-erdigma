/**
 * DKM Erdigma — application bundle.
 *
 * The app is server-rendered. Livewire's `wire:navigate` swaps the page body
 * instead of doing a full reload, so navigation feels instant while every page
 * is still plain Blade.
 *
 * That has one consequence worth stating plainly: `DOMContentLoaded` fires only
 * on the very first load. Anything that decorates the DOM has to run again after
 * each navigation, which is what `bootUi()` and the listeners at the bottom
 * handle.
 *
 * Alpine is NOT imported here — Livewire ships its own copy, and starting a
 * second instance breaks every `x-data` on the page.
 */

import { Offcanvas } from 'bootstrap';
import Swal from 'sweetalert2';
import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.css';

import { initCharts, destroyCharts } from './modules/charts';
import { initCountdown, stopCountdown } from './modules/countdown';
import { initSidebar } from './modules/sidebar';
import { bindMonthCarry, clearNavigating, markNavigating, syncActiveNavLink } from './modules/navigation';
import { initGeolocation } from './modules/geolocation';
import { initCalibration } from './modules/calibration';
import { bindSubmitFeedback, markFormSubmitting } from './modules/form-submit';
import { initSettingsPreview, stopSettingsPreview } from './modules/settings-preview';
import { initQuran, stopQuran } from './modules/quran';
import { initMatsurat, stopMatsurat } from './modules/matsurat';
import { initImmersive, stopImmersive } from './modules/immersive';

window.Swal = Swal;

/**
 * Date and time pickers, in Indonesian.
 *
 * `data-picker` chooses the flavour so Blade never has to repeat option objects.
 */
function initPickers() {
    flatpickr.localize(Indonesian);

    const presets = {
        date: { dateFormat: 'Y-m-d', altInput: true, altFormat: 'j F Y' },
        datetime: {
            enableTime: true,
            time_24hr: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'j F Y, H:i',
        },
        time: { enableTime: true, noCalendar: true, time_24hr: true, dateFormat: 'H:i' },
    };

    document.querySelectorAll('[data-picker]').forEach((element) => {
        if (element._flatpickr) {
            return;
        }

        flatpickr(element, { ...(presets[element.dataset.picker] ?? presets.date), allowInput: true });
    });
}

/**
 * Searchable selects — picking ten people out of a few hundred is unusable with
 * a plain `<select multiple>`.
 */
function initSelects() {
    document.querySelectorAll('[data-searchable]').forEach((element) => {
        if (element.tomselect) {
            return;
        }

        /*
         * `data-creatable` lets a value that is not in the list be typed in — a
         * new place, say. The typed text becomes the value sent, and the server
         * adds it to its list, so the next form offers it like any other.
         */
        const creatable = element.dataset.creatable !== undefined;

        new TomSelect(element, {
            plugins: element.multiple ? ['remove_button'] : [],
            maxOptions: null,
            placeholder: element.dataset.placeholder ?? 'Cari lalu pilih…',
            create: creatable,
            createOnBlur: creatable,

            /*
             * Render the dropdown on <body> rather than inside the field.
             *
             * Anything with `data-aos` animates opacity and transform, which
             * makes it a stacking context — and a child cannot escape one no
             * matter how high its z-index. Raising the number does nothing; the
             * panel has to leave the context altogether.
             */
            dropdownParent: 'body',

            render: {
                no_results: () => '<div class="no-results py-2 px-3 text-muted">Tidak ada hasil.</div>',
                option_create: (data, escape) => `<div class="create py-2 px-3">Tambahkan <strong>${escape(data.input)}</strong>…</div>`,
            },
        });
    });
}

/**
 * Toast for the flash message the server put in the session.
 */
function initFlash() {
    const holder = document.getElementById('flash-payload');

    if (!holder) {
        return;
    }

    const flash = JSON.parse(holder.textContent);
    const icons = { success: 'success', danger: 'error', warning: 'warning', info: 'info' };

    // Removed so a back-navigation to a cached page does not replay the toast.
    holder.remove();

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icons[flash.type] ?? 'info',
        title: flash.title,
        text: flash.message,
        showConfirmButton: false,
        timer: 4200,
        timerProgressBar: true,
        customClass: { popup: 'shadow-lg' },
    });
}

/**
 * Counts a statistic up from zero — draws the eye to the number that changed.
 */
function initCountUp() {
    const elements = document.querySelectorAll('[data-count-to]:not([data-counted])');

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        elements.forEach((el) => {
            el.textContent = el.dataset.countTo;
            el.dataset.counted = 'true';
        });

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            const element = entry.target;
            const target = Number(element.dataset.countTo);
            const decimals = Number(element.dataset.countDecimals ?? 0);
            const duration = 900;
            const start = performance.now();

            element.dataset.counted = 'true';

            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                // Ease-out cubic: fast at first, settling gently on the value.
                const eased = 1 - Math.pow(1 - progress, 3);

                element.textContent = (target * eased)
                    .toFixed(decimals)
                    .replace('.', ',')
                    .replace(/\B(?=(\d{3})+(?!\d))/g, '.');

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };

            requestAnimationFrame(step);
            observer.unobserve(element);
        });
    }, { threshold: 0.25 });

    elements.forEach((element) => observer.observe(element));
}

/**
 * Destructive actions ask first, in Indonesian, and say what will happen.
 *
 * Bound once to `document`, so it keeps working across navigations without
 * being re-registered.
 */
function bindConfirmations() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm]');

        if (!form || form.dataset.confirmed === 'true') {
            return;
        }

        event.preventDefault();

        Swal.fire({
            title: form.dataset.confirmTitle ?? 'Yakin ingin melanjutkan?',
            text: form.dataset.confirm,
            icon: form.dataset.confirmIcon ?? 'warning',
            showCancelButton: true,
            confirmButtonText: form.dataset.confirmButton ?? 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                confirmButton: 'btn btn-danger px-3',
                cancelButton: 'btn btn-light px-3 me-2',
            },
            buttonsStyling: false,
        }).then((result) => {
            if (result.isConfirmed) {
                form.dataset.confirmed = 'true';

                // `form.submit()` fires no submit event, so the busy state
                // has to be raised here rather than by the global listener.
                markFormSubmitting(form);
                form.submit();
            }
        });
    });
}

/**
 * Everything that has to run for each page, whether it arrived by full load or
 * by `wire:navigate`.
 */
/**
 * Checkboxes that reveal a block of fields.
 *
 * A `change` handler on one element; a whole component for this would be more
 * code than the behaviour.
 */
function initToggles() {
    document.querySelectorAll('[data-toggle-target]').forEach((input) => {
        const target = document.querySelector(input.dataset.toggleTarget);

        if (target) {
            input.addEventListener('change', () => {
                target.style.display = input.checked ? '' : 'none';
            });
        }
    });
}

function bootUi() {
    initPickers();
    initSelects();
    initFlash();
    initCountUp();
    initCountdown();
    initCharts();
    initGeolocation();
    initCalibration();
    initSettingsPreview();
    initQuran();
    initMatsurat();
    initImmersive();
    initToggles();
    syncActiveNavLink();
    clearNavigating();
}

/**
 * Released before the body is swapped. Charts hold a canvas reference and the
 * countdown holds an interval; leaving either behind would leak on every
 * navigation.
 */
function teardownUi() {
    /*
     * The mobile menu, if it is open.
     *
     * Its links navigate, and `wire:navigate` swaps the body underneath an open
     * offcanvas — leaving the backdrop and the scroll lock applied to a body
     * that no longer has a menu in it.
     */
    document.querySelectorAll('.offcanvas.show').forEach((panel) => {
        Offcanvas.getInstance(panel)?.hide();
    });

    destroyCharts();
    stopCountdown();
    stopSettingsPreview();
    stopQuran();
    stopMatsurat();
    stopImmersive();
}

/*
 * Bound once. The sidebar, the confirmation handler and the submit feedback all
 * survive navigation —
 * the sidebar because it is persisted, the handler because it listens on
 * `document` — so re-binding them per page would stack duplicate listeners and
 * fire each dialog twice.
 */
document.addEventListener('DOMContentLoaded', () => {
    bindConfirmations();
    bindSubmitFeedback();
    bindMonthCarry();
    initSidebar();
    bootUi();
});

document.addEventListener('livewire:navigated', bootUi);

/*
 * Livewire swapping part of a page is not a navigation, so `livewire:navigated`
 * never fires for it — but the swapped markup still needs its decoration back.
 *
 * `morph.updated` runs after each component re-render. The counter is the one
 * that matters here: its tally lives in localStorage, and a freshly rendered
 * list would otherwise show zeros over a reading already half done.
 */
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook('morph.updated', () => {
        initMatsurat();
        initSelects();
    });
});
document.addEventListener('livewire:navigating', () => {
    markNavigating();
    teardownUi();
});
