@if (\App\Services\EmployerIntegrationGate::allowsFullManagement())
    <div class="border-t border-zinc-200 pt-6 dark:border-zinc-800 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">تخصیص یکپارچه‌سازی</h2>
                <p class="mt-1 text-sm text-zinc-500">اتصال CRM/VoIP و شناسه‌های ارائه‌دهنده (مثل شماره داخلی) را برای این کارشناس تنظیم کنید.</p>
            </div>
            <button type="button" wire:click="addIntegrationAssignment" class="saas-btn-secondary text-sm">افزودن اتصال</button>
        </div>

        @foreach ($integration_assignments as $index => $assignment)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800 space-y-3" wire:key="integration-assignment-{{ $index }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium">اتصال</label>
                        <select wire:model.live="integration_assignments.{{ $index }}.connection" class="saas-input">
                            <option value="">انتخاب اتصال…</option>
                            @foreach ($this->integrationConnectionOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error("integration_assignments.{$index}.connection") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @if (count($integration_assignments) > 1)
                        <button type="button" wire:click="removeIntegrationAssignment({{ $index }})" class="saas-btn-secondary text-sm text-red-600 mt-6">حذف</button>
                    @endif
                </div>

                @foreach ($this->metaFieldsForAssignment($index) as $field)
                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            {{ $field['name'] }}
                            @if ($field['required']) <span class="text-red-500">*</span> @endif
                        </label>
                        <input
                            wire:model="integration_assignments.{{ $index }}.meta.{{ $field['key'] }}"
                            type="{{ $field['type'] === 'password' ? 'password' : 'text' }}"
                            class="saas-input"
                            @if ($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endif
                        >
                        @error("integration_assignments.{$index}.meta.{$field['key']}") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
