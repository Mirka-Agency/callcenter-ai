@auth
    @php
        $announcements = rescue(
            fn () => \App\Models\Announcement::query()->visibleTo(auth()->user())->latest('starts_at')->latest('id')->limit(3)->get(['id', 'title', 'body', 'level']),
            collect(),
            report: false,
        );
    @endphp
    @foreach ($announcements as $announcement)
        <div
            x-data="{ key: 'announcement-dismissed-{{ $announcement->id }}', open: false }"
            x-init="open = localStorage.getItem(key) === null"
            x-show="open"
            x-cloak
            @class(['announcement-banner', 'announcement-banner--'.$announcement->level->value])
            role="status"
        >
            <div class="announcement-banner__content">
                <div>
                    <p class="announcement-banner__title">{{ $announcement->title }}</p>
                    @if (filled($announcement->body))
                        <p class="announcement-banner__body">{{ $announcement->body }}</p>
                    @endif
                </div>
                <button type="button" class="announcement-banner__close" @click="localStorage.setItem(key, '1'); open = false" aria-label="{{ __('admin.announcements.dismiss') }}">×</button>
            </div>
        </div>
    @endforeach
@endauth
