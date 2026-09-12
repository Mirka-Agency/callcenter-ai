@php
    use App\Support\AgentPerformancePresenter;
    use Illuminate\Support\Js;
@endphp

@if (! empty($teamWeaknesses))
    <div class="saas-card">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2 class="text-lg font-semibold">ضعف‌های پرتکرار تیم</h2>
            @if ($selectedTeamWeakness)
                <button type="button" wire:click="clearTeamWeakness" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                    بستن فهرست تماس‌ها
                </button>
            @endif
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (array_slice($teamWeaknesses, 0, 8) as $weakness)
                <button
                    type="button"
                    wire:click="selectTeamWeakness({{ Js::from($weakness['item']) }})"
                    @class([
                        'rounded-md px-3 py-1 text-sm transition',
                        'bg-red-600 text-white dark:bg-red-500' => $selectedTeamWeakness === $weakness['item'],
                        'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50' => $selectedTeamWeakness !== $weakness['item'],
                    ])
                >{{ $weakness['item'] }} ({{ $weakness['count'] }})</button>
            @endforeach
        </div>

        @if ($selectedTeamWeakness)
            <div class="mt-5 border-t border-zinc-200/80 pt-4 dark:border-zinc-800">
                <p class="text-sm text-zinc-500">تماس‌های مرتبط با: <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $selectedTeamWeakness }}</span></p>

                <div class="mt-3 space-y-3">
                    @forelse ($teamWeaknessCalls as $call)
                        <a href="{{ route('employer.intelligence.show', $call['analysis_id']) }}" class="block rounded-lg border border-zinc-200/80 bg-white p-4 transition hover:border-indigo-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-800">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-zinc-900 dark:text-white">{{ $call['customer'] }}</p>
                                        <span class="text-xs text-zinc-400">{{ $call['date'] }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-zinc-500">{{ $call['employee'] }}</p>
                                    <p class="mt-1 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $call['summary'] ?? '—' }}</p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-3 text-sm">
                                    <span class="rounded-lg bg-zinc-100 px-2.5 py-1 font-medium dark:bg-zinc-800">{{ $call['duration_label'] }}</span>
                                    <span @class(['rounded-lg px-2.5 py-1 font-bold tabular-nums', AgentPerformancePresenter::scoreTextClass($call['quality_score'] ?? null)])>
                                        {{ $call['quality_score'] ?? '—' }}
                                    </span>
                                    <span class="rounded-lg bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                        لید {{ $call['lead_score'] ?? '—' }}
                                    </span>
                                    <span class="text-xs text-zinc-500">{{ $call['sentiment'] ?? '—' }}</span>
                                </div>
                            </div>
                        </a>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-200 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700">تماسی با این ضعف پیدا نشد.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
@endif
