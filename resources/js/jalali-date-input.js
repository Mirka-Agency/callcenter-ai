import dayjs from 'dayjs/esm';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import calendarSystems from '@calidy/dayjs-calendarsystems';
import PersianCalendarSystem from '@calidy/dayjs-calendarsystems/calendarSystems/PersianCalendarSystem';
import fa from 'dayjs/locale/fa';

dayjs.extend(customParseFormat);
dayjs.extend(calendarSystems);
dayjs.registerCalendarSystem('persian', new PersianCalendarSystem());
dayjs.locale(fa);

let dismissListenerRegistered = false;

function parseGregorian(value) {
    if (! value) {
        return null;
    }

    const parsed = dayjs(value, ['YYYY-MM-DD', 'YYYY-MM-DD HH:mm:ss'], true);

    return parsed.isValid() ? parsed.toCalendarSystem('persian') : null;
}

function formatGregorian(persianDate) {
    return persianDate.toCalendarSystem('gregory').format('YYYY-MM-DD');
}

function resolveLivewireComponent(root) {
    const livewireComponent = root.closest('[wire\\:id]');

    if (! livewireComponent || ! window.Livewire) {
        return null;
    }

    const componentId = livewireComponent.getAttribute('wire:id');

    return componentId ? window.Livewire.find(componentId) : null;
}

function registerDismissListener() {
    if (dismissListenerRegistered) {
        return;
    }

    dismissListenerRegistered = true;

    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-jalali-date-input]').forEach((root) => {
            const panel = root.querySelector('[data-jalali-panel]');

            if (panel && ! panel.hidden && ! root.contains(event.target)) {
                panel.hidden = true;
            }
        });
    });
}

function teardownJalaliDateInput(root) {
    root._jalaliAbortController?.abort();
    delete root._jalaliAbortController;
    delete root._jalaliSync;
    delete root._jalaliGetValue;
    delete root._jalaliLocalValue;
    delete root.dataset.jalaliInitialized;
}

function isJalaliDateInputHealthy(root) {
    return root.isConnected
        && root.dataset.jalaliInitialized === '1'
        && typeof root._jalaliSync === 'function'
        && root.querySelector('[data-jalali-trigger]');
}

function initJalaliDateInput(root) {
    if (isJalaliDateInputHealthy(root)) {
        return;
    }

    teardownJalaliDateInput(root);

    const wireModel = root.dataset.wireModel;

    if (! wireModel) {
        return;
    }

    const deferSync = root.dataset.wireDefer === '1';
    const livewire = resolveLivewireComponent(root);

    if (! livewire) {
        return;
    }

    const display = root.querySelector('[data-jalali-display]');
    const panel = root.querySelector('[data-jalali-panel]');
    const monthSelect = root.querySelector('[data-jalali-month]');
    const yearInput = root.querySelector('[data-jalali-year]');
    const daysGrid = root.querySelector('[data-jalali-days]');
    const clearButton = root.querySelector('[data-jalali-clear]');
    const trigger = root.querySelector('[data-jalali-trigger]');

    if (! display || ! panel || ! monthSelect || ! yearInput || ! daysGrid || ! trigger) {
        return;
    }

    const abortController = new AbortController();
    const { signal } = abortController;

    root._jalaliAbortController = abortController;

    let focusedDate = dayjs().toCalendarSystem('persian').hour(0).minute(0).second(0);
    let selectedDate = parseGregorian(livewire.get(wireModel));
    root._jalaliLocalValue = selectedDate ? formatGregorian(selectedDate) : null;

    root._jalaliGetValue = () => root._jalaliLocalValue ?? null;

    const commitValue = (gregorianValue) => {
        root._jalaliLocalValue = gregorianValue;

        if (! deferSync) {
            livewire.set(wireModel, gregorianValue);
        }
    };

    if (selectedDate) {
        focusedDate = selectedDate;
    }

    const renderCalendar = () => {
        monthSelect.value = String(focusedDate.month());
        yearInput.value = String(focusedDate.year());

        const emptyDays = focusedDate.startOf('month').day();
        const daysInMonth = focusedDate.daysInMonth();

        daysGrid.innerHTML = '';

        for (let i = 0; i < emptyDays; i += 1) {
            daysGrid.appendChild(document.createElement('div'));
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(day);
            button.className = 'jalali-day';

            const dayDate = focusedDate.date(day);
            const isToday = dayDate.isSame(dayjs().toCalendarSystem('persian'), 'day');
            const isSelected = selectedDate && dayDate.isSame(selectedDate, 'day');

            if (isToday) {
                button.classList.add('is-today');
            }

            if (isSelected) {
                button.classList.add('is-selected');
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                selectedDate = dayDate;
                commitValue(formatGregorian(selectedDate));
                display.value = selectedDate.format('YYYY/MM/DD');
                panel.hidden = true;
                renderCalendar();
            }, { signal });

            daysGrid.appendChild(button);
        }
    };

    const syncFromLivewire = () => {
        const serverValue = livewire.get(wireModel);
        root._jalaliLocalValue = serverValue;
        selectedDate = parseGregorian(serverValue);
        display.value = selectedDate ? selectedDate.format('YYYY/MM/DD') : '';

        if (selectedDate) {
            focusedDate = selectedDate;
        }

        renderCalendar();
    };

    root._jalaliSync = syncFromLivewire;

    syncFromLivewire();

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        panel.hidden = ! panel.hidden;

        if (! panel.hidden) {
            renderCalendar();
        }
    }, { signal });

    monthSelect.addEventListener('change', () => {
        focusedDate = focusedDate.month(Number(monthSelect.value));
        renderCalendar();
    }, { signal });

    yearInput.addEventListener('change', () => {
        const year = Number(yearInput.value);

        if (! Number.isNaN(year) && year >= 1300 && year <= 1500) {
            focusedDate = focusedDate.year(year);
            renderCalendar();
        }
    }, { signal });

    clearButton?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        selectedDate = null;
        commitValue(null);
        display.value = '';
        panel.hidden = true;
        renderCalendar();
    }, { signal });

    registerDismissListener();

    root.dataset.jalaliInitialized = '1';
}

