<div class="space-y-10">
    @php
        $filterActionTargets = 'applyCustomDateRange';
    @endphp

    <x-saas.filter-loading-overlay :target="$filterActionTargets" />

    <div class="flex flex-wrap items-center justify-between gap-4" data-tour="manual-upload-header">
        <div>
            <p class="text-sm font-medium uppercase tracking-wider text-indigo-600 dark:text-indigo-400">بارگذاری تماس</p>
            <h1 class="text-3xl font-semibold tracking-tight">تحلیل هوشمند مکالمات</h1>
            <p class="mt-2 max-w-2xl text-zinc-500">فایل صوتی تماس را بارگذاری کنید — هوش مصنوعی در چند دقیقه گفتار، کیفیت و عملکرد را تحلیل می‌کند.</p>
        </div>
        <a href="{{ route('employer.processing-queue.index') }}" class="saas-btn-secondary shrink-0">@lang('ui.cta.view_queue')</a>
    </div>

    @if (($wallet['balance'] ?? 0) < (($wallet['currency'] ?? 'IRR') === 'IRR' ? 1000 : 0.01))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            @lang('ui.wallet.insufficient') <a href="{{ route('employer.wallet.index') }}" class="font-medium underline">شارژ اعتبار تحلیل</a>
        </div>
    @elseif (($wallet['balance'] ?? 0) < (($wallet['currency'] ?? 'IRR') === 'IRR' ? 100_000 : 10))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
            اعتبار تحلیل کم است ({{ \App\Models\PlatformAiSettings::formatMoney($wallet['balance']) }}). قبل از بارگذاری تماس‌های بیشتر، موجودی را شارژ کنید.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <div class="saas-card border-indigo-200/50 shadow-md shadow-indigo-500/5 dark:border-indigo-500/20" data-tour="manual-upload-panel">
            <x-saas.manual-upload-panel
                :employees="$employees"
                :show-employee-assign="true"
                :upload-zone-state="$uploadZoneState"
                :selected-file-name="$selectedFileName"
                :selected-file-size="$selectedFileSize"
                :show-metadata="$showMetadata"
                :audio-ready="$audioReady"
                :highlighted-sample-id="$highlightedSampleId"
            />
        </div>

        <div data-tour="manual-samples">
            <x-saas.sample-conversations
                :samples="$sampleConversations"
                :highlighted-id="$highlightedSampleId"
            />
        </div>
    </div>

    <div class="space-y-4" data-tour="manual-history">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">بارگذاری‌های اخیر</h2>
                <p class="mt-1 text-sm text-zinc-500">تاریخچه تحلیل تماس‌های بارگذاری‌شده توسط تیم</p>
            </div>
        </div>

        <div class="saas-card">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="جستجو..." class="saas-input text-sm">
                <select wire:model.live="filterEmployeeId" class="saas-input text-sm">
                    <option value="">همه کارشناسان</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>

            <div data-deferred-date-range class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-indigo-200/80 bg-indigo-50/50 px-4 py-3 dark:border-indigo-500/30 dark:bg-indigo-950/20">
                <label class="text-sm text-zinc-500">از</label>
                <x-saas.jalali-date-input wire:key="upload-date-from" wire:model="draftCustomFrom" defer class="text-sm" />
                <label class="text-sm text-zinc-500">تا</label>
                <x-saas.jalali-date-input wire:key="upload-date-until" wire:model="draftCustomTo" defer class="text-sm" />
                <button type="button" data-apply-deferred-date-range class="saas-btn-primary text-sm">
                    تایید بازه
                </button>
            </div>
        </div>

        <div class="grid gap-4">
            @forelse ($uploads as $upload)
                <a href="{{ route('employer.manual-analyses.show', $upload) }}" class="saas-card block transition hover:border-zinc-300 dark:hover:border-zinc-600" wire:key="upload-{{ $upload->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-4">
                            @if ($upload->employee)
                                <x-saas.avatar :employee="$upload->employee" size="md" />
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $upload->displayTitle() }}</p>
                                <p class="mt-1 text-sm text-zinc-500">
                                    {{ $upload->employee?->full_name ?? 'بدون اختصاص' }}
                                    · {{ shamsi($upload->created_at, 'datetime') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            @include('livewire.shared.upload-status-badge', ['call' => $upload, 'analysis' => $upload->latestAnalysis])
                            @if ($upload->latestAnalysis)
                                <span class="text-xl font-bold text-emerald-600">{{ $upload->latestAnalysis->score }}</span>
                            @endif
                        </div>
                    </div>
                    @include('livewire.shared.processing-job-progress', ['job' => $upload->processingJob])
                    @if ($upload->latestAnalysis)
                        <p class="mt-3 text-xs text-zinc-400">
                            {{ number_format($upload->latestAnalysis->total_tokens) }} توکن · {{ \App\Models\PlatformAiSettings::formatMoney($upload->latestAnalysis->cost) }}
                        </p>
                    @endif
                </a>
            @empty
                <x-saas.empty-state
                    title="{{ __('ui.empty.no_uploads.title') }}"
                    description="{{ __('ui.empty.no_uploads.description') }}"
                />
            @endforelse
        </div>

        {{ $uploads->links() }}
    </div>
</div>

@script
<script>
    const orgId = @js($organizationId);
    if (window.Echo && orgId) {
        window.Echo.private(`organization.${orgId}.processing-queue`)
            .listen('.CallProcessingUpdated', (e) => {
                Livewire.dispatch('processing-job-updated', e.job ?? e);
                if (e.notification) {
                    const url = e.notification.url
                        || (e.notification.type === 'success' && e.job?.call_id
                            ? `/app/manual-analyses/${e.job.call_id}`
                            : e.job?.job_uuid
                                ? `/app/processing-queue/${e.job.job_uuid}`
                                : null);
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: { ...e.notification, url },
                    }));
                }
            });
    }
</script>
@endscript
