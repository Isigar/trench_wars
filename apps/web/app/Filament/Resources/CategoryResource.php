<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/07-cms/07-05-PLAN.md task 1 + <interfaces> verbatim.
 *
 * CategoryResource — simpler sibling of ArticleResource. Categories ship 4
 * LOCKED rows via CategorySeeder (plan 07-03); admin can add additional
 * categories via this resource. Translatable name (JSONB); single
 * non-translatable slug (v1 — Open Question 5 LOCKED inline).
 *
 * Delete guard: a category with at least one article cannot be deleted
 * because the articles.category_id FK uses ON DELETE RESTRICT (plan 07-02);
 * the table action visibility checks the live articles_count to avoid the
 * QueryException surface.
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 41;

    public static function getModelLabel(): string
    {
        return __('admin.category.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.category.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'CMS';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin.category.label'))
                ->schema([
                    TextInput::make('name.en')
                        ->label(__('admin.category.fields.name'))
                        ->required()
                        ->maxLength(100),

                    TextInput::make('slug')
                        ->label(__('admin.category.fields.slug'))
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit'),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.category.fields.name'))
                    ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? '—') : '—')
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereRaw("name->>'en' ILIKE ?", ['%' . $search . '%']);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('admin.category.fields.slug'))
                    ->searchable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('articles_count')
                    ->label(__('admin.article.plural_label'))
                    ->counts('articles')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record): bool => $record->articles()->count() === 0)
                    ->modalDescription(fn ($record): string => $record->articles()->count() > 0
                        ? (string) __('cms.errors.category_in_use')
                        : ''),
            ])
            ->bulkActions([
                // INTENTIONALLY EMPTY — D-012 forbids bulk-delete on auditable resources.
            ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
