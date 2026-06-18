import {
    FULL_TOUR_ID,
    buildFullEmployeeTour,
    employeePageTours,
    resolveEmployeeRoute,
    tourForRoute,
} from './employee-onboarding-tours';

const STORAGE_PREFIX = 'employee_onboarding_';
const RESUME_KEY = 'employee_onboarding_resume';
const FAB_SELECTOR = 'button.employee-onboarding-fab[data-onboarding-trigger]';

class EmployeeOnboardingTour {
    constructor() {
        this.active = false;
        this.tourId = null;
        this.steps = [];
        this.stepIndex = 0;
        this.ui = null;
        this.shades = {};
        this.ring = null;
        this.focusedTarget = null;
        this.initialized = false;
        this.onKeydown = this.onKeydown.bind(this);
        this.onResize = this.onResize.bind(this);
    }

    init() {
        if (! window.__employeeOnboarding) {
            return;
        }

        if (this.initialized) {
            window.__employeeOnboarding.currentRoute = resolveEmployeeRoute(window.location.pathname);

            return;
        }

        this.initialized = true;
        this.ensureUi();
        this.bindTriggers();
        this.tryResume();

        if (this.shouldAutoStartFullTour()) {
            window.setTimeout(() => this.startFullTour(), 600);
        }
    }

