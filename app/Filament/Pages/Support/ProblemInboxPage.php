<?php

namespace App\Filament\Pages\Support;

use App\Services\Admin\ProblemInbox;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

/**
 * Visible to every admin; each admin only sees items from the areas they were granted.
 */
class ProblemInboxPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'problem-inbox';

    protected string $view = 'filament.pages.support.problem-inbox';

    public static function canAccess(): bool
    {
        return auth()->user()?->role?->isAdminStaff() === true;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.problem_inbox');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.support');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $count = Cache::remember(
            'problem-inbox-count:'.$user?->id,
            now()->addMinutes(5),
            fn (): int => app(ProblemInbox::class)->count($user),
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getTitle(): string
    {
        return __('admin.inbox.title');
    }

    public function getViewData(): array
    {
        return [
            'items' => app(ProblemInbox::class)->items(auth()->user()),
        ];
    }
}
