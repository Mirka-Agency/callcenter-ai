@php
    $callLog = $callLog ?? null;
    $extension = $extension ?? null;
    $resolvedEmployeeId = $resolvedEmployeeId ?? null;
    $canAssignEmployee = $canAssignEmployee ?? false;
    $employees = $employees ?? collect();
    $createEmployeeUrl = $createEmployeeUrl ?? null;

    $callerNumber = $callLog?->source_number ?? $analysis->call?->caller_number ?? null;
    $receiverNumber = $callLog?->destination_number ?? $analysis->call?->receiver_number ?? null;
    $hasVoipData = $callLog !== null;
    $needsAssignment = ! $analysis->employee;
@endphp

@if ($hasVoipData || $callerNumber || $needsAssignment)
<div class="saas-card space-y-4">
    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">اطلاعات تماس VoIP</h2>

    <dl class="space-y-3 text-sm">
        @if ($callerNumber)
            <div class="flex items-start justify-between gap-3">
                <dt class="text-zinc-500">شماره تماس‌گیرنده</dt>
                <dd class="text-end font-medium tabular-nums text-zinc-900 dark:text-white" dir="ltr">{{ $callerNumber }}</dd>
            </div>
        @endif

        @if ($receiverNumber)
            <div class="flex items-start justify-between gap-3">
                <dt class="text-zinc-500">شماره مقصد</dt>
                <dd class="text-end font-medium tabular-nums text-zinc-900 dark:text-white" dir="ltr">{{ $receiverNumber }}</dd>
            </div>
        @endif

        <div class="flex items-start justify-between gap-3">
            <dt class="text-zinc-500">داخلی</dt>
            <dd class="text-end">
                @if ($extension)
                    <code class="rounded bg-zinc-100 px-2 py-0.5 text-sm font-mono dark:bg-zinc-800">{{ $extension }}</code>
                @else
                    <span class="text-zinc-400">—</span>
                @endif
            </dd>
        </div>

        <div class="flex items-start justify-between gap-3 border-t border-zinc-200/80 pt-3 dark:border-zinc-800">
            <dt class="text-zinc-500">کارشناس</dt>
            <dd class="text-end">
                @if ($analysis->employee)
                    <div class="space-y-1">
                        <x-saas.user-cell
                            :employee="$analysis->employee"
                            avatar-size="xs"
                            class="justify-end"
                        />
                        @if ($extension)
                            <p class="text-xs text-zinc-500" dir="ltr">داخلی {{ $extension }}</p>
                        @endif
                    </div>
                @else
                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                        بدون کارشناس
                    </span>
                @endif
            </dd>
        </div>
    </dl>

    @if ($needsAssignment && $canAssignEmployee)
        <div class="border-t border-zinc-200/80 pt-4 dark:border-zinc-800 space-y-3">
            <p class="text-xs text-zinc-500">
                @if ($extension)
                    این تماس کارشناس ندارد. کارشناس را انتخاب کنید تا به همین تماس وصل شود و داخلی <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">{{ $extension }}</code> برای تماس‌های بعدی هم نگاشت شود.
                @else
                    این تماس کارشناس ندارد. یک کارشناس انتخاب کنید تا به همین تحلیل وصل شود.
                @endif
            </p>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <select
                    wire:model="assignEmployeeId"
                    class="saas-input flex-1 min-w-0"
                >
                    <option value="0">انتخاب کارشناس…</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name ?: ('کارشناس #'.$emp->id) }}</option>
                    @endforeach
                </select>

                <button
                    type="button"
                    wire:click="assignCallEmployee"
                    wire:loading.attr="disabled"
                    wire:target="assignCallEmployee"
                    class="saas-btn-primary whitespace-nowrap text-sm shrink-0"
                >
                    <span wire:loading.remove wire:target="assignCallEmployee">تخصیص کارشناس</span>
                    <span wire:loading wire:target="assignCallEmployee">در حال تخصیص…</span>
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