    ensureUi() {
        if (this.ui) {
            return;
        }

        const root = document.createElement('div');
        root.id = 'employee-onboarding-root';
        root.className = 'employee-onboarding';
        root.setAttribute('dir', 'ltr');
        root.hidden = true;
        root.innerHTML = `
            <div class="employee-onboarding__shade" data-shade="top"></div>
            <div class="employee-onboarding__shade" data-shade="left"></div>
            <div class="employee-onboarding__shade" data-shade="right"></div>
            <div class="employee-onboarding__shade" data-shade="bottom"></div>
            <div class="employee-onboarding__ring" data-onboarding-ring hidden></div>
            <div class="employee-onboarding__popover" data-onboarding-popover dir="rtl" lang="fa" role="dialog" aria-modal="true">
                <div class="employee-onboarding__progress" data-onboarding-progress></div>
                <h3 class="employee-onboarding__title" data-onboarding-title></h3>
                <p class="employee-onboarding__content" data-onboarding-content></p>
                <div class="employee-onboarding__actions">
                    <button type="button" class="employee-onboarding__skip" data-onboarding-skip>رد کردن</button>
                    <div class="employee-onboarding__nav">
                        <button type="button" class="saas-btn-primary text-sm" data-onboarding-next>بعدی</button>
                        <button type="button" class="saas-btn-secondary text-sm" data-onboarding-prev>قبلی</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(root);

        this.ui = root;
        this.shades = {
            top: root.querySelector('[data-shade="top"]'),
            left: root.querySelector('[data-shade="left"]'),
            right: root.querySelector('[data-shade="right"]'),
            bottom: root.querySelector('[data-shade="bottom"]'),
        };
        this.ring = root.querySelector('[data-onboarding-ring]');

        root.querySelector('[data-onboarding-next]')?.addEventListener('click', () => this.next());
        root.querySelector('[data-onboarding-prev]')?.addEventListener('click', () => this.prev());
        root.querySelector('[data-onboarding-skip]')?.addEventListener('click', () => this.skip());

        Object.values(this.shades).forEach((shade) => {
            shade?.addEventListener('click', () => this.skip());
        });
    }

    bindTriggers() {
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-onboarding-trigger]');

            if (! trigger || this.active) {
                return;
            }

            event.preventDefault();
            this.startPageTour();
        });

        document.addEventListener('livewire:navigated', () => {
            window.__employeeOnboarding.currentRoute = resolveEmployeeRoute(window.location.pathname);
            window.setTimeout(() => {
                this.tryResume();

                if (this.active) {
                    this.renderStep();
                }
            }, 400);
        });
    }

    shouldAutoStartFullTour() {
        if (localStorage.getItem(`${STORAGE_PREFIX}${FULL_TOUR_ID}_skipped`) === '1') {
            return false;
        }

        if (localStorage.getItem(`${STORAGE_PREFIX}${FULL_TOUR_ID}_completed`) === '1') {
            return false;
        }

        return resolveEmployeeRoute(window.location.pathname) === 'employee.dashboard';
    }

    tryResume() {
        const raw = sessionStorage.getItem(RESUME_KEY);

        if (! raw) {
            return;
        }

        try {
            const payload = JSON.parse(raw);

            if (! payload?.tourId || typeof payload.stepIndex !== 'number') {
                sessionStorage.removeItem(RESUME_KEY);

                return;
            }

            sessionStorage.removeItem(RESUME_KEY);
            this.run(payload.tourId, payload.steps ?? this.stepsForTour(payload.tourId), payload.stepIndex);
        } catch {
            sessionStorage.removeItem(RESUME_KEY);
        }
    }

    stepsForTour(tourId) {
        if (tourId === FULL_TOUR_ID) {
            return buildFullEmployeeTour();
        }

        const route = resolveEmployeeRoute(window.location.pathname);
        const tour = tourForRoute(route);

        return tour?.steps ?? [];
    }

    startPageTour() {
        const route = resolveEmployeeRoute(window.location.pathname);
        const tour = tourForRoute(route);

        if (! tour?.steps?.length) {
            window.dispatchEvent(new CustomEvent('show-toast', {
                detail: { type: 'info', message: 'راهنمای این صفحه هنوز تعریف نشده است.' },
            }));

            return;
        }

        this.run(`page:${route}`, tour.steps, 0);
    }

    startFullTour() {
        this.run(FULL_TOUR_ID, buildFullEmployeeTour(), 0);
    }

    run(tourId, steps, stepIndex = 0) {
        if (! steps.length) {
            return;
        }

        this.ensureUi();
        this.tourId = tourId;
        this.steps = steps;
        this.stepIndex = Math.max(0, Math.min(stepIndex, steps.length - 1));
        this.active = true;
        this.ui.hidden = false;
        document.body.classList.add('employee-onboarding-open');
        document.addEventListener('keydown', this.onKeydown);
        window.addEventListener('resize', this.onResize, { passive: true });
        window.addEventListener('scroll', this.onResize, { passive: true });
        this.renderStep();
    }

    stop(markCompleted = false) {
        if (! this.active) {
            this.clearFocusTarget();

            return;
        }

        this.active = false;
        this.ui.hidden = true;
        document.body.classList.remove('employee-onboarding-open');
        document.removeEventListener('keydown', this.onKeydown);
        window.removeEventListener('resize', this.onResize);
        window.removeEventListener('scroll', this.onResize);
        sessionStorage.removeItem(RESUME_KEY);
        this.clearFocusTarget();
        this.clearSpotlight();

        if (markCompleted && this.tourId) {
            localStorage.setItem(`${STORAGE_PREFIX}${this.tourId}_completed`, '1');
        }
    }

    skip() {
        if (this.tourId === FULL_TOUR_ID || this.tourId?.startsWith('page:')) {
            localStorage.setItem(`${STORAGE_PREFIX}${this.tourId === FULL_TOUR_ID ? FULL_TOUR_ID : this.tourId}_skipped`, '1');
        }

        if (this.tourId === FULL_TOUR_ID) {
            localStorage.setItem(`${STORAGE_PREFIX}${FULL_TOUR_ID}_skipped`, '1');
        }

        this.stop(false);
    }

    complete() {
        this.stop(true);
    }

    shouldNavigateForStep(step) {
        if (! step?.route) {
            return false;
        }

        if (step.route === resolveEmployeeRoute(window.location.pathname)) {
            return false;
        }

        const routeUrl = window.__employeeOnboarding?.routes?.[step.route];

        if (! routeUrl) {
            return false;
        }

        const isMobile = window.matchMedia('(max-width: 1023px)').matches;

        if (step.selector?.includes('data-tour-nav') && isMobile) {
            return true;
        }

        if (! step.center && step.selector && this.findTarget(step.selector)) {
            const target = this.findTarget(step.selector);

            if (target?.closest('.saas-sidebar') && isMobile && ! Alpine.store('layout')?.sidebarOpen) {
                return true;
            }

            return false;
        }

        return true;
    }

    navigateToStep(step, stepIndex) {
        const url = window.__employeeOnboarding?.routes?.[step.route];

        if (! url) {
            this.goToStep(stepIndex);

            return;
        }

        Alpine.store('layout')?.closeSidebar();
        this.ui.hidden = true;
        sessionStorage.setItem(RESUME_KEY, JSON.stringify({
            tourId: this.tourId,
            stepIndex,
            steps: this.steps,
        }));

        window.location.assign(url);
    }

    next() {
        const nextStep = this.steps[this.stepIndex + 1];

        if (! nextStep) {
            this.complete();

            return;
        }

        if (this.shouldNavigateForStep(nextStep)) {
            this.navigateToStep(nextStep, this.stepIndex + 1);

            return;
        }

        this.goToStep(this.stepIndex + 1);
    }

    prev() {
        if (this.stepIndex <= 0) {
            return;
        }

        const prevStep = this.steps[this.stepIndex - 1];

        if (this.shouldNavigateForStep(prevStep)) {
            this.navigateToStep(prevStep, this.stepIndex - 1);

            return;
        }

        this.goToStep(this.stepIndex - 1);
    }

    goToStep(index) {
        this.stepIndex = index;
        this.renderStep();
    }

    renderStep() {
        const step = this.steps[this.stepIndex];

        if (! step) {
            this.complete();

            return;
        }

        const title = this.ui.querySelector('[data-onboarding-title]');
        const content = this.ui.querySelector('[data-onboarding-content]');
        const progress = this.ui.querySelector('[data-onboarding-progress]');
        const prevBtn = this.ui.querySelector('[data-onboarding-prev]');
        const nextBtn = this.ui.querySelector('[data-onboarding-next]');
        const popover = this.ui.querySelector('[data-onboarding-popover]');

        title.textContent = step.title;
        content.textContent = step.content;
        progress.textContent = `مرحله ${this.stepIndex + 1} از ${this.steps.length}`;

        prevBtn.disabled = this.stepIndex === 0;
        nextBtn.textContent = this.stepIndex === this.steps.length - 1 ? 'پایان' : 'بعدی';

        const target = step.center
            ? null
            : (step.fab ? this.findFabTarget() : this.findTarget(step.selector));

        if (target?.closest('.saas-sidebar') && window.matchMedia('(max-width: 1023px)').matches) {
            Alpine.store('layout')?.openSidebar();
        }

        if (! target) {
            this.clearFocusTarget();
            this.showFullDim();
            popover.classList.add('employee-onboarding__popover--center');
            popover.style.top = '50%';
            popover.style.left = '50%';
            popover.style.transform = 'translate(-50%, -50%)';

            return;
        }

        popover.classList.remove('employee-onboarding__popover--center');
        this.setFocusTarget(target);

        const measureTarget = () => this.positionAround(target, step.placement ?? 'bottom', step.align ?? 'center', popover);

        if (! this.isFixedTarget(target)) {
            target.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
            this.schedulePosition(measureTarget);
        } else {
            measureTarget();
        }
    }

    isFixedTarget(target) {
        const position = window.getComputedStyle(target).position;

        return position === 'fixed' || position === 'sticky';
    }

    schedulePosition(callback) {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                callback();
            });
        });
    }

    findTarget(selector) {
        if (! selector) {
            return null;
        }

        const candidates = document.querySelectorAll(selector);

        for (const candidate of candidates) {
            if (! candidate.closest('#employee-onboarding-root')) {
                return candidate;
            }
        }

        return null;
    }

    findFabTarget() {
        return this.findTarget(FAB_SELECTOR);
    }

    setShadeRect(shade, left, top, width, height) {
        if (! shade) {
            return;
        }

        shade.style.left = `${left}px`;
        shade.style.top = `${top}px`;
        shade.style.width = `${Math.max(0, width)}px`;
        shade.style.height = `${Math.max(0, height)}px`;
    }

    positionSpotlight(x, y, width, height) {
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        this.setShadeRect(this.shades.top, 0, 0, viewportWidth, y);
        this.setShadeRect(this.shades.left, 0, y, x, height);
        this.setShadeRect(this.shades.right, x + width, y, viewportWidth - x - width, height);
        this.setShadeRect(this.shades.bottom, 0, y + height, viewportWidth, viewportHeight - y - height);
    }

    setFocusTarget(target) {
        this.focusedTarget = target;
    }

    clearFocusTarget() {
        this.focusedTarget = null;
    }

    showFullDim() {
        this.positionSpotlight(0, 0, 0, 0);
        this.setShadeRect(this.shades.top, 0, 0, window.innerWidth, window.innerHeight);
        this.ring.hidden = true;
    }

    positionAround(target, placement, align, popover) {
        const rect = target.getBoundingClientRect();
        const padding = 10;
        const x = Math.max(8, rect.left - padding);
        const y = Math.max(8, rect.top - padding);
        const width = Math.min(window.innerWidth - 16, rect.width + padding * 2);
        const height = Math.min(window.innerHeight - 16, rect.height + padding * 2);

        this.positionSpotlight(x, y, width, height);

        this.ring.hidden = false;
        this.ring.style.inset = 'auto';
        this.ring.style.top = `${y}px`;
        this.ring.style.left = `${x}px`;
        this.ring.style.right = 'auto';
        this.ring.style.bottom = 'auto';
        this.ring.style.width = `${width}px`;
        this.ring.style.height = `${height}px`;

        const popRect = popover.getBoundingClientRect();
        const margin = 14;
        let top = rect.bottom + margin;
        let left = rect.left + rect.width / 2 - popRect.width / 2;

        if (placement === 'top') {
            top = rect.top - popRect.height - margin;
        }

        if (placement === 'left') {
            top = rect.top + rect.height / 2 - popRect.height / 2;
            left = rect.left - popRect.width - margin;
        }

        if (placement === 'right') {
            top = rect.top + rect.height / 2 - popRect.height / 2;
            left = rect.right + margin;
        }

        if (align === 'start') {
            left = rect.left;
        } else if (align === 'end') {
            left = rect.right - popRect.width;
        }

        const maxLeft = window.innerWidth - popRect.width - 16;
        const maxTop = window.innerHeight - popRect.height - 16;

        popover.style.inset = 'auto';
        popover.style.transform = 'none';
        popover.style.right = 'auto';
        popover.style.bottom = 'auto';
        popover.style.top = `${Math.min(Math.max(16, top), maxTop)}px`;
        popover.style.left = `${Math.min(Math.max(16, left), maxLeft)}px`;
    }

    clearSpotlight() {
        this.showFullDim();
    }

    onKeydown(event) {
        if (! this.active) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            this.skip();
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.next();
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.prev();
        }
    }

    onResize() {
        if (! this.active) {
            return;
        }

        this.renderStep();
    }
}

const tour = new EmployeeOnboardingTour();

export function initEmployeeOnboarding() {
    if (! window.__employeeOnboarding) {
        return false;
    }

    window.__employeeOnboarding.currentRoute = resolveEmployeeRoute(window.location.pathname);
    tour.init();
    window.employeeOnboardingTour = tour;

    return true;
}

function bootEmployeeOnboarding(attempt = 0) {
    if (initEmployeeOnboarding()) {
        return;
    }

    if (attempt < 40) {
        window.setTimeout(() => bootEmployeeOnboarding(attempt + 1), 50);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => bootEmployeeOnboarding());
} else {
    bootEmployeeOnboarding();
}

document.addEventListener('livewire:init', () => bootEmployeeOnboarding());
document.addEventListener('livewire:navigated', () => {
    if (window.__employeeOnboarding) {
        window.__employeeOnboarding.currentRoute = resolveEmployeeRoute(window.location.pathname);
    }
});

export { employeePageTours, FULL_TOUR_ID };
