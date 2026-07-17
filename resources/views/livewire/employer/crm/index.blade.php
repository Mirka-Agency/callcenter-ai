<div class="space-y-8">
    <div data-tour="crm-header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">یکپارچه‌سازی CRM</h1>
                <p class="mt-2 text-zinc-500">کاریز، مرحله کاریز و مالک معامله پیش‌فرض را تنظیم کنید تا معاملات دیدار از روی تحلیل تماس ساخته شوند.</p>
            </div>
            @if (\App\Services\EmployerIntegrationGate::allowsFullManagement())
                <a href="{{ route('employer.crm.connections.index') }}" class="saas-btn-primary">مدیریت اتصالات CRM</a>
            @endif
        </div>
    </div>

    @unless ($isComplete)
        @include('livewire.shared.integration-setup-pending', [
            'title' => 'CRM برای این سازمان فعال نیست',
            'description' => 'فقط ادمین می‌تواند CRM را برای سازمان فعال یا غیرفعال کند. پس از فعال‌سازی و تأیید اتصال توسط ادمین، می‌توانید کاریز و مرحله معامله را اینجا تنظیم کنید.',
        ])
    @else
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-900/50 dark:bg-indigo-950/40 dark:text-indigo-100">
            <p class="font-medium">تنظیم مسیر فروش در دیدار</p>
            <p class="mt-1 text-indigo-800/90 dark:text-indigo-200/90">
                فعال یا غیرفعال کردن CRM فقط توسط ادمین انجام می‌شود. شما کاریز، مرحله کاریز و در صورت نیاز مالک معامله را برای معاملات ساخته‌شده از تحلیل تماس تنظیم می‌کنید.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2" data-tour="crm-connections">
            @foreach ($this->connections as $connection)
                <div class="saas-card">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">{{ $connection->name }}</h3>
                            <p class="text-sm text-zinc-500">{{ $connection->provider->name }}</p>
                            @if ($connection->is_default)
                                <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-300">اتصال پیش‌فرض</p>
                            @endif
                        </div>
                        <span class="saas-badge saas-badge-success">فعال توسط ادمین</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="saas-card space-y-6" data-tour="crm-settings">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">تنظیمات معامله دیدار</h2>
                    <p class="mt-1 text-sm text-zinc-500">این مقادیر هنگام ایجاد معامله از تحلیل تماس استفاده می‌شوند.</p>
                </div>
                <button type="button" wire:click="refreshOptions" class="saas-btn-secondary text-sm" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="refreshOptions">بروزرسانی از دیدار</span>
                    <span wire:loading wire:target="refreshOptions">در حال دریافت…</span>
                </button>
            </div>

            @if ($optionsError)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ $optionsError }}
                </div>
            @endif

            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                @if ($this->connections->count() > 1)
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">اتصال CRM</label>
                        <select wire:model.live="selectedConnectionId" class="saas-input">
                            @foreach ($this->connections as $connection)
                                <option value="{{ $connection->id }}">{{ $connection->name }} ({{ $connection->provider->name }})</option>
                            @endforeach
                        </select>
                        @error('selectedConnectionId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium">کاریز <span class="text-red-500">*</span></label>
                    <select wire:model.live="pipelineId" class="saas-input" required>
                        <option value="">انتخاب کاریز…</option>
                        @foreach ($pipelines as $pipeline)
                            <option value="{{ $pipeline['id'] }}">{{ $pipeline['title'] }}</option>
                        @endforeach
                    </select>
                    @error('pipelineId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">مرحله کاریز <span class="text-red-500">*</span></label>
                    <select wire:model="pipelineStageId" class="saas-input" @disabled($pipelineId === '') required>
                        <option value="">انتخاب مرحله…</option>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage['id'] }}">{{ $stage['title'] }}</option>
                        @endforeach
                    </select>
                    @error('pipelineStageId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">مالک معامله <span class="text-zinc-400">(اختیاری)</span></label>
                    <select wire:model="dealOwnerId" class="saas-input">
                        <option value="">بدون مالک مشخص (پیش‌فرض دیدار)</option>
                        @foreach ($users as $user)
                            <option value="{{ $user['id'] }}">
                                {{ $user['name'] }}@if (! empty($user['email'])) — {{ $user['email'] }}@endif
                            </option>
                        @endforeach
                    </select>
                    @error('dealOwnerId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="saas-btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">ذخیره تنظیمات</span>
                        <span wire:loading wire:target="save">در حال ذخیره…</span>
                    </button>
                </div>
            </form>
        </div>
    @endunless
</div>
