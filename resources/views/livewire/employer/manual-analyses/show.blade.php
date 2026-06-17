<div class="space-y-8" @if($call->processingJob?->isActive()) wire:poll.5s @endif>
    <div class="flex items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-4">
            @if ($upload->employee)
                <x-saas.avatar :employee="$upload->employee" size="lg" ring />
            @endif
            <div class="min-w-0">
                <h1 class="text-3xl font-bold tracking-tight">{{ $upload->displayTitle() }}</h1>
                <p class="mt-2 text-zinc-500">
                    {{ $upload->employee?->full_name ?? 'بدون اختصاص' }}
                    · {{ shamsi($upload->created_at, 'datetime') }}
                </p>
            </div>
        </div>
        @include('livewire.shared.upload-status-badge', ['call' => $upload, 'analysis' => $analysis])
    </div>

    <div class="saas-card grid gap-4 sm:grid-cols-4">
        <div><span class="text-sm text-zinc-500">منبع</span><p class="font-medium">بارگذاری دستی</p></div>
        <div><span class="text-sm text-zinc-500">بارگذاری‌کننده</span><p class="font-medium">{{ $upload->uploader?->name ?? '—' }}</p></div>
        <div><span class="text-sm text-zinc-500">مشتری</span><p class="font-medium">{{ $upload->customer_name ?? '—' }}</p></div>
        <div><span class="text-sm text-zinc-500">دسته‌بندی</span><p class="font-medium">{{ $upload->category ?? '—' }}</p></div>
    </div>

    @include('livewire.shared.recording-player', ['recordingUrl' => $recordingUrl ?? null, 'recordingExpired' => $recordingExpired ?? false])

    @include('livewire.shared.call-processing-status', ['call' => $call, 'queueUrl' => $queueUrl])

    @if ($analysis)
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="saas-card lg:col-span-2">
                <h2 class="text-lg font-semibold">خلاصه مکالمه</h2>
                <p class="mt-3 whitespace-pre-wrap leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $analysis->summary }}</p>
            </div>
            <div class="saas-card space-y-4">
                <div><span class="text-sm text-zinc-500">امتیاز</span><p class="text-2xl font-bold text-emerald-600">{{ $analysis->score }}</p></div>
                <div><span class="text-sm text-zinc-500">احساس</span><p class="font-medium">{{ $analysis->sentiment->label() }}</p></div>
                <div><span class="text-sm text-zinc-500">توکن‌های ورودی</span><p class="font-medium">{{ number_format($analysis->input_tokens) }}</p></div>
                <div><span class="text-sm text-zinc-500">توکن‌های خروجی</span><p class="font-medium">{{ number_format($analysis->output_tokens) }}</p></div>
                <div><span class="text-sm text-zinc-500">هزینه کل</span><p class="font-medium">{{ \App\Models\PlatformAiSettings::formatMoney($analysis->cost) }}</p></div>
                @include('livewire.shared.analysis-ai-infrastructure', ['analysis' => $analysis])
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="saas-card">
                <h3 class="font-semibold">نقاط قوت</h3>
                <ul class="mt-3 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                    @foreach ($analysis->strengths_json ?? [] as $item)<li>• {{ $item }}</li>@endforeach
                </ul>
            </div>
            <div class="saas-card">
                <h3 class="font-semibold">نقاط ضعف</h3>
                <ul class="mt-3 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                    @foreach ($analysis->weaknesses_json ?? [] as $item)<li>• {{ $item }}</li>@endforeach
                </ul>
            </div>
            <div class="saas-card">
                <h3 class="font-semibold">اقدامات بعدی</h3>
                <ul class="mt-3 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                    @foreach ($analysis->next_actions_json ?? [] as $item)<li>• {{ $item }}</li>@endforeach
                </ul>
            </div>
        </div>

        @include('livewire.shared.analysis-lead-and-concerns')
        @include('livewire.shared.analysis-customer-identity')

        @if ($analysis->transcript)
            <div class="saas-card">
                <h2 class="text-lg font-semibold">رونوشت</h2>
                <div class="mt-4 max-h-96 overflow-y-auto whitespace-pre-wrap rounded-lg bg-zinc-50 p-4 text-sm text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    {{ $analysis->transcript }}
                </div>
            </div>
        @endif
    @else
        <x-saas.empty-state
            title="{{ __('ui.processing.pending_title') }}"
            description="{{ __('ui.processing.pending_description') }}"
        />
    @endif
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
