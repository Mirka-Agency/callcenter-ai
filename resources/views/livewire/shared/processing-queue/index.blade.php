<div class="space-y-8" @if($autoRefresh) wire:poll.5s @endif>
    @php
        $filterLoadingTargets = 'search,statusFilter';
    @endphp

    <x-saas.filter-loading-overlay :target="$filterLoadingTargets" />

    <div class="flex flex-wrap items-center justify-between gap-4" data-tour="queue-header">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">صف پردازش</h1>
            <p class="mt-2 text-zinc-500">نظارت بر آپلود فایل‌های صوتی و کارهای تحلیل هوش مصنوعی به‌صورت لحظه‌ای.</p>
        </div>
        <button type="button" wire:click="refreshQueue" class="saas-btn-secondary">بروزرسانی</button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" data-tour="queue-stats">
        <div class="saas-card"><p class="text-sm text-zinc-500">مجموع</p><p class="mt-1 text-2xl font-bold">{{ $stats['total'] }}</p></div>
        <div class="saas-card"><p class="text-sm text-zinc-500">در صف</p><p class="mt-1 text-2xl font-bold text-amber-600">{{ $stats['queued'] }}</p></div>
        <div class="saas-card"><p class="text-sm text-zinc-500">در حال پردازش</p><p class="mt-1 text-2xl font-bold text-blue-600">{{ $stats['processing'] }}</p></div>
        <div class="saas-card"><p class="text-sm text-zinc-500">تکمیل‌شده</p><p class="mt-1 text-2xl font-bold text-emerald-600">{{ $stats['completed'] }}</p></div>
        <div class="saas-card"><p class="text-sm text-zinc-500">ناموفق</p><p class="mt-1 text-2xl font-bold text-red-600">{{ $stats['failed'] }}</p></div>
    </div>

    <div class="saas-card" data-tour="queue-filters">
        <div class="flex flex-wrap gap-4">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="جستجو بر اساس نام فایل..." class="saas-input max-w-md flex-1">
            <select wire:model.live="statusFilter" class="saas-input w-auto">
                <option value="">همه وضعیت‌ها</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-6 overflow-x-auto" wire:loading.class="opacity-60" wire:target="{{ $filterLoadingTargets }}" data-tour="queue-table">
            <table class="saas-table w-full min-w-[900px]">
                <thead>
                    <tr>
                        <th>فایل</th>
                        <th>وضعیت</th>
                        <th>مرحله</th>
                        <th>پیشرفت</th>
                        <th>آپلود</th>
                        <th>شروع</th>
                        <th>تکمیل</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr wire:key="job-{{ $job->id }}">
                            <td>{{ $job->file_name }}</td>
                            <td><span @class([
                                'rounded-md px-2 py-0.5 text-xs font-medium',
                                'bg-zinc-100 text-zinc-700' => $job->status->value === 'uploading',
                                'bg-amber-100 text-amber-800' => $job->status->value === 'queued',
                                'bg-blue-100 text-blue-800' => $job->status->value === 'processing',
                                'bg-emerald-100 text-emerald-800' => $job->status->value === 'completed',
                                'bg-red-100 text-red-800' => in_array($job->status->value, ['failed', 'cancelled'], true),
                            ])>{{ $job->status->label() }}</span></td>
                            <td class="text-sm text-zinc-600">{{ $job->stage->label() }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $job->progress_percentage }}%"></div>
                                    </div>
                                    <span class="text-xs text-zinc-500">{{ $job->progress_percentage }}%</span>
                                </div>
                            </td>
                            <td class="text-xs text-zinc-500">{{ shamsi($job->upload_started_at, 'datetime') }}</td>
                            <td class="text-xs text-zinc-500">{{ shamsi($job->processing_started_at, 'datetime') }}</td>
                            <td class="text-xs text-zinc-500">{{ shamsi($job->completed_at, 'datetime') }}</td>
                            <td><a href="{{ $jobShowRoute($job) }}" class="text-sm font-medium text-indigo-600 hover:underline">جزئیات</a></td>
                        </tr>
                        @if ($job->error_message)
                            <tr>
                                <td colspan="8" class="text-xs text-red-600">{{ \App\Support\UserFacingError::processing($job->error_message) }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-zinc-500">هنوز کار پردازشی وجود ندارد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $jobs->links() }}</div>
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
                    const isEmployee = window.location.pathname.startsWith('/workspace');
                    const base = isEmployee ? '/workspace' : '/app';
                    const url = e.notification.url
                        || (e.notification.type === 'success' && e.job?.call_id
                            ? (isEmployee ? `${base}/uploads/${e.job.call_id}` : `${base}/manual-analyses/${e.job.call_id}`)
                            : e.job?.job_uuid
                                ? `${base}/processing-queue/${e.job.job_uuid}`
                                : null);
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: { ...e.notification, url },
                    }));
                }
            });
    }
</script>
@endscript
