<?php

namespace App\Filament\Resources\BlogCategories\Pages;

use App\Application\Blog\Actions\SaveBlogCategory;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBlogCategory extends CreateRecord
{
    protected static string $resource = BlogCategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(SaveBlogCategory::class)->handle($user, null, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
