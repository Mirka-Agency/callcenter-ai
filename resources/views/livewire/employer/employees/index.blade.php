<div class="saas-page">
    <x-saas.page-header data-tour="page-header" title="کارشناسان" description="مدیریت تیم تماس، دسترسی‌ها و پیگیری عملکرد کارشناسان.">
        <x-slot:actions>
            <a href="{{ route('employer.employees.create') }}" class="saas-btn-primary">@lang('ui.cta.add_employee')</a>
        </x-slot:actions>
    </x-saas.page-header>

    <div class="saas-card" data-tour="employees-search">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="جستجوی کارشناسان..." class="saas-input max-w-md">
    </div>

    <div class="saas-card overflow-hidden !p-0" data-tour="employees-table">
        <table class="saas-table">
            <thead>
                <tr>
                    <th>کارشناس</th>
                    <th>ایمیل</th>
                    <th>بخش</th>
                    <th>تحلیل‌ها</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td>
                            <x-saas.user-cell
                                :employee="$employee"
                                :subtitle="$employee->position ?? $employee->department"
                            />
                        </td>
                        <td class="text-zinc-500">{{ $employee->user?->email }}</td>
                        <td>{{ $employee->department ?? '—' }}</td>
                        <td>{{ $employee->conversation_analyses_count }}</td>
                        <td>
                            <span @class(['saas-badge-success' => $employee->is_active, 'saas-badge-danger' => ! $employee->is_active, 'saas-badge'])>
                                {{ $employee->is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('employer.intelligence.performance.show', $employee) }}" class="saas-btn-primary text-sm">عملکرد</a>
                                <a href="{{ route('employer.employees.edit', $employee) }}" class="saas-btn-secondary text-sm">ویرایش</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="!py-12">
                            <x-saas.empty-state
                                title="{{ __('ui.empty.no_employees.title') }}"
                                description="{{ __('ui.empty.no_employees.description') }}"
                                :action="route('employer.employees.create')"
                                :actionLabel="__('ui.cta.add_employee')"
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $employees->links() }}
</div>
