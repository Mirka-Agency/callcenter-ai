@props(['portal' => ''])

@if ($portal === 'employer')
    <button
        type="button"
        data-onboarding-trigger
        data-tour="onboarding-fab"
        class="employer-onboarding-fab"
        title="راهنمای این صفحه"
        aria-label="راهنمای تعاملی این صفحه"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3m.08 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>راهنما</span>
    </button>
@endif
