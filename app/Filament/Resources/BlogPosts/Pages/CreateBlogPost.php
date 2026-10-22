<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Application\Blog\Actions\DiscardRecoverableDraft;
use App\Application\Blog\Actions\FindOrCreateRecoverableDraft;
use App\Domain\Blog\BlogPostStatus;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Models\BlogPost;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    public function mount(): void
    {
        abort_unless(static::getResource()::canCreate(), 403);

        /** @var User $user */
        $user = Auth::user();

        $existing = BlogPost::query()
            ->where('owned_by_user_id', $user->id)
            ->where('status', BlogPostStatus::Draft)
            ->where('is_recoverable_draft', true)
            ->first();

        if ($existing instanceof BlogPost && request()->boolean('discard')) {
            app(DiscardRecoverableDraft::class)->handle($user, $existing);
            $existing = null;

            Notification::make()
                ->title(__('filament.blog.draft.discarded'))
                ->success()
                ->send();
        }

        if ($existing instanceof BlogPost) {
            Notification::make()
                ->title(__('filament.blog.draft.resume_title'))
                ->body(__('filament.blog.draft.resume_body'))
                ->persistent()
                ->actions([
                    Action::make('discard_draft')
                        ->label(__('filament.blog.draft.discard'))
                        ->color('danger')
                        ->url(static::getResource()::getUrl('create', ['discard' => 1])),
                ])
                ->send();

            $this->redirect(static::getResource()::getUrl('edit', ['record' => $existing]));

            return;
        }

        $draft = app(FindOrCreateRecoverableDraft::class)->handle($user);

        $this->redirect(static::getResource()::getUrl('edit', ['record' => $draft]));
    }
}
