<div class="saas-page space-y-6">
    <x-saas.page-header
        data-tour="page-header"
        title="{{ __('ui.voip.extensions_title') }}"
        description="{{ __('ui.voip.extensions_page_hint') }}"
    >
        <x-slot:actions>
            <a href="{{ route('employer.employees.create') }}" class="saas-btn-secondary">
                {{ __('ui.voip.unmatched_add_employee') }}
            </a>
            <button
                type="button"
                wire:click="toggleAddForm"
                data-tour="extensions-add-button"
                @class([
                    'text-sm',
                    'saas-btn-primary' => ! $showAddForm,
                    'saas-btn-secondary' => $showAddForm,
                ])
            >
                {{ $showAddForm ? __('ui.voip.extensions_add_cancel') : __('ui.voip.extensions_add') }}
            </button>
        </x-slot:actions>
    </x-saas.page-header>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    @if ($showAddForm)
    <section class="saas-card space-y-4" data-tour="extensions-add">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('ui.voip.extensions_add') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('ui.voip.extensions_add_hint') }}</p>
        </div>

        @if ($connections->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                <p>{{ __('ui.voip.extensions_no_connections') }}</p>
                <a href="{{ route('employer.voip.index') }}" class="mt-2 inline-flex font-medium text-amber-800 underline dark:text-amber-200">
                    {{ __('ui.voip.extensions_open_voip') }}
                </a>
            </div>
        @elseif ($employees->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                <p>{{ __('ui.voip.unmatched_no_employees') }}</p>
                <a href="{{ route('employer.employees.create') }}" class="mt-2 inline-flex font-medium text-amber-800 underline dark:text-amber-200">
                    {{ __('ui.voip.unmatched_add_employee') }}
                </a>
            </div>
        @else
            <form wire:submit="addExtension" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('ui.voip.extensions_number_label') }}</label>
                    <input
                        wire:model="newExtension"
                        type="text"
                        inputmode="numeric"
                        dir="ltr"
                        class="saas-input"
                        placeholder="{{ __('ui.voip.extensions_number_placeholder') }}"
                    >
                    @error('newExtension')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('ui.voip.extensions_connection_label') }}</label>
                    @if ($connections->count() === 1)
                        <input type="hidden" wire:model="newConnectionId">
                        <p class="saas-input bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">{{ $connections->first()->name }}</p>
                    @else
                        <select wire:model="newConnectionId" class="saas-input">
                            <option value="">{{ __('ui.voip.extensions_connection_placeholder') }}</option>
                            @foreach ($connections as $connection)
                                <option value="{{ $connection->id }}">{{ $connection->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('newConnectionId')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('ui.voip.extensions_employee_label') }}</label>
                    <select wire:model="newEmployeeId" class="saas-input">
                        <option value="0">{{ __('ui.voip.recent_calls_select_employee') }}</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                    @error('newEmployeeId')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button type="submit" class="saas-btn-primary w-full" wire:loading.attr="disabled" wire:target="addExtension">
                        <span wire:loading.remove wire:target="addExtension">{{ __('ui.voip.extensions_add_button') }}</span>
                        <span wire:loading wire:target="addExtension">{{ __('ui.voip.extensions_adding') }}</span>
                    </button>
                </div>
            </form>
        @endif
    </section>
    @endif

    <div data-tour="extensions-list">
        @if ($extensions === [])
            <div class="saas-card">
                <x-saas.empty-state
                    title="{{ __('ui.empty.extensions.title') }}"
                    description="{{ __('ui.empty.extensions.description') }}"
                >
                    @unless ($showAddForm)
                        <button
                            type="button"
                            wire:click="toggleAddForm"
                            class="saas-btn-primary mt-6"
                        >
                            {{ __('ui.voip.extensions_add') }}
                        </button>
                    @endunless
                </x-saas.empty-state>
            </div>
        @else
            <div class="saas-card overflow-x-auto">
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.voip.extensions_number_label') }}</th>
                            <th>{{ __('ui.voip.extensions_employee_label') }}</th>
                            <th>{{ __('ui.voip.extensions_connection_label') }}</th>
                            <th class="text-end">{{ __('ui.voip.extensions_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($extensions as $row)
                            @php
                                $selectionKey = $row['extension'].'__'.$row['connection_id'];
                            @endphp
                            <tr wire:key="extension-{{ $selectionKey }}">
                                <td>
                                    <code class="rounded bg-zinc-100 px-2 py-0.5 text-sm font-semibold tabular-nums dark:bg-zinc-800" dir="ltr">{{ $row['extension'] }}</code>
                                </td>
                                <td>
                                    <div class="min-w-[12rem]">
                                        <select
                                            wire:model="employeeSelections.{{ $selectionKey }}"
                                            class="saas-input"
                                            @disabled($employees->isEmpty())
                                        >
                                            <option value="">{{ __('ui.voip.recent_calls_select_employee') }}</option>
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                                            @endforeach
                                            @if (! $employees->contains('id', $row['employee_id']))
                                                <option value="{{ $row['employee_id'] }}">{{ $row['employee_name'] }}</option>
                                            @endif
                                        </select>
                                        @error('employeeSelections.'.$selectionKey)
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </td>
                                <td class="text-sm text-zinc-700 dark:text-zinc-300">{{ $row['connection_name'] }}</td>
                                <td>
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="updateEmployee('{{ $row['extension'] }}', {{ $row['connection_id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="updateEmployee('{{ $row['extension'] }}', {{ $row['connection_id'] }})"
                                            class="saas-btn-secondary text-sm"
                                            @disabled($employees->isEmpty())
                                        >
                                            <span wire:loading.remove wire:target="updateEmployee('{{ $row['extension'] }}', {{ $row['connection_id'] }})">
                                                {{ __('ui.voip.extensions_save_employee') }}
                                            </span>
                                            <span wire:loading wire:target="updateEmployee('{{ $row['extension'] }}', {{ $row['connection_id'] }})">
                                                {{ __('ui.voip.extensions_saving') }}
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="deleteExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})"
                                            wire:confirm="{{ __('ui.voip.extensions_delete_confirm') }}"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})"
                                            class="saas-btn-secondary text-sm text-red-600"
                                        >
                                            {{ __('ui.voip.extensions_delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
