<div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">مدیریت اتصالات CRM</h1>
            <p class="mt-2 text-zinc-500">اتصال‌های CRM سازمان خود را بسازید، ویرایش و تست کنید.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('employer.crm.index') }}" class="saas-btn-secondary">بازگشت به CRM</a>
            <a href="{{ route('employer.crm.connections.create') }}" class="saas-btn-primary">اتصال جدید</a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4">
        @forelse ($connections as $connection)
            <div class="saas-card flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="font-semibold">{{ $connection->name }}</h3>
                    <p class="text-sm text-zinc-500">{{ $connection->provider->name }}</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        @if ($connection->is_default)
                            <span class="saas-badge">پیش‌فرض</span>
                        @endif
                        <span class="saas-badge {{ $connection->is_active ? 'saas-badge-success' : '' }}">
                            {{ $connection->is_active ? 'فعال' : 'غیرفعال' }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('employer.crm.connections.edit', $connection) }}" class="saas-btn-secondary text-sm">ویرایش</a>
                    <button type="button" wire:click="test({{ $connection->id }})" class="saas-btn-secondary text-sm">تست</button>
                    <button type="button" wire:click="sync({{ $connection->id }})" class="saas-btn-secondary text-sm">همگام‌سازی</button>
                    <button type="button" wire:click="delete({{ $connection->id }})" wire:confirm="این اتصال حذف شود؟" class="saas-btn-secondary text-sm text-red-600">حذف</button>
                </div>
            </div>
        @empty
            <div class="saas-card text-sm text-zinc-500">هنوز اتصال CRM ثبت نشده است.</div>
        @endforelse
    </div>
</div>