function jalaliRootsIn(container = document) {
    if (container instanceof Element) {
        const roots = [...container.querySelectorAll('[data-jalali-date-input]')];

        if (container.matches('[data-jalali-date-input]')) {
            roots.push(container);
        }

        return roots;
    }

    return [...document.querySelectorAll('[data-jalali-date-input]')];
}

function initJalaliDateInputsIn(container = document) {
    jalaliRootsIn(container).forEach(initJalaliDateInput);
}

function syncJalaliDateInputsIn(container = document) {
    jalaliRootsIn(container).forEach((root) => {
        if (isJalaliDateInputHealthy(root)) {
            root._jalaliSync?.();
        }
    });
}

function refreshJalaliDateInputsIn(container = document) {
    jalaliRootsIn(container).forEach((root) => {
        if (! isJalaliDateInputHealthy(root)) {
            teardownJalaliDateInput(root);
        }
    });

    initJalaliDateInputsIn(container);
    syncJalaliDateInputsIn(container);
}

let deferredApplyListenerRegistered = false;

function registerDeferredApplyListener() {
    if (deferredApplyListenerRegistered) {
        return;
    }

    deferredApplyListenerRegistered = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-apply-deferred-date-range]');

        if (! button) {
            return;
        }

        event.preventDefault();

        const container = button.closest('[data-deferred-date-range]');

        if (! container) {
            return;
        }

        const livewire = resolveLivewireComponent(button);

        if (! livewire) {
            return;
        }

        const fromRoot = container.querySelector('[data-wire-model="draftCustomFrom"]');
        const toRoot = container.querySelector('[data-wire-model="draftCustomTo"]');
        const from = fromRoot?._jalaliGetValue?.() ?? null;
        const to = toRoot?._jalaliGetValue?.() ?? null;

        livewire.call('applyCustomDateRange', from, to);
    });
}

let livewireHooksRegistered = false;

function registerLivewireHooks() {
    if (livewireHooksRegistered || ! window.Livewire?.hook) {
        return;
    }

    livewireHooksRegistered = true;

    const refresh = (payload = {}) => {
        requestAnimationFrame(() => {
            refreshJalaliDateInputsIn(payload.el ?? document);
        });
    };

    window.Livewire.hook('morph.added', refresh);
    window.Livewire.hook('morph.updated', refresh);
    window.Livewire.hook('morph.removed', ({ el }) => {
        if (el instanceof Element) {
            jalaliRootsIn(el).forEach(teardownJalaliDateInput);

            if (el.matches('[data-jalali-date-input]')) {
                teardownJalaliDateInput(el);
            }
        }
    });
}

export function initJalaliDateInputs() {
    refreshJalaliDateInputsIn(document);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        registerDeferredApplyListener();
        initJalaliDateInputs();
    });
} else {
    registerDeferredApplyListener();
    initJalaliDateInputs();
}

document.addEventListener('livewire:init', () => {
    registerLivewireHooks();
    registerDeferredApplyListener();
    initJalaliDateInputs();
});

document.addEventListener('livewire:navigated', initJalaliDateInputs);
