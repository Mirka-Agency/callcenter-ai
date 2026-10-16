<div class="saas-page space-y-6">
    <x-saas.page-header
        title="نیازمند بررسی"
        description="پیشنهاد هویت از تحلیل تماس. تا وقتی تأیید نشود، نام و شرکت شخص عوض نمی‌شود."
    />

    @include('livewire.shared.customers.partials.section-nav', ['portal' => $portal, 'active' => 'reviews'])

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ session('status') }}
        </div>
    @endif

    @error('review')
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</div>
    @enderror

    <div class="space-y-3">
        @forelse ($reviews as $review)
            @php
                $kindLabel = match ($review->kind) {
                    'field_suggestion' => 'پیشنهاد فیلد',
                    'company_candidate' => 'پیشنهاد شرکت',
                    'duplicate_phone' => 'شماره تکراری',
                    'unresolved_phone' => 'شماره نامشخص',
                    'phone_candidate' => 'شماره پیشنهادی',
                    default => $review->kind,
                };
            @endphp
            <article class="saas-card space-y-3" wire:key="review-{{ $review->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">{{ $kindLabel }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">
                            @if ($review->field !== '')
                                فیلد: {{ $review->field }}
                            @endif
                            @if ($review->context['reason'] ?? null)
                                دلیل: {{ $review->context['reason'] }}
                            @endif
                        </p>
                    </div>
                    <p class="text-xs text-zinc-400">{{ shamsi($review->created_at) }}</p>
                </div>

                @if ($review->current_value || $review->suggested_value)
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-zinc-500">مقدار فعلی</dt>
                            <dd class="mt-1 font-medium">{{ $review->current_value ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">پیشنهاد</dt>
                            <dd class="mt-1 font-medium">{{ $review->suggested_value ?: '—' }}</dd>
                        </div>
                    </dl>
                @endif

                @if ($review->evidence)
                    <p class="text-sm leading-7 text-zinc-600">شاهد: {{ $review->evidence }}</p>
                @endif

                @if ($review->kind === 'duplicate_phone')
                    <p class="text-sm text-zinc-600">چند رکورد برای شکل‌های معادل این شماره وجود دارد. هیچ‌کدام حذف یا به هم وصل نشده‌اند.</p>
                @endif

                @if ($canResolve)
                    <div class="flex flex-wrap gap-2">
                        @if (in_array($review->kind, ['field_suggestion', 'company_candidate', 'phone_candidate'], true))
                            <button type="button" class="saas-btn-primary text-sm" wire:click="accept({{ $review->id }})">تأیید و ثبت</button>
                        @endif
                        <button type="button" class="saas-btn border border-zinc-300 bg-white text-sm" wire:click="dismiss({{ $review->id }})">بستن بدون تغییر</button>
                    </div>
                @else
                    <p class="text-xs text-zinc-500">تأیید این مورد فقط برای مدیر سازمان است.</p>
                @endif
            </article>
        @empty
            <x-saas.empty-state title="مورد بازی نیست" description="تعارض یا پیشنهاد هویتی در انتظار بررسی نیست." />
        @endforelse
    </div>

    {{ $reviews->links() }}
</div>
