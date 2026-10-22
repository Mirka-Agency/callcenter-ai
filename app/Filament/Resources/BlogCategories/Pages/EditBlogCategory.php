<?php

namespace App\Filament\Resources\BlogCategories\Pages;

use App\Application\Blog\Actions\DeleteBlogCategory;
use App\Application\Blog\Actions\SaveBlogCategory;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Models\BlogCategory;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBlogCategory extends EditRecord
{
    protected static string $resource = BlogCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription(__('filament.blog.confirm.delete_category'))
                ->using(function (BlogCategory $record): void {
                    /** @var User $user */
                    $user = auth()->user();
                    app(DeleteBlogCategory::class)->handle($user, $record);
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BlogCategory $record */
        /** @var User $user */
        $user = auth()->user();

        return app(SaveBlogCategory::class)->handle($user, $record, $data);
    }
}
