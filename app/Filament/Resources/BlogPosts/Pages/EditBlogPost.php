<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Application\Blog\Actions\AutosaveBlogPost;
use App\Application\Blog\Actions\DeleteBlogPost;
use App\Application\Blog\Actions\DiscardRecoverableDraft;
use App\Application\Blog\Actions\SaveBlogPost;
use App\Application\Blog\Support\BlogPostEditLock;
use App\Domain\Blog\BlogPostStatus;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Models\BlogPost;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * @property-read BlogPost $record
 */
class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    public string $autosaveStatus = 'idle';

    public bool $hasWriteLock = false;

    public ?string $lockHolderName = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->acquireLockOrMarkReadonly();
    }

    public function hydrate(): void
    {
        if ($this->hasWriteLock) {
            $this->heartbeatLock();
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var BlogPost $record */
        $record = $this->record;

        if ($record->usesPlaceholderTitle()) {
            $data['title'] = '';
        }

        $data['publish'] = $record->status === BlogPostStatus::Published;
        $data['faq_json'] = is_array($record->faq_json) ? $record->faq_json : [];
        $data['content_version'] = $record->content_version;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BlogPost $record */
        /** @var User $user */
        $user = Auth::user();

        if (! $this->hasWriteLock) {
            Notification::make()
                ->title(__('filament.blog.errors.lock_required'))
                ->danger()
                ->send();

            $this->halt();
        }

        try {
            return app(SaveBlogPost::class)->handle($user, $record, $data, requireLock: true);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? __('filament.blog.errors.save_failed'))
                ->danger()
                ->send();

            throw $exception;
        }
    }

    protected function afterSave(): void
    {
        /** @var BlogPost $record */
        $record = $this->record->fresh();
        $this->record = $record;
        $this->form->fill($this->mutateFormDataBeforeFill($record->attributesToArray() + [
            'publish' => $record->status === BlogPostStatus::Published,
            'faq_json' => $record->faq_json ?? [],
        ]));
    }

    public function autosaveDraft(): void
    {
        if (! $this->hasWriteLock) {
            $this->autosaveStatus = 'locked';

            return;
        }

        /** @var BlogPost $record */
        $record = $this->record;

        if ($record->status !== BlogPostStatus::Draft) {
            $this->autosaveStatus = 'idle';

            return;
        }

        if ((bool) ($this->data['publish'] ?? false)) {
            $this->autosaveStatus = 'idle';

            return;
        }

        /** @var User $user */
        $user = Auth::user();
        $this->autosaveStatus = 'saving';

        try {
            $data = $this->form->getState();
            $saved = app(AutosaveBlogPost::class)->handle($user, $record, $data);
            $this->record = $saved;
            $this->data['content_version'] = $saved->content_version;
            $this->autosaveStatus = 'saved';
        } catch (ValidationException $exception) {
            $this->autosaveStatus = 'error';
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? __('filament.blog.errors.autosave_failed'))
                ->danger()
                ->send();
        } catch (\Throwable) {
            $this->autosaveStatus = 'error';
        }
    }

    public function heartbeatLock(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $ok = app(BlogPostEditLock::class)->heartbeat((int) $this->record->id, $user);

        if (! $ok) {
            $this->hasWriteLock = false;
            $this->acquireLockOrMarkReadonly();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('discard_recoverable')
                ->label(__('filament.blog.draft.discard'))
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => (bool) $this->record->is_recoverable_draft
                    && (int) $this->record->owned_by_user_id === (int) Auth::id())
                ->action(function (): void {
                    /** @var User $user */
                    $user = Auth::user();
                    app(DiscardRecoverableDraft::class)->handle($user, $this->record);
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
            Action::make('view_public')
                ->label(__('filament.blog.actions.view_public'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => route('blog.show', $this->record->slug))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->record->isPublished()),
            DeleteAction::make()
                ->requiresConfirmation()
                ->using(function (BlogPost $record): void {
                    /** @var User $user */
                    $user = Auth::user();
                    app(DeleteBlogPost::class)->handle($user, $record);
                }),
        ];
    }

    protected function getFormActions(): array
    {
        if (! $this->hasWriteLock) {
            return [
                Action::make('back')
                    ->label(__('filament.blog.actions.cancel'))
                    ->url(static::getResource()::getUrl('index')),
            ];
        }

        return parent::getFormActions();
    }

    public function getHeading(): string
    {
        $title = $this->record->usesPlaceholderTitle()
            ? __('filament.blog.untitled')
            : (string) $this->record->title;

        return $title;
    }

    protected function acquireLockOrMarkReadonly(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $result = app(BlogPostEditLock::class)->acquire((int) $this->record->id, $user);

        $this->hasWriteLock = $result['acquired'];
        $this->lockHolderName = $result['holder']['user_name'] ?? null;

        if (! $this->hasWriteLock) {
            $this->form->disabled();

            Notification::make()
                ->title(__('filament.blog.errors.lock_held', ['name' => $this->lockHolderName ?? '—']))
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
