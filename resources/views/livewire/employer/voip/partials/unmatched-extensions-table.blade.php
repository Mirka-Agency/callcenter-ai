@php
    $employees = $employees ?? collect();
    $hasEmployees = $employees->isNotEmpty();
@endphp

@if ($unmatchedExtensions === [])
    <div class="saas-card">
        <x-saas.empty-state
            title="{{ __('ui.empty.unmatched_extensions.title') }}"
            description="{{ __('ui.empty.unmatched_extensions.description') }}"
            :action="route('employer.voip.index')"
            action-label="{{ __('ui.voip.unmatched_extensions_empty_action') }}"
        />
    </div>
@else
    @if (! $hasEmployees)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
            <p>{{ __('ui.voip.unmatched_no_employees') }}</p>
            <a href="{{ route('employer.employees.create') }}" class="mt-2 inline-flex font-medium text-amber-800 underline dark:text-amber-200">
                {{ __('ui.voip.unmatched_add_employee') }}
            </a>
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($unmatchedExtensions as $row)
            @php
                $selectionKey = $row['extension'].'__'.$row['connection_id'];
                $customerNumber = $row['last_customer_number'] ?? null;
                $lastDirection = $row['last_direction'] ?? null;
            @endphp
            <article wire:key="unmatched-{{ $selectionKey }}" class="saas-card space-y-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-lg font-bold tabular-nums text-amber-800 dark:bg-amber-950/40 dark:text-amber-200" dir="ltr">
                        {{ $row['extension'] }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                                    {{ __('ui.voip.unmatched_record_title', ['extension' => $row['extension']]) }}
                                </h2>
                                <p class="mt-1 text-sm text-zinc-500">
                                    {{ trans_choice('ui.voip.unmatched_record_subtitle', $row['call_count'], ['count' => $row['call_count']]) }}
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                {{ __('ui.voip.recent_calls_no_employee_badge') }}
                            </span>
                        </div>

                        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs text-zinc-500">{{ __('ui.voip.unmatched_last_call_column') }}</dt>
                                <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                                    @if ($customerNumber && $lastDirection === 'inbound')
                                        {{ __('ui.voip.unmatched_last_call_inbound') }}
                                        <span dir="ltr" class="inline-block tabular-nums">{{ $customerNumber }}</span>
                                    @elseif ($customerNumber && $lastDirection === 'outbound')
                                        {{ __('ui.voip.unmatched_last_call_outbound') }}
                                        <span dir="ltr" class="inline-block tabular-nums">{{ $customerNumber }}</span>
                                    @else
                                        {{ __('ui.voip.unmatched_last_call_generic') }}
                                    @endif
                                    <span class="mt-0.5 block text-xs font-normal text-zinc-500">
                                        {{ $row['last_call_at'] ? shamsi($row['last_call_at'], 'ago') : '—' }}
                                        @if ($row['last_call_at'])
                                            <span class="text-zinc-400">·</span>
                                            {{ shamsi($row['last_call_at'], 'datetime') }}
                                        @endif
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500">{{ __('ui.voip.unmatched_call_count_column') }}</dt>
                                <dd class="mt-1 text-sm font-medium tabular-nums text-zinc-900 dark:text-white">
                                    {{ number_format($row['call_count']) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500">{{ __('ui.voip.unmatched_connection_column') }}</dt>
                                <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $row['connection_name'] }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="border-t border-zinc-200/80 pt-4 dark:border-zinc-800">
                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('ui.voip.unmatched_assign_prompt') }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('ui.voip.unmatched_assign_help') }}</p>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                        <div class="min-w-0 flex-1">
                            <select
                                wire:model="unmatchedSelections.{{ $selectionKey }}"
                                @disabled(! $hasEmployees)
                                class="saas-input w-full"
                            >
                                <option value="">{{ __('ui.voip.recent_calls_select_employee') }}</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                            @error('unmatchedSelections.'.$selectionKey)
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button
                            type="button"
                            wire:click="assignUnmatchedExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="assignUnmatchedExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})"
                            @disabled(! $hasEmployees)
                            class="saas-btn-primary whitespace-nowrap text-sm"
                        >
                            <span wire:loading.remove wire:target="assignUnmatchedExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})">
                                {{ __('ui.voip.unmatched_assign_button') }}
                            </span>
                            <span wire:loading wire:target="assignUnmatchedExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})">
                                {{ __('ui.voip.unmatched_assigning') }}
                            </span>
                        </button>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif
