<div class="w-full sm:w-48">
    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">مرتب‌سازی</label>
    <select wire:model.live="sort" class="saas-input mt-1 text-sm">
        @foreach (\App\Support\CustomerListSort::cases() as $option)
            <option value="{{ $option->value }}">{{ $option->label() }}</option>
        @endforeach
    </select>
</div>
