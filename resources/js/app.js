import './bootstrap';
import './api';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// Register Alpine extensions before Livewire boots Alpine.
// Do not import Alpine here, Livewire ships and starts its own.
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    if (!Alpine) return;

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

        const timer = setTimeout(() => {
            const nodes = focusables();
            (nodes.length > 0 ? nodes[0] : el).focus();
        }, 50);

        el.addEventListener('keydown', onKeydown);

        return () => {
            clearTimeout(timer);
            el.removeEventListener('keydown', onKeydown);
            if (previousFocus && typeof previousFocus.focus === 'function') {
                try { previousFocus.focus(); } catch (e) { /* noop */ }
            }
        };
    });
});

// Shell state used by the sidebar and topbar via x-data="nksShell()"
window.nksShell = function () {
    return {
        mobileOpen: false,
        collapsed: false,

        init() {
            const stored = localStorage.getItem('nks-sidebar-collapsed');
            this.collapsed = stored === 'true';

            this.$watch('collapsed', (value) => {
                localStorage.setItem('nks-sidebar-collapsed', value ? 'true' : 'false');
            });
        },

        toggleCollapsed() {
            this.collapsed = !this.collapsed;
        },
    };
};

// Theme toggle triggered from the topbar button
window.nksToggleTheme = function () {
    const isDark = document.documentElement.classList.contains('dark');
    const next = isDark ? 'light' : 'dark';

    document.documentElement.classList.toggle('dark', next === 'dark');
    localStorage.setItem('nks-theme', next);

    const icon = document.getElementById('nks-theme-icon');
    if (icon) {
        icon.classList.toggle('fa-moon', next !== 'dark');
        icon.classList.toggle('fa-sun', next === 'dark');
    }
};

// Sync the theme icon on first paint if the user already selected dark
document.addEventListener('DOMContentLoaded', () => {
    const icon = document.getElementById('nks-theme-icon');
    if (icon && document.documentElement.classList.contains('dark')) {
        icon.classList.remove('fa-moon');
        icon.classList.add('fa-sun');
    }
});

// Toast stack
const ICONS = {
    success: 'fa-solid fa-check',
    error: 'fa-solid fa-xmark',
    warning: 'fa-solid fa-triangle-exclamation',
    info: 'fa-solid fa-circle-info',
};

function ensureToastStack() {
    let stack = document.getElementById('nks-toast-stack');
    if (!stack) {
        stack = document.createElement('div');
        stack.id = 'nks-toast-stack';
        stack.className = 'nks-toast-stack';
        stack.setAttribute('role', 'status');
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
    }
    return stack;
}

window.nksToast = function (type, title, message = '', duration = 5200) {
    const stack = ensureToastStack();
    const toast = document.createElement('div');
    toast.className = `nks-toast nks-toast--${type}`;

    const icon = ICONS[type] || ICONS.info;

    toast.innerHTML = `
        <div class="nks-toast-icon"><i class="${icon}" aria-hidden="true"></i></div>
        <div class="nks-toast-body">
            <p class="nks-toast-title"></p>
            ${message ? '<p class="nks-toast-message"></p>' : ''}
        </div>
        <button type="button" class="nks-toast-close" aria-label="Dismiss">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    `;

    toast.querySelector('.nks-toast-title').textContent = title;
    if (message) toast.querySelector('.nks-toast-message').textContent = message;

    const dismiss = () => {
        toast.classList.add('is-leaving');
        setTimeout(() => toast.remove(), 200);
    };

    toast.querySelector('.nks-toast-close').addEventListener('click', dismiss);
    stack.appendChild(toast);

    if (duration > 0) setTimeout(dismiss, duration);

    return toast;
};

// Accept `window.dispatchEvent(new CustomEvent('toast', { detail: {...} }))`
window.addEventListener('toast', (e) => {
    const { type = 'info', title = '', message = '' } = e.detail || {};
    window.nksToast(type, title, message);
});

// Accept `$this->dispatch('toast', type: ..., title: ..., message: ...)` from Livewire
document.addEventListener('livewire:init', () => {
    Livewire.on('toast', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        window.nksToast(data.type || 'info', data.title || '', data.message || '');
    });
});

// Page transition loader
document.addEventListener('livewire:navigating', () => {
    document.getElementById('nks-page-loader')?.classList.add('is-active');
});

document.addEventListener('livewire:navigated', () => {
    document.getElementById('nks-page-loader')?.classList.remove('is-active');
});