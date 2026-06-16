// Theme + command palette + real-time
import './echo';
import './waveform-player';
import { initJalaliDateInputs } from './jalali-date-input';
import { initReportCharts } from './reports-charts';

document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('open-command-palette'));
    }
});

function navigateTo(href) {
    if (! href) {
        return;
    }

    if (window.Livewire?.navigate) {
        window.Livewire.navigate(href);
    } else {
        window.location.assign(href);
    }
}

function initClickableTableRows() {
    const isInteractiveTarget = (target) => target.closest('button, a, input, select, textarea, label, [data-row-ignore]');

    document.addEventListener('click', (event) => {
        const row = event.target.closest('[data-row-href]');

        if (! row || isInteractiveTarget(event.target)) {
            return;
        }

        event.preventDefault();
        navigateTo(row.dataset.rowHref);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const row = event.target.closest('[data-row-href]');

        if (! row || isInteractiveTarget(event.target)) {
            return;
        }

        event.preventDefault();
        navigateTo(row.dataset.rowHref);
    });
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function initToastStack() {
    const container = document.getElementById('toast-stack');

    if (!container) {
        return;
    }

    const toasts = [];

    const render = () => {
        container.innerHTML = toasts.map((toast) => {
            const colorClass = toast.type === 'success'
                ? 'bg-emerald-600'
                : toast.type === 'error'
                    ? 'bg-red-600'
                    : 'bg-zinc-800';

            return `
                <div
                    class="pointer-events-auto cursor-pointer rounded-md px-4 py-3 text-sm font-medium text-white shadow-lg ${colorClass}"
                    data-toast-id="${toast.id}"
                    data-url="${escapeHtml(toast.url || '')}"
                >${escapeHtml(toast.message || '')}</div>
            `;
        }).join('');
    };

    const removeToast = (id) => {
        const index = toasts.findIndex((toast) => toast.id === id);

        if (index === -1) {
            return;
        }

        toasts.splice(index, 1);
        render();
    };

    const addToast = (detail = {}) => {
        const id = Date.now() + Math.random();
        toasts.push({
            id,
            type: detail.type || 'info',
            message: detail.message || '',
            url: detail.url || null,
        });

        render();
        window.setTimeout(() => removeToast(id), 8000);
    };

    container.addEventListener('click', (event) => {
        const toast = event.target.closest('[data-toast-id]');
        const url = toast?.dataset.url;

        if (url) {
            window.location.href = url;
        }
    });

    window.addEventListener('show-toast', (event) => {
        addToast(event.detail || {});
    });

    document.addEventListener('livewire:init', () => {
        window.Livewire.on('show-toast', (detail) => {
            if (detail && typeof detail === 'object' && !Array.isArray(detail)) {
                window.dispatchEvent(new CustomEvent('show-toast', { detail }));
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initToastStack);
} else {
    initToastStack();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initJalaliDateInputs);
} else {
    initJalaliDateInputs();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initClickableTableRows);
} else {
    initClickableTableRows();
}
