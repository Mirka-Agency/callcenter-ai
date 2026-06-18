// Theme + command palette + real-time
import './echo';
import './waveform-player';
import { initJalaliDateInputs } from './jalali-date-input';
import { initReportCharts } from './reports-charts';
import { initEmployerOnboarding } from './employer-onboarding';
import { initEmployeeOnboarding } from './employee-onboarding';

document.addEventListener('alpine:init', () => {
    Alpine.store('layout', {
        sidebarOpen: false,
        toggleSidebar() {
            this.sidebarOpen = ! this.sidebarOpen;
            this.syncBodyScroll();
        },
        openSidebar() {
            this.sidebarOpen = true;
            this.syncBodyScroll();
        },
        closeSidebar() {
            this.sidebarOpen = false;
            this.syncBodyScroll();
        },
        syncBodyScroll() {
            const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
            const shouldLock = this.sidebarOpen && ! isDesktop;

            document.body.classList.toggle('overflow-hidden', shouldLock);
            document.documentElement.classList.toggle('overflow-hidden', shouldLock);
        },
    });
});

let mobileSidebarInitialized = false;

export function resetMobileShell() {
    try {
        const layout = Alpine.store('layout');

        if (layout) {
            layout.sidebarOpen = false;
            layout.syncBodyScroll();
        }
    } catch {
        // Alpine may not be ready yet.
    }

    document.body.classList.remove(
        'overflow-hidden',
        'employer-onboarding-open',
        'employee-onboarding-open',
    );
    document.documentElement.classList.remove('overflow-hidden');
}

function initMobileSidebar() {
    if (mobileSidebarInitialized) {
        return;
    }

    mobileSidebarInitialized = true;

    const closeOnDesktop = () => {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            resetMobileShell();
        }
    };

    const closeOnMobileNav = () => {
        if (! window.matchMedia('(min-width: 1024px)').matches) {
            resetMobileShell();
        }
    };

    window.addEventListener('resize', closeOnDesktop, { passive: true });
    window.addEventListener('pageshow', resetMobileShell, { passive: true });

    document.addEventListener('livewire:navigate', closeOnMobileNav);
    document.addEventListener('livewire:navigated', closeOnMobileNav);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('.saas-sidebar a[href]');

        if (! link || window.matchMedia('(min-width: 1024px)').matches) {
            return;
        }

        resetMobileShell();
    }, true);
}

function bootMobileShell() {
    resetMobileShell();
    initMobileSidebar();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootMobileShell);
} else {
    bootMobileShell();
}

document.addEventListener('alpine:init', initMobileSidebar);
document.addEventListener('alpine:initialized', resetMobileShell);

document.addEventListener('livewire:navigated', () => {
    resetMobileShell();
    syncSidebarActiveNav();
});

function syncSidebarActiveNav() {
    const currentPath = window.location.pathname.replace(/\/$/, '') || '/';

    document.querySelectorAll('.saas-sidebar [data-tour-nav]').forEach((link) => {
        if (! (link instanceof HTMLAnchorElement)) {
            return;
        }

        let linkPath = '';

        try {
            linkPath = new URL(link.href).pathname.replace(/\/$/, '') || '/';
        } catch {
            return;
        }

        link.classList.toggle('saas-nav-item-active', linkPath === currentPath);
    });
}

function initRequestProgress() {
    const bar = document.getElementById('saas-request-progress');

    if (! bar) {
        return;
    }

    let pendingRequests = 0;

    const show = () => {
        pendingRequests++;
        bar.classList.add('is-active');
        bar.setAttribute('aria-hidden', 'false');
    };

    const hide = () => {
        pendingRequests = Math.max(0, pendingRequests - 1);

        if (pendingRequests === 0) {
            bar.classList.remove('is-active');
            bar.setAttribute('aria-hidden', 'true');
        }
    };

    document.addEventListener('livewire:navigate', show);
    document.addEventListener('livewire:navigated', hide);

    document.addEventListener('livewire:init', () => {
        Livewire.hook('commit', ({ respond, fail }) => {
            show();

            respond(() => {
                hide();
            });

            fail(() => {
                hide();
            });
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRequestProgress);
} else {
    initRequestProgress();
}

function initExportLinks() {
    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-export-link]');

        if (! link || link.dataset.exportBusy === '1') {
            return;
        }

        const label = link.textContent.trim();
        link.dataset.exportBusy = '1';
        link.setAttribute('aria-busy', 'true');
        link.classList.add('pointer-events-none', 'opacity-70');
        link.innerHTML = `
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-flex h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>
                ${label}
            </span>
        `;

        window.setTimeout(() => {
            link.textContent = label;
            link.classList.remove('pointer-events-none', 'opacity-70');
            link.removeAttribute('aria-busy');
            delete link.dataset.exportBusy;
        }, 5000);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExportLinks);
} else {
    initExportLinks();
}

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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEmployerOnboarding);
    document.addEventListener('DOMContentLoaded', initEmployeeOnboarding);
} else {
    initEmployerOnboarding();
    initEmployeeOnboarding();
}
