<div class="space-y-8" @if($job->isActive()) wire:poll.5s @endif>
    @php
        $showAiInfrastructure = \App\Support\AiInfrastructure::isVisible();
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">جزئیات پردازش</h1>
            <p class="mt-2 text-sm text-zinc-500">{{ $job->file_name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $queueIndexUrl }}" class="saas-btn-secondary">@lang('ui.cta.view_queue')</a>
            <a href="{{ $uploadUrl }}" class="saas-btn-secondary">مشاهده نتیجه بارگذاری</a>
            @if ($job->status->isRecoverable())
                <button type="button" wire:click="retryProcessingJob" class="saas-btn-primary">{{ __('ui.processing.retry') }}</button>
                <button type="button" wire:click="deleteProcessingJob" wire:confirm="{{ __('ui.processing.delete_confirm') }}" class="saas-btn-secondary text-red-600">{{ __('ui.processing.delete') }}</button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="saas-card">
            <p class="text-sm text-zinc-500">وضعیت</p>
            <p class="mt-1 text-lg font-semibold">{{ $job->status->label() }}</p>
        </div>
        <div class="saas-card">
            <p class="text-sm text-zinc-500">مرحله</p>
            <p class="mt-1 text-lg font-semibold">{{ $job->stage->label() }}</p>
        </div>
        <div class="saas-card">
            <p class="text-sm text-zinc-500">پیشرفت</p>
            <p class="mt-1 text-lg font-semibold">{{ $job->progress_percentage }}%</p>
        </div>
        <div class="saas-card">
            <p class="text-sm text-zinc-500">فایل</p>
            <p class="mt-1 text-lg font-semibold truncate">{{ $job->file_name }}</p>
        </div>
    </div>

    @if ($job->error_message)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            {{ \App\Support\UserFacingError::processing($job->error_message) }}
        </div>
    @endif

    <div class="saas-card">
        <h2 class="text-lg font-semibold">اطلاعات کار</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
            <div><dt class="text-zinc-500">شروع بارگذاری</dt><dd class="font-medium">{{ shamsi($job->upload_started_at, 'datetime_seconds') }}</dd></div>
            <div><dt class="text-zinc-500">ورود به صف</dt><dd class="font-medium">{{ shamsi($job->queued_at, 'datetime_seconds') }}</dd></div>
            <div><dt class="text-zinc-500">شروع پردازش</dt><dd class="font-medium">{{ shamsi($job->processing_started_at, 'datetime_seconds') }}</dd></div>
            <div><dt class="text-zinc-500">زمان تکمیل</dt><dd class="font-medium">{{ shamsi($job->completed_at, 'datetime_seconds') }}</dd></div>
            <div><dt class="text-zinc-500">زمان انتظار در صف</dt><dd class="font-medium">{{ $job->waiting_seconds ? $job->waiting_seconds.'s' : '—' }}</dd></div>
            <div><dt class="text-zinc-500">مدت پردازش</dt><dd class="font-medium">{{ $job->processing_duration_seconds ? $job->processing_duration_seconds.'s' : '—' }}</dd></div>
        </dl>
    </div>

    @if ($analysis)
        <div class="saas-card">
            <h2 class="text-lg font-semibold">نتیجه تحلیل</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                <div><dt class="text-zinc-500">امتیاز</dt><dd class="font-medium">{{ $analysis->score }}</dd></div>
                @if ($showAiInfrastructure)
                    <div><dt class="text-zinc-500">مدل</dt><dd class="font-medium">{{ $analysis->model_name }}</dd></div>
                @endif
                <div><dt class="text-zinc-500">توکن‌های ورودی</dt><dd class="font-medium">{{ number_format($analysis->input_tokens) }}</dd></div>
                <div><dt class="text-zinc-500">توکن‌های خروجی</dt><dd class="font-medium">{{ number_format($analysis->output_tokens) }}</dd></div>
                <div><dt class="text-zinc-500">هزینه</dt><dd class="font-medium">{{ \App\Models\PlatformAiSettings::formatMoney($analysis->cost) }}</dd></div>
                <div><dt class="text-zinc-500">پاسخ هوش مصنوعی</dt><dd class="font-medium text-emerald-600">موفق</dd></div>
            </dl>
            <p class="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $analysis->summary }}</p>
        </div>
    @endif
</div>

@script
<script>
    const orgId = @js($organizationId);
    if (window.Echo && orgId) {
        window.Echo.private(`organization.${orgId}.processing-queue`)
            .listen('.CallProcessingUpdated', (e) => {
                Livewire.dispatch('processing-job-updated', e.job ?? e);
            });
    }
</script>
@endscript
