<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use FilamentTiptapEditor\Enums\TiptapOutput;
use FilamentTiptapEditor\TiptapEditor;

/**
 * Source: .planning/phases/07-cms/07-05-PLAN.md task 1 + <interfaces> verbatim.
 *
 * ArticleResource — Filament v3 admin CRUD for articles (D-012).
 *
 * Form layout: 2 Sections — "Content" (title/slug/category/excerpt/hero/body)
 * + "Publication" (status/scheduled_at/published_at/allow_discord_announce).
 *
 * Pitfall 10 mitigation chain (Stored XSS via author-inserted iframe/script):
 *   1. config/filament-tiptap-editor.php 'default' profile excludes oembed/youtube/video/source.
 *   2. TiptapEditor::make('body.en')->profile('default') — references the pinned allowlist.
 *   3. TiptapOutput::Json — never stores raw HTML; output is Tiptap JSON document.
 *   4. PublicArticleData::fromModel renders via tiptap_converter()->asHTML at request time;
 *      the converter's extension set mirrors the profile (no iframe-bearing nodes).
 *   5. PublicArticleDataTest asserts `not->toContain('<iframe')` + `not->toContain('<script')`.
 *
 * Open Question 4 LOCKED inline: author-provided slug with unique-rule validation;
 * NOT auto-suffixed because the Article slug is a permalink — auto-suffixing breaks
 * shared links. The ->unique(ignoreRecord: true) rule is the UX gate; DB UNIQUE
 * constraint (plan 07-02) is defence-in-depth. slug ->disabledOn('edit') prevents
 * permalink drift after publish.
 *
 * Open Question 5 LOCKED inline: single non-translatable slug in v1; per-locale
 * slugs deferred to CMS-V2.
 *
 * Authorization: canCreate/canEdit/canDelete delegate to ArticlePolicy via
 * Filament's static::getModel() auto-resolution (Filament v3 idiom).
 *
 * Threat refs:
 *   - T-07-05-01 (image bomb)       → maxSize(5120) + accepted MIME whitelist
 *   - T-07-05-02 (scheduled_at past)→ DateTimePicker ->minDate(now()); service re-validates
 *   - T-07-05-03 (slug collision)   → ->unique(ignoreRecord: true) + DB UNIQUE
 *   - T-07-05-04 (Tiptap XSS)       → profile('default') + TiptapOutput::Json
 *   - T-07-05-05 (cms-editor delete)→ ArticlePolicy::delete requires super-admin role
 *   - T-07-05-07 (author spoof)     → CreateArticle::mutateFormDataBeforeCreate hardcodes auth()->id()
 */
class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('admin.article.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.article.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'CMS';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin.article.label'))
                ->schema([
                    TextInput::make('title.en')
                        ->label(__('admin.article.fields.title'))
                        ->required()
                        ->maxLength(200),

                    TextInput::make('slug')
                        ->label(__('admin.article.fields.slug'))
                        ->required()
                        ->maxLength(200)
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit')
                        ->helperText(__('cms.fields.slug.help')),

                    Select::make('category_id')
                        ->label(__('admin.article.fields.category_id'))
                        ->relationship('category', 'name->en')
                        ->required()
                        ->preload()
                        ->searchable(),

                    TextInput::make('excerpt.en')
                        ->label(__('admin.article.fields.excerpt'))
                        ->maxLength(500),

                    SpatieMediaLibraryFileUpload::make('hero')
                        ->label(__('cms.fields.hero.label'))
                        ->collection('hero')
                        ->image()
                        ->imageEditor()
                        ->responsiveImages()
                        ->maxSize(5 * 1024)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->helperText(__('cms.fields.hero.help'))
                        ->columnSpanFull(),

                    TiptapEditor::make('body.en')
                        ->label(__('admin.article.fields.body'))
                        ->profile('default')                  // Pitfall 10 — pinned in 07-01 config
                        ->output(TiptapOutput::Json)          // never Html
                        ->disk('public')
                        ->directory('article-media')
                        ->maxContentWidth('5xl')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(1),

            Section::make(__('admin.article.publication.section'))
                ->description(__('admin.article.publication.help'))
                ->schema([
                    Select::make('status')
                        ->label(__('admin.article.fields.status'))
                        ->options([
                            'draft' => __('cms.status.draft.label'),
                            'scheduled' => __('cms.status.scheduled.label'),
                            'published' => __('cms.status.published.label'),
                        ])
                        ->default('draft')
                        ->required()
                        ->reactive(),

                    DateTimePicker::make('scheduled_at')
                        ->label(__('admin.article.fields.scheduled_at'))
                        ->seconds(false)
                        ->timezone('UTC')
                        ->visible(fn (Get $get): bool => $get('status') === 'scheduled')
                        ->required(fn (Get $get): bool => $get('status') === 'scheduled')
                        ->minDate(now())
                        ->helperText(__('cms.fields.scheduled_at.help')),

                    DateTimePicker::make('published_at')
                        ->label(__('admin.article.fields.published_at'))
                        ->seconds(false)
                        ->timezone('UTC')
                        ->visible(fn (Get $get): bool => $get('status') === 'published')
                        ->disabled()
                        ->helperText(__('cms.fields.published_at.help')),

                    Toggle::make('allow_discord_announce')
                        ->label(__('admin.article.fields.allow_discord_announce'))
                        ->default(true)
                        ->helperText(__('cms.fields.allow_discord_announce.help')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('admin.article.fields.title'))
                    ->getStateUsing(fn ($record): string => is_array($record->title) ? ($record->title['en'] ?? '—') : '—')
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereRaw("title->>'en' ILIKE ?", ['%' . $search . '%']);
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('admin.article.fields.category_id'))
                    ->getStateUsing(fn ($record): string => is_array($record->category?->name) ? ($record->category->name['en'] ?? '—') : '—')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('admin.article.fields.status'))
                    ->getStateUsing(fn ($record): string => (string) __('cms.status.' . $record->status . '.label'))
                    ->colors([
                        'secondary' => fn ($state, $record): bool => $record->status === 'draft',
                        'warning' => fn ($state, $record): bool => $record->status === 'scheduled',
                        'success' => fn ($state, $record): bool => $record->status === 'published',
                    ]),

                Tables\Columns\TextColumn::make('author.username')
                    ->label(__('admin.article.fields.author_user_id'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('published_at')
                    ->label(__('admin.article.fields.published_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('common.updated_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.article.fields.status'))
                    ->options([
                        'draft' => __('cms.status.draft.label'),
                        'scheduled' => __('cms.status.scheduled.label'),
                        'published' => __('cms.status.published.label'),
                    ]),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('admin.article.fields.category_id'))
                    ->relationship('category', 'name->en'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
