<?php

namespace App\Filament\Resources\BlogCategories;

use App\Filament\Concerns\OnlySuperAdmin;
use App\Filament\Resources\BlogCategories\Pages\CreateBlogCategory;
use App\Filament\Resources\BlogCategories\Pages\EditBlogCategory;
use App\Filament\Resources\BlogCategories\Pages\ListBlogCategories;
use App\Filament\Resources\BlogCategories\Schemas\BlogCategoryForm;
use App\Filament\Resources\BlogCategories\Tables\BlogCategoriesTable;
use App\Models\BlogCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BlogCategoryResource extends Resource
{
    use OnlySuperAdmin;

    protected static ?string $model = BlogCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.blog_categories');
    }

    public static function getModelLabel(): string
    {
        return __('filament.models.blog_category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.models.blog_categories');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.groups.content');
    }

    public static function form(Schema $schema): Schema
    {
        return BlogCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlogCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogCategories::route('/'),
            'create' => CreateBlogCategory::route('/create'),
            'edit' => EditBlogCategory::route('/{record}/edit'),
        ];
    }
}
