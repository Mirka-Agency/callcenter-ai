@php
    $employees = $employees ?? collect();
@endphp

@if ($unmatchedExtensions === [])
    <p class="text-sm text-zinc-500">{{ __('ui.voip.unmatched_extensions_empty') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="saas-table">
            <thead>
                <tr>
                    <th>{{ __('ui.voip.unmatched_extension_column') }}</th>
                    <th>{{ __('ui.voip.unmatched_route_column') }}</th>
                    <th>{{ __('ui.voip.unmatched_connection_column') }}</th>
                    <th>{{ __('ui.voip.unmatched_call_count_column') }}</th>
                    <th>{{ __('ui.voip.unmatched_last_call_column') }}</th>
                    <th>{{ __('ui.voip.unmatched_employee_column') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($unmatchedExtensions as $row)
                    @php($selectionKey = $row['extension'].'__'.$row['connection_id'])
                    <tr wire:key="unmatched-{{ $selectionKey }}">
                        <td>
                            <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-sm dark:bg-zinc-800">{{ $row['extension'] }}</code>
                        </td>
                        <td class="text-sm tabular-nums" dir="ltr">
                            {{ $row['last_source_number'] ?: '—' }}
                            →
                            {{ $row['last_destination_number'] ?: $row['extension'] }}
                        </td>
                        <td>{{ $row['connection_name'] }}</td>
                        <td>{{ $row['call_count'] }}</td>
                        <td>{{ $row['last_call_at'] ? shamsi($row['last_call_at'], 'datetime') : '—' }}</td>
                        <td>
                            <select
                                wire:model="unmatchedSelections.{{ $selectionKey }}"
                                class="saas-input w-full min-w-[10rem]"
                            >
                                <option value="">{{ __('ui.voip.recent_calls_select_employee') }}</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                            @error('unmatchedSelections.'.$selectionKey)
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </td>
                        <td>
                            <button
                                type="button"
                                wire:click="assignUnmatchedExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="assignUnmatchedExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})"
                                class="saas-btn-secondary whitespace-nowrap text-sm"
                            >
                                <span wire:loading.remove wire:target="assignUnmatchedExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})">
                                    {{ __('ui.voip.unmatched_assign_button') }}
                                </span>
                                <span wire:loading wire:target="assignUnmatchedExtension('{{ $row['extension'] }}', {{ $row['connection_id'] }})">
                                    {{ __('ui.voip.unmatched_assigning') }}
                                </span>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
