@php
    $exportEntity = $exportEntity ?? 'contacts';
    $exportSearch = $exportSearch ?? ($search ?? '');
    $exportSort = $exportSort ?? ($sort ?? 'last_contact');
@endphp

<div class="crm-directory-header-actions">
    <div class="crm-directory-header-bar">
        <div class="crm-directory-actions">
            <button type="button" class="crm-directory-action crm-directory-action--add" wire:click="openCreateDialog">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                </svg>
                افزودن
            </button>
            <x-saas.customer-list-export class="crm-directory-export" :portal="$portal" :entity="$exportEntity" :search="$exportSearch" :sort="$exportSort" />
        </div>
        @include('livewire.shared.customers.partials.directory-stats', [
            'stats' => $stats,
            'portal' => $portal,
            'active' => $active ?? null,
        ])
    </div>
</div>
