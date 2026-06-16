<div x-show="commandOpen" x-transition class="saas-command-palette" @click.self="commandOpen = false">
    <div class="saas-command-panel">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <input type="text" placeholder="جستجوی صفحات..." class="saas-input !border-0 !bg-transparent !px-0 !shadow-none focus:!ring-0" autofocus>
        </div>
        <div class="max-h-80 overflow-y-auto p-2">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" @click="commandOpen = false">
                    <x-saas.icon :name="$item['icon']" class="h-4 w-4 text-zinc-400" />
                    {{ $item['label'] }}
                </a>
                @foreach ($item['children'] ?? [] as $child)
                    <a href="{{ route($child['route'], $child['query'] ?? []) }}#sample-conversations" class="flex items-center gap-3 rounded-md py-2 pe-3 ps-9 text-xs text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800" @click="commandOpen = false">
                        {{ $child['label'] }}
                    </a>
                @endforeach
            @endforeach
        </div>
    </div>
</div>
