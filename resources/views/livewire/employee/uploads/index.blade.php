<div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">آپلودهای من</h1>
            <p class="mt-2 text-zinc-500">فایل‌های صوتی را برای تحلیل هوش مصنوعی آپلود کنید.</p>
        </div>
        <a href="{{ route('employee.processing-queue.index') }}" class="saas-btn-secondary">صف پردازش</a>
    </div>

    @if (($wallet['balance'] ?? 0) <= 0)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            موجودی کیف پول هوش مصنوعی کافی نیست ({{ \App\Models\PlatformAiSettings::formatMoney($wallet['balance'] ?? 0) }}).
            تا زمانی که سازمان کیف پول هوش مصنوعی را شارژ نکند، آپلودها با خطا مواجه می‌شوند.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">آپلود فایل صوتی</h2>
            <p class="mt-1 text-sm text-zinc-500">فرمت‌های پشتیبانی‌شده: mp3، wav، m4a، ogg، flac (حداکثر ۵۰ مگابایت)</p>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <div class="lg:col-span-2">
                    <label class="text-sm font-medium">فایل صوتی *</label>
                    <input wire:model="audio" type="file" accept=".mp3,.wav,.m4a,.ogg,.flac,audio/*" class="saas-input mt-1">
                    @error('audio') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="audio" class="mt-2 text-sm text-amber-600">در حال آپلود فایل...</div>
                    <div wire:loading.remove wire:target="audio" class="mt-2 text-xs text-zinc-500">
                        فایل صوتی را انتخاب کنید، سپس روی <strong>آپلود و تحلیل</strong> کلیک کنید.
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium">عنوان</label>
                    <input wire:model="title" class="saas-input mt-1" placeholder="عنوان اختیاری">
                </div>
                <div>
                    <label class="text-sm font-medium">دسته‌بندی</label>
                    <input wire:model="category" class="saas-input mt-1" placeholder="مثلاً فروش، پشتیبانی">
                </div>
                <div>
                    <label class="text-sm font-medium">نام مشتری</label>
                    <input wire:model="customerName" class="saas-input mt-1">
                </div>
                <div>
                    <label class="text-sm font-medium">تلفن مشتری</label>
                    <input wire:model="customerPhone" class="saas-input mt-1">
                </div>
                <div>
                    <label class="text-sm font-medium">تاریخ مکالمه</label>
                    <input wire:model="conversationDate" type="datetime-local" class="saas-input mt-1">
                </div>
                <div>
                    <label class="text-sm font-medium">برچسب‌ها</label>
                    <input wire:model="tags" class="saas-input mt-1" placeholder="با کاما جدا کنید">
                </div>
                <div class="lg:col-span-2">
                    <label class="text-sm font-medium">یادداشت‌ها</label>
                    <textarea wire:model="notes" rows="3" class="saas-input mt-1"></textarea>
                </div>

                <div class="lg:col-span-2">
                    <button
                        type="button"
                        id="upload-analyze-button"
                        wire:click="submitForAnalysis"
                        wire:loading.attr="disabled"
                        wire:target="submitForAnalysis"
                        class="saas-btn-primary"
                    >
                        <span wire:loading.remove wire:target="submitForAnalysis">آپلود و تحلیل</span>
                        <span wire:loading wire:target="submitForAnalysis">در حال تحلیل فایل...</span>
                    </button>
                    <p wire:loading wire:target="submitForAnalysis" class="mt-2 text-sm text-blue-600">فایل در صف پردازش قرار گرفت...</p>
                </div>
            </div>
        </div>

        <x-saas.sample-conversations
            :samples="$sampleConversations"
            :highlighted-id="$highlightedSampleId"
        />
    </div>

    <div class="grid gap-4">
        @forelse ($uploads as $upload)
            <a href="{{ route('employee.uploads.show', $upload) }}" class="saas-card block transition hover:border-zinc-300 dark:hover:border-zinc-600" wire:key="upload-{{ $upload->id }}">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold">{{ $upload->displayTitle() }}</p>
                        <p class="mt-1 text-sm text-zinc-500">
                            {{ shamsi($upload->created_at, 'datetime') }}
                            @if ($upload->customer_name) · {{ $upload->customer_name }} @endif
                        </p>
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
            <x-saas.empty-state title="هنوز آپلودی وجود ندارد" description="یک فایل صوتی آپلود کنید تا تحلیل هوش مصنوعی آغاز شود." />
        @endforelse
    </div>

    {{ $uploads->links() }}
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
                            ? `/workspace/uploads/${e.job.call_id}`
                            : e.job?.job_uuid
                                ? `/workspace/processing-queue/${e.job.job_uuid}`
                                : null);
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: { ...e.notification, url },
                    }));
                }
            });
    }

    const uploadBtn = document.getElementById('upload-analyze-button');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', () => {});
    }
</script>
@endscript
