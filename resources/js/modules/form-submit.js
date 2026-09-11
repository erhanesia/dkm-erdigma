/**
 * Turns a submit button into a busy button while its form is in flight.
 *
 * Saving goes to the server and back. Without a signal the button looks exactly
 * as it did before the click, so people click it again — and a second POST on a
 * create form is a duplicate record, not a no-op.
 *
 * Bound once on `document`, so every form in the app gets this without any of
 * them asking for it.
 */

/** Buttons currently swapped out, with what to put back. */
const busy = new WeakMap();

/**
 * Marks a button busy: the icon becomes a spinner, the label says so, and the
 * width is pinned so the row does not jump.
 */
function markBusy(button) {
    if (!button || busy.has(button)) {
        return;
    }

    busy.set(button, {
        html: button.innerHTML,
        width: button.style.width,
        disabled: button.disabled,
    });

    // Measured before the content changes, or the button snaps narrower the
    // moment the longer label is replaced.
    button.style.width = `${button.offsetWidth}px`;

    const label = button.dataset.submittingLabel ?? 'Menyimpan…';

    button.innerHTML =
        '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'
        + `<span>${label}</span>`;

    button.setAttribute('aria-busy', 'true');

    /*
     * Disabled on the next tick, not now.
     *
     * A disabled control is omitted from the submission, so disabling inside the
     * submit handler would drop this button's own name and value from the
     * payload — which matters for any form that tells the server which button
     * was pressed. By the time this timeout runs the browser has already
     * serialised the form.
     */
    window.setTimeout(() => {
        button.disabled = true;
    }, 0);
}

function restore(button) {
    const previous = busy.get(button);

    if (!previous) {
        return;
    }

    button.innerHTML = previous.html;
    button.style.width = previous.width;
    button.disabled = previous.disabled;
    button.removeAttribute('aria-busy');

    busy.delete(button);
}

/**
 * The button that submitted the form.
 *
 * `event.submitter` names it exactly, which matters on forms with more than one
 * submit button — only the pressed one should spin.
 */
function submitterOf(form, event) {
    return event?.submitter ?? form.querySelector('button[type="submit"], input[type="submit"]');
}

/**
 * Exported so the confirmation dialog can use it too: confirming calls
 * `form.submit()`, and that method deliberately does not fire a submit event, so
 * the listener below never sees it.
 */
export function markFormSubmitting(form, event = null) {
    const button = submitterOf(form, event);

    if (button && !button.hasAttribute('formnovalidate')) {
        markBusy(button);
    }
}

export function bindSubmitFeedback() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        /*
         * Another handler has already stopped this submission — the delete
         * confirmation, for one. Spinning now would leave the button stuck
         * behind a dialog the person may well cancel.
         */
        if (event.defaultPrevented || form.dataset.noSubmitFeedback !== undefined) {
            return;
        }

        markFormSubmitting(form, event);

        // A form aimed at another tab leaves this page sitting there, so the
        // button has to come back on its own.
        if (form.target && form.target !== '_self') {
            window.setTimeout(() => restore(submitterOf(form, event)), 1500);
        }
    });

    /*
     * Coming back with the browser's back button can restore the page from
     * cache exactly as it was left — including a button frozen mid-spin. This
     * puts every one of them back.
     */
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) {
            return;
        }

        document
            .querySelectorAll('[aria-busy="true"]')
            .forEach((button) => restore(button));
    });
}
