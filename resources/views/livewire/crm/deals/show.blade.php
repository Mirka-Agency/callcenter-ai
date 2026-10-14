@php
    use App\Application\Crm\Deals\DealMoney;
    use App\Domain\Crm\Deals\DealActivityType;
@endphp

<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <a href="{{ $backUrl }}" class="hover:text-indigo-600" wire:navigate>معاملات</a>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">{{ $deal->title }}</span>
    </nav>

    <x-saas.page-header :title="$deal->title" :description="$deal->pipeline?->name.' · '.$deal->status->label().' · منبع '.$deal->source->label()">
        <x-slot:actions>
            @if ($canUpdate && ! $deal->trashed() && $deal->status->isOpen())
            @endif
            @if ($canReopen && $deal->status->isClosed() && ! $deal->trashed())
                <button type="button" class="saas-btn-secondary text-sm" wire:click="reopen">بازگشایی</button>
            @endif
            @if ($canUpdate && ! $deal->trashed())
                <button type="button" class="saas-btn-secondary text-sm" wire:click="archive" wire:confirm="معامله بایگانی شود؟ سوابق حفظ می‌شوند.">بایگانی</button>
            @endif
        </x-slot:actions>
    </x-saas.page-header>

    @if ($deal->trashed())
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">این معامله بایگانی شده است.</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="saas-card space-y-4 p-4 lg:col-span-2">
            <h2 class="text-lg font-semibold">اطلاعات معامله</h2>
            <form wire:submit="save" class="grid gap-3 md:grid-cols-2">
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">عنوان</span>
                    <input wire:model="title" class="saas-input" @disabled(! $canUpdate || ! $deal->status->isOpen() || $deal->trashed())>
                    @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">مبلغ (ریال)</span>
                    <input wire:model="amountRials" inputmode="numeric" class="saas-input" @disabled(! $canUpdate || ! $deal->status->isOpen() || $deal->trashed())>
                    @error('amount_rials') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">مرحله</span>
                    <select wire:model="stageId" class="saas-input" @disabled(! $canUpdate || ! $deal->status->isOpen() || $deal->trashed())>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage->id }}" @disabled(! $stage->is_active && (int) $deal->crm_pipeline_stage_id !== (int) $stage->id)>{{ $stage->name }}</option>
                        @endforeach
                    </select>
                    @if ($canUpdate && $deal->status->isOpen() && ! $deal->trashed())
                        <button type="button" class="mt-2 text-sm text-indigo-700" wire:click="moveStage">ثبت تغییر مرحله</button>
                    @endif
                    @error('stage') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('status') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">تاریخ احتمالی بسته‌شدن</span>
                    <input wire:model="expectedCloseOn" type="date" class="saas-input" @disabled(! $canUpdate || ! $deal->status->isOpen() || $deal->trashed())>
                </label>
                <label class="block text-sm md:col-span-2">
                    <span class="mb-1 block text-zinc-600">توضیحات و یادداشت</span>
                    <textarea wire:model="description" rows="4" class="saas-input" @disabled(! $canUpdate || $deal->trashed())></textarea>
                    @error('description') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>
                @if ($canUpdate && ! $deal->trashed())
                    <div>
                        <button type="submit" class="saas-btn-primary text-sm" wire:loading.attr="disabled">ذخیره</button>
                    </div>
                @endif
            </form>
            <div class="grid gap-3 border-t border-zinc-100 pt-4 md:grid-cols-2">
                <label class="block text-sm md:col-span-2">
                    <span class="mb-1 block text-zinc-600">اقدام بعدی</span>
                    <input wire:model="nextActionText" class="saas-input" @disabled(! $canUpdate || $deal->trashed()) placeholder="مثلاً ارسال پیشنهاد به‌روز">
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">موعد اقدام بعدی</span>
                    <input wire:model="nextActionDueOn" type="date" class="saas-input" @disabled(! $canUpdate || $deal->trashed())>
                </label>
            </div>
            @if ($deal->status->value === 'lost')
                <p class="text-sm text-rose-700">دلیل شکست: {{ $deal->lost_reason }}@if($deal->lost_reason_code) ({{ $deal->lost_reason_code }})@endif</p>
            @endif
            @if ($deal->status->value === 'won' && $deal->won_reason)
                <p class="text-sm text-emerald-700">دلیل موفقیت: {{ $deal->won_reason }}</p>
            @endif
            @if ($canUpdate && $deal->status->isOpen() && ! $deal->trashed())
                <div class="space-y-3 border-t border-zinc-100 pt-4">
                    <form wire:submit="closeWon" class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <label class="block text-sm">
                            <span class="mb-1 block text-zinc-600">دلیل موفقیت (اختیاری)</span>
                            <select wire:model="wonReasonCode" class="saas-input">
                                <option value="">بدون کد</option>
                                @foreach ($wonReasons as $reason)
                                    <option value="{{ $reason->code }}">{{ $reason->label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-zinc-600">توضیح موفقیت</span>
                            <input wire:model="wonReason" class="saas-input" placeholder="اختیاری">
                            @error('won_reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </label>
                        <button type="submit" class="saas-btn-primary self-end text-sm">ثبت موفق</button>
                    </form>
                    <form wire:submit="closeLost" class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <label class="block text-sm">
                            <span class="mb-1 block text-zinc-600">دلیل شکست</span>
                            <select wire:model="lostReasonCode" class="saas-input">
                                <option value="">انتخاب دلیل</option>
                                @foreach ($lostReasons as $reason)
                                    <option value="{{ $reason->code }}">{{ $reason->label }}</option>
                                @endforeach
                            </select>
                            @error('lost_reason_code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-zinc-600">توضیح</span>
                            <input wire:model="lostReason" class="saas-input" placeholder="در صورت نیاز توضیح">
                            @error('lost_reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </label>
                        <button type="submit" class="saas-btn-secondary self-end text-sm">ثبت ناموفق</button>
                    </form>
                </div>
            @endif
            @error('deal') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
        </section>

        <div class="space-y-6">
            <section class="saas-card space-y-3 p-4">
                <h2 class="text-lg font-semibold">سلامت معامله</h2>
                @php
                    $healthLevel = $health['level'] ?? null;
                    $healthLabel = $healthLevel instanceof \App\Domain\Crm\Deals\DealHealthLevel
                        ? $healthLevel->label()
                        : (\App\Domain\Crm\Deals\DealHealthLevel::tryFrom((string) $healthLevel)?->label() ?? 'داده ناکافی');
                @endphp
                <p class="text-2xl font-semibold">{{ $health['score'] === null ? '—' : $health['score'] }}</p>
                <p class="text-sm text-zinc-600">سطح: {{ $healthLabel }}</p>
                <p class="text-xs text-zinc-500">این شاخص احتمال آماری برد نیست و مستقل از امتیاز لید و مرحله فروش است.</p>
                @if (! empty($health['calculated_at']))
                    <p class="text-xs text-zinc-400">آخرین محاسبه: {{ shamsi($health['calculated_at']) }}</p>
                @endif
                <ul class="space-y-1 text-sm text-zinc-600">
                    @foreach (($health['factors'] ?? []) as $factor)
                        <li>{{ $factor['label'] ?? '' }}@if(($factor['impact'] ?? 0) !== 0) ({{ $factor['impact'] > 0 ? '+' : '' }}{{ $factor['impact'] }})@endif</li>
                    @endforeach
                </ul>
            </section>

            <section class="saas-card space-y-3 p-4">
                <h2 class="text-lg font-semibold">توقف و فعالیت</h2>
                <p class="text-sm">روز در مرحله فعلی: {{ $aging['days_in_current_stage'] ?? '—' }}</p>
                <p class="text-sm">روز بدون فعالیت: {{ $aging['inactive_days'] ?? '—' }}</p>
                <p class="text-sm">آخرین فعالیت: {{ $aging['last_activity_at'] ? shamsi($aging['last_activity_at']) : '—' }}</p>
                <p class="text-sm">آخرین تماس مرتبط: {{ $aging['last_related_call_at'] ? shamsi($aging['last_related_call_at']) : '—' }}</p>
                @if ($aging['is_stale'] ?? false)
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">این معامله متوقف یا بدون فعالیت کافی تشخیص داده شده است.</p>
                @endif
            </section>

            <section class="saas-card space-y-3 p-4">
                <h2 class="text-lg font-semibold">مسئول معامله</h2>
                @if ($canAssignOwner && $deal->status->isOpen() && ! $deal->trashed())
                    <div class="flex flex-col gap-2">
                        <select wire:model="ownerId" class="saas-input">
                            <option value="">بدون مسئول</option>
                            @foreach ($owners as $member)
                                <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="saas-btn-secondary text-sm" wire:click="assignOwner">ثبت مسئول</button>
                        @error('owner') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    <p class="text-sm">{{ $deal->owner?->full_name ?: 'بدون مسئول' }}</p>
                @endif
                <p class="text-xs text-zinc-500">مسئول معامله با مسئول مشتری یکی نیست و مرحله معامله، چرخه عمر مشتری را عوض نمی‌کند.</p>
            </section>

            <section class="saas-card space-y-3 p-4">
                <h2 class="text-lg font-semibold">شخص و شرکت</h2>
                <p class="text-sm text-zinc-600">شرکت: {{ $deal->company?->name ?: '—' }}</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($selectedCustomers as $customer)
                        <li class="flex items-center justify-between gap-2" wire:key="contact-{{ $customer->id }}">
                            <span>{{ $customer->displayName() }} @if ((string) $primaryCustomerId === (string) $customer->id) <span class="text-xs text-indigo-700">اصلی</span> @endif</span>
                            @if ($canUpdate && $deal->status->isOpen() && ! $deal->trashed())
                                <button type="button" class="text-xs text-rose-600" wire:click="removeCustomer({{ $customer->id }})">حذف</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($canUpdate && $deal->status->isOpen() && ! $deal->trashed())
                    <input wire:model.live.debounce.300ms="addCustomerQuery" class="saas-input" placeholder="افزودن شخص">
                    <label class="block text-xs text-zinc-500">
                        مخاطب اصلی
                        <select wire:model="primaryCustomerId" class="saas-input mt-1">
                            @foreach ($selectedCustomers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
                            @endforeach
                        </select>
                    </label>
                    @foreach ($customerChoices as $customer)
                        <button type="button" class="block text-sm text-indigo-700" wire:click="addCustomer({{ $customer->id }})">{{ $customer->displayName() }}</button>
                    @endforeach
                @endif
                <p class="text-sm">ارزش: {{ DealMoney::formatRials($deal->amount_rials) }}</p>
            </section>
        </div>
    </div>

    <section class="saas-card p-4">
        <h2 class="text-lg font-semibold">تاریخچه مراحل</h2>
        <ol class="mt-4 space-y-3">
            @forelse ($deal->stageHistory->sortByDesc('id') as $entry)
                <li class="text-sm" wire:key="history-{{ $entry->id }}">
                    <span class="text-zinc-500">{{ shamsi($entry->changed_at) }}</span>
                    {{ $entry->from_stage_name ?: '—' }} ← {{ $entry->to_stage_name ?: '—' }}
                    ({{ $entry->from_status ?: '—' }} به {{ $entry->to_status }})
                    <span class="text-zinc-400">{{ $entry->actor?->name ?: 'سامانه' }} · {{ $entry->source }}</span>
                </li>
            @empty
                <li class="text-sm text-zinc-500">هنوز تغییری ثبت نشده است.</li>
            @endforelse
        </ol>
    </section>

    <section class="saas-card space-y-4 p-4">
        <h2 class="text-lg font-semibold">تماس‌ها و تحلیل هوش مصنوعی</h2>
        @if ($canUpdate && ! $deal->trashed() && $calls->isNotEmpty())
            <div class="flex flex-col gap-2 sm:flex-row">
                <select wire:model="linkCallId" class="saas-input">
                    <option value="">انتخاب تماس شخص مرتبط</option>
                    @foreach ($calls as $call)
                        <option value="{{ $call->id }}">{{ $call->customer_phone ?: $call->caller_number }} · {{ shamsi($call->started_at) }}</option>
                    @endforeach
                </select>
                <button type="button" class="saas-btn-secondary text-sm" wire:click="linkCall">اتصال تماس</button>
            </div>
            @error('call') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
        @endif
        @forelse ($deal->dealCalls as $link)
            <article class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800" wire:key="call-link-{{ $link->id }}">
                <p class="text-sm font-medium">تماس {{ $link->call?->customer_phone ?: $link->call?->caller_number }}</p>
                <p class="mt-1 text-xs text-zinc-500">منبع اتصال: {{ $link->source === 'analysis' ? 'تحلیل' : 'دستی' }}</p>
                @if (is_array($link->evidence_json))
                    <div class="mt-3 space-y-1 text-sm text-zinc-600">
                        <p class="text-xs font-medium text-indigo-700">خلاصه تحلیل، جدا از اطلاعات تأییدشده انسانی</p>
                        @if (! empty($link->evidence_json['summary']))
                            <p>{{ $link->evidence_json['summary'] }}</p>
                        @endif
                        <p>امتیاز لید: {{ $link->evidence_json['lead_score'] ?? '—' }} · قصد خرید: {{ $link->evidence_json['purchase_intent'] ?? '—' }}</p>
                    </div>
                @endif
            </article>
        @empty
            <p class="text-sm text-zinc-500">تماسی به این معامله وصل نشده است.</p>
        @endforelse
        @foreach ($suggestions as $suggestion)
            <article class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-3" wire:key="deal-suggestion-{{ $suggestion->id }}">
                <p class="text-sm font-medium">{{ $suggestion->suggestion_type->label() }} · {{ $suggestion->status->label() }}</p>
                <p class="mt-1 text-sm text-zinc-600">{{ $suggestion->payload['summary'] ?? '' }}</p>
                <div class="mt-3 flex gap-2">
                    <button type="button" class="saas-btn-primary text-sm" wire:click="acceptSuggestion({{ $suggestion->id }})">تأیید</button>
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="dismissSuggestion({{ $suggestion->id }})">رد</button>
                </div>
            </article>
        @endforeach
    </section>

    <section class="saas-card space-y-3 p-4">
        <h2 class="text-lg font-semibold">موانع فروش</h2>
        <p class="text-sm text-zinc-500">فقط از دغدغه‌های استخراج‌شده از تحلیل تماس‌های متصل ساخته می‌شود؛ نقل‌قول ساختگی ندارد.</p>
        @forelse ($deal->barriers->sortByDesc('last_seen_at') as $barrier)
            <article class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800" wire:key="barrier-{{ $barrier->id }}">
                <p class="text-sm font-medium">{{ $barrier->label }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ $barrier->status->label() }} · {{ $barrier->occurrence_count }} بار · آخرین: {{ $barrier->last_seen_at ? shamsi($barrier->last_seen_at) : '—' }}</p>
                @if ($barrier->evidence_text)
                    <p class="mt-2 text-sm text-zinc-600">شاهد: {{ $barrier->evidence_text }}</p>
                @endif
                @if ($barrier->suggested_action)
                    <p class="mt-1 text-sm text-indigo-700">اقدام پیشنهادی: {{ $barrier->suggested_action }}</p>
                @endif
                @if ($canUpdate && $barrier->status->value === 'suggested')
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="saas-btn-primary text-sm" wire:click="resolveBarrier({{ $barrier->id }}, 'confirmed')">تأیید</button>
                        <button type="button" class="saas-btn-secondary text-sm" wire:click="resolveBarrier({{ $barrier->id }}, 'dismissed')">رد</button>
                    </div>
                @endif
            </article>
        @empty
            <p class="text-sm text-zinc-500">مانعی ثبت نشده است.</p>
        @endforelse
    </section>

    <section class="saas-card p-4">
        <h2 class="text-lg font-semibold">فعالیت‌های معامله</h2>
        <p class="mt-1 text-sm text-zinc-500">پیگیری و وظایف فروش در ماژول سوم با فیلد استاندارد <code>crm_deal_id</code> به همین معامله وصل می‌شوند. سیستم وظایف موازی ساخته نشده است.</p>
        <ul class="mt-4 space-y-2 text-sm">
            @foreach ($deal->activities->sortByDesc('id') as $activity)
                <li wire:key="activity-{{ $activity->id }}">
                    {{ $activity->type instanceof DealActivityType ? $activity->type->label() : $activity->type }}
                    <span class="text-zinc-400">{{ shamsi($activity->created_at) }} · {{ $activity->actor?->name ?: 'سامانه' }} · {{ $activity->source }}</span>
                    @if (is_array($activity->previous_json) || is_array($activity->next_json))
                        <p class="mt-1 text-xs text-zinc-500">
                            قبل: {{ \Illuminate\Support\Str::limit(json_encode($activity->previous_json, JSON_UNESCAPED_UNICODE) ?: '—', 120) }}
                            · بعد: {{ \Illuminate\Support\Str::limit(json_encode($activity->next_json, JSON_UNESCAPED_UNICODE) ?: '—', 120) }}
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    @if ($deal->externalLinks->isNotEmpty())
        <section class="saas-card p-4">
            <h2 class="text-lg font-semibold">شناسه خارجی</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($deal->externalLinks as $link)
                    <li wire:key="external-{{ $link->id }}">{{ $link->external_id }} · {{ $link->sync_status }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
