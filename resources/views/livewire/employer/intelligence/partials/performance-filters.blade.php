<div class="saas-card sticky top-0 z-10 space-y-4 shadow-sm">
    <div class="flex flex-wrap gap-2">
        @foreach ($presets as $preset)
            <button
                type="button"
                wire:click="$set('datePreset', '{{ $preset->value }}')"
                @class([
                    'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                    'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $datePreset === $preset->value,
                    'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' => $datePreset !== $preset->value,
                ])
            >{{ $preset->label() }}</button>
        @endforeach
    </div>

    @if ($datePreset === 'custom')
        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm text-zinc-500">از</label>
            <x-saas.jalali-date-input wire:key="performance-custom-from" wire:model.live="customFrom" class="text-sm" />
            <label class="text-sm text-zinc-500">تا</label>
            <x-saas.jalali-date-input wire:key="performance-custom-to" wire:model.live="customTo" class="text-sm" />
        </div>
    @endif

    @if (isset($filterEmployees) && $filterEmployees->isNotEmpty())
        <div class="space-y-2">
            <span class="text-sm font-medium text-zinc-500">مشاهده پروفایل کارشناس</span>
            <div class="flex flex-wrap items-center gap-2">
                <x-saas.agent-chip
                    name="همه"
                    wire:click="clearEmployeeFilter"
                    :active="$selectedEmployeeIds === []"
                />
                @foreach ($filterEmployees as $employee)
                    <x-saas.agent-chip
                        :employee="$employee"
                        :href="route('employer.intelligence.performance.show', $employee->id).'?preset='.$datePreset.'&from='.$customFrom.'&to='.$customTo"
                    />
                @endforeach
            </div>
        </div>
    @endif
</div>
