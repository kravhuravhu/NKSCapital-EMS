import './bootstrap';
import './api';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import Alpine from 'alpinejs';

// F13.2 — focus trap for modals
// - moves focus to first focusable child on mount
// - cycles Tab and Shift+Tab inside the dialog
// - restores focus to the trigger element on unmount
// - dispatches 'modal-escape' when Escape is pressed so the host modal can wire close
Alpine.directive('trap', (el) => {
    const selector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    const focusables = () =>
        Array.from(el.querySelectorAll(selector))
            .filter((n) => n.offsetParent !== null && n.getAttribute('aria-hidden') !== 'true');

    const previousFocus = document.activeElement;

    const onKeydown = (e) => {
        if (e.key === 'Escape') {
            el.dispatchEvent(new CustomEvent('modal-escape', { bubbles: true }));
            return;
        }
        if (e.key !== 'Tab') return;

        const nodes = focusables();
        if (nodes.length === 0) {
            e.preventDefault();
            return;
        }

        const first = nodes[0];
        const last = nodes[nodes.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    };

    // focus the first focusable element in the dialog on mount
    const timer = setTimeout(() => {
        const nodes = focusables();
        if (nodes.length > 0) {
            nodes[0].focus();
        } else {
            el.focus();
        }
    }, 50);

    el.addEventListener('keydown', onKeydown);

    return () => {
        clearTimeout(timer);
        el.removeEventListener('keydown', onKeydown);
        if (previousFocus && typeof previousFocus.focus === 'function') {
            try { previousFocus.focus(); } catch (e) { /* ignore */ }
        }
    };
});

window.Alpine = Alpine;
Alpine.start();