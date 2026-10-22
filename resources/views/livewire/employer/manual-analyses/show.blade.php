<div class="space-y-8">
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

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    @unless ($upload->employee)
        <div class="saas-card space-y-3">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">این تماس کارشناس ندارد</h2>
            <p class="text-sm text-zinc-500">کارشناس را انتخاب کنید تا امتیاز، کوچینگ و متریک عملکرد برای همان شخص محاسبه شود.</p>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <select wire:model="assignEmployeeId" class="saas-input min-w-0 flex-1">
                    <option value="0">انتخاب کارشناس…</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name ?: ('کارشناس #'.$employee->id) }}</option>
                    @endforeach
                </select>
                <button
                    type="button"
                    wire:click="assignCallEmployee"
                    wire:loading.attr="disabled"
                    wire:target="assignCallEmployee"
                    class="saas-btn-primary shrink-0 whitespace-nowrap text-sm"
                >
                    <span wire:loading.remove wire:target="assignCallEmployee">تخصیص کارشناس</span>
                    <span wire:loading wire:target="assignCallEmployee">در حال تخصیص…</span>
                </button>
            </div>
            @error('assignEmployeeId')
                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            <a href="{{ $createEmployeeUrl }}" class="inline-flex text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                یا کارشناس جدید بسازید
            </a>
        </div>
    @endunless

    @include('livewire.shared.recording-player', ['recordingUrl' => $recordingUrl ?? null, 'recordingExpired' => $recordingExpired ?? false])

    <div @if($call->processingJob?->isActive()) wire:poll.5s @endif>
        @include('livewire.shared.call-processing-status', ['call' => $call, 'queueUrl' => $queueUrl])
    </div>

    @if ($analysis)
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="saas-card lg:col-span-2">
                <h2 class="text-lg font-semibold">خلاصه مکالمه</h2>
                <p class="mt-3 whitespace-pre-wrap leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $analysis->summary }}</p>
            </div>
            <div class="saas-card space-y-4">
                <div><span class="text-sm text-zinc-500">امتیاز</span><p class="text-2xl font-bold text-emerald-600">{{ $analysis->score }}</p></div>
                <div><span class="text-sm text-zinc-500">احساس</span><p class="font-medium">{{ $analysis->sentiment->label() }}</p></div>
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
