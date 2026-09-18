<div>
    @if ($showPopup && $incomingCall)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-16 backdrop-blur-sm" wire:transition>
            <div class="w-full max-w-md rounded-lg border border-indigo-200 bg-white shadow-2xl dark:border-indigo-900 dark:bg-zinc-900">
                <div class="rounded-t-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-4 text-white">
                    <p class="text-sm font-medium uppercase tracking-wider opacity-80">تماس ورودی</p>
                    <p class="mt-1 text-2xl font-bold">{{ $incomingCall['caller_number'] ?? 'نامشخص' }}</p>
                    <p class="mt-1 text-xs opacity-75">شناسه تماس‌گیرنده از سیستم تلفنی</p>
                </div>
                <div class="space-y-3 px-6 py-5">
                    @if ($incomingCall['customer_name'] ?? null)
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500">مشتری</span>
                            <span class="font-medium">{{ $incomingCall['customer_name'] }}</span>
                        </div>
                    @else
                        <div class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            نام این تماس‌گیرنده هنوز شناسایی نشده است.
                            @unless ($hasCrm)
                                CRM را متصل کنید تا شماره با شخص موجود مطابقت داده شود.
                            @else
                                CRM متصل است — نام پس از تطبیق شماره با شخص نمایش داده می‌شود.
                            @endunless
                        </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">شرکت</span>
                        <span class="font-medium">{{ $incomingCall['organization_name'] ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">زمان تماس</span>
                        <span class="font-medium">{{ now()->format('H:i') }}</span>
                    </div>
                </div>
                <div class="flex gap-3 border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    <button wire:click="acceptCall" class="saas-btn flex-1 justify-center bg-emerald-600 text-white hover:bg-emerald-700">
                        پذیرش تماس
                    </button>
                    <button wire:click="dismissPopup" class="saas-btn-secondary">رد کردن</button>
                </div>
            </div>
        </div>
    @endif

    @if ($claimError)
        <div class="fixed bottom-4 end-4 z-50 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-lg">
            {{ $claimError }}
        </div>
    @endif

    @if ($showPanel && $intelligence)
        <div class="fixed inset-y-0 end-0 z-40 w-full max-w-lg overflow-y-auto border-s border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-950">
            <div class="sticky top-0 border-b border-zinc-100 bg-white/95 px-6 py-4 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">هوش مشتری</h2>
                    <button wire:click="closePanel" class="text-zinc-400 hover:text-zinc-600">&times;</button>
                </div>
            </div>

            <div class="space-y-6 p-6">
                <div class="rounded-lg bg-indigo-50 p-4 dark:bg-indigo-950/40">
                    <p class="text-sm text-indigo-600 dark:text-indigo-300">مشتری</p>
                    <p class="text-xl font-semibold">{{ $intelligence['customer_name'] ?? 'نامشخص' }}</p>
                    <p class="mt-1 text-sm text-zinc-500">{{ $intelligence['customer_phone'] }}</p>
                    @if ($intelligence['last_contact_date'] ?? null)
                        <p class="mt-2 text-xs text-zinc-400">آخرین تماس: {{ $intelligence['last_contact_date'] }}</p>
                    @endif
                </div>

                @if ($intelligence['context_summary'] ?? null)
                    <div>
                        <h3 class="font-semibold">خلاصه زمینه</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $intelligence['context_summary'] }}</p>
                    </div>
                @endif

                @if (! empty($intelligence['recommended_actions']))
                    <div>
                        <h3 class="font-semibold">اقدامات پیشنهادی</h3>
                        <ol class="mt-3 list-decimal space-y-2 ps-5 text-sm text-zinc-600 dark:text-zinc-300">
                            @foreach ($intelligence['recommended_actions'] as $action)
                                <li>{{ $action }}</li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                @if (! empty($intelligence['recent_actions']))
                    <div>
                        <h3 class="font-semibold">تاریخچه اقدامات اخیر</h3>
                        <ul class="mt-3 space-y-2">
                            @foreach ($intelligence['recent_actions'] as $item)
                                <li class="flex items-start gap-2 text-sm">
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                                    <span>{{ $item['action'] }} @if($item['date'] ?? null)<span class="text-zinc-400">· {{ $item['date'] }}</span>@endif</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (! empty($intelligence['timeline']))
                    <div>
                        <h3 class="font-semibold">خط زمانی مشتری</h3>
                        <div class="mt-3 space-y-3">
                            @foreach ($intelligence['timeline'] as $event)
                                <div class="flex gap-3 text-sm">
                                    <span class="shrink-0 rounded bg-zinc-100 px-2 py-0.5 text-xs uppercase text-zinc-500 dark:bg-zinc-800">{{ $event['type'] }}</span>
                                    <div>
                                        <p>{{ $event['title'] }}</p>
                                        @if ($event['date'] ?? null)
                                            <p class="text-xs text-zinc-400">{{ $event['date'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! $hasCrm || ! $hasVoip)
                    <div class="space-y-2 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                        <p class="font-medium text-zinc-700 dark:text-zinc-300">یکپارچه‌سازی برای بینش بهتر</p>
                        @unless ($hasVoip)
                            <p><strong>تلفن:</strong> سیستم تلفنی را متصل کنید تا تماس‌های ورودی و پاپ‌آپ زنده به‌صورت خودکار فعال شوند.</p>
                        @endunless
                        @unless ($hasCrm)
                            <p><strong>CRM:</strong> CRM را متصل کنید تا نام تماس‌گیرنده شناسایی شود و تاریخچه مشتری بارگذاری گردد.</p>
                        @endunless
                        <p><strong>شناسایی تماس‌گیرنده:</strong> شماره از تماس ورودی نمایش داده می‌شود و CRM در صورت تطبیق، آن را با نام شخص تکمیل می‌کند.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

@script
<script>
    const orgId = @js($organizationId);

    if (window.Echo && orgId) {
        window.Echo.private(`organization.${orgId}.incoming-calls`)
            .listen('.IncomingCallReceived', (e) => {
                Livewire.dispatch('incoming-call-received', e);
            })
            .listen('.IncomingCallClaimed', (e) => {
                Livewire.dispatch('incoming-call-claimed', e);
            });
    }
</script>
@endscript
