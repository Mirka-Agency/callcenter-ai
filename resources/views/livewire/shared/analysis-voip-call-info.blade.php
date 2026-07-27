@php
    $callLog = $callLog ?? null;
    $extension = $extension ?? null;
    $resolvedEmployeeId = $resolvedEmployeeId ?? null;
    $canManageIntegrations = $canManageIntegrations ?? false;
    $employees = $employees ?? collect();
    $createEmployeeUrl = $createEmployeeUrl ?? null;

    $callerNumber = $callLog?->source_number ?? $analysis->call?->caller_number ?? null;
    $receiverNumber = $callLog?->destination_number ?? $analysis->call?->receiver_number ?? null;
    $hasVoipData = $callLog !== null;
@endphp

@if ($hasVoipData || $callerNumber)
<div class="saas-card space-y-4">
    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">اطلاعات تماس VoIP</h2>

    <dl class="space-y-3 text-sm">
        @if ($callerNumber)
            <div class="flex items-start justify-between gap-3">
                <dt class="text-zinc-500">شماره تماس‌گیرنده</dt>
                <dd class="text-end font-medium tabular-nums text-zinc-900 dark:text-white dir-ltr">{{ $callerNumber }}</dd>
            </div>
        @endif

        @if ($receiverNumber)
            <div class="flex items-start justify-between gap-3">
                <dt class="text-zinc-500">شماره مقصد</dt>
                <dd class="text-end font-medium tabular-nums text-zinc-900 dark:text-white dir-ltr">{{ $receiverNumber }}</dd>
            </div>
        @endif

        @if ($extension)
            <div class="flex items-start justify-between gap-3">
                <dt class="text-zinc-500">داخلی</dt>
                <dd class="text-end">
                    <code class="rounded bg-zinc-100 px-2 py-0.5 text-sm font-mono dark:bg-zinc-800">{{ $extension }}</code>
                </dd>
            </div>
        @endif

        @if ($extension)
            <div class="flex items-start justify-between gap-3 border-t border-zinc-200/80 pt-3 dark:border-zinc-800">
                <dt class="text-zinc-500">کارشناس داخلی</dt>
                <dd class="text-end">
                    @if ($analysis->employee)
                        <x-saas.user-cell
                            :employee="$analysis->employee"
                            avatar-size="xs"
                            class="justify-end"
                        />
                    @elseif ($resolvedEmployeeId)
                        {{-- employee recently assigned, not yet loaded --}}
                        <span class="text-emerald-600 dark:text-emerald-400 text-xs font-medium">اختصاص یافت</span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            بدون کارشناس
                        </span>
                    @endif
                </dd>
            </div>
        @endif
    </dl>

    {{-- Assign section: only shown when extension exists but no employee and management is allowed --}}
    @if ($extension && ! $analysis->employee && ! $resolvedEmployeeId && $canManageIntegrations)
        <div class="border-t border-zinc-200/80 pt-4 dark:border-zinc-800 space-y-3">
            <p class="text-xs text-zinc-500">این داخلی هنوز به کارشناسی نسبت داده نشده. یک کارشناس موجود انتخاب کنید یا کارشناس جدید بسازید:</p>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <select
                    wire:model="assignEmployeeId"
                    class="saas-input flex-1 min-w-0"
                >
                    <option value="0">انتخاب کارشناس…</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                    @endforeach
                </select>

                <button
                    type="button"
                    wire:click="assignCallEmployee"
                    wire:loading.attr="disabled"
                    wire:target="assignCallEmployee"
                    class="saas-btn-primary whitespace-nowrap text-sm shrink-0"
                >
                    <span wire:loading.remove wire:target="assignCallEmployee">اختصاص داخلی</span>
                    <span wire:loading wire:target="assignCallEmployee">در حال اختصاص…</span>
                </button>
            </div>

            @error('assignEmployeeId')
                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @if ($createEmployeeUrl)
                <a
                    href="{{ $createEmployeeUrl }}"
                    class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                    </svg>
                    یا کارشناس جدید بسازید
                </a>
            @endif
        </div>
    @endif
</div>
@endif
