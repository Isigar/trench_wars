<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

/**
 * Source: .planning/phases/01-foundations/01-13-PLAN.md task 2 + CONTEXT.md
 * "P1 admin grants permissions via tinker / artisan".
 *
 * Read-mostly resource: List + Edit only. Create is intentionally omitted —
 * permissions are seeded via PermissionSeeder + the trenchwars:make-admin
 * artisan command (plan 11). Surfacing Create in Filament would let admins
 * mint permission strings the codebase doesn't reference.
 */
class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('admin.permission.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.permission.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // WR-05 (01-REVIEW.md): permission `name` MUST be read-only via the
            // admin UI. Codebase paths (User::canAccessPanel, MakeAdminCommand,
            // FilamentPanelAccessTest, etc.) hard-code the string `admin-access`
            // — renaming via Filament would lock every existing admin out of
            // the panel until someone hand-edits the DB or re-runs the seeder.
            // Permissions are a developer concern; admins use roles to grant.
            Forms\Components\TextInput::make('name')
                ->label(__('admin.permission.fields.name'))
                ->disabled()
                ->dehydrated(false)
                ->maxLength(255),
            Forms\Components\Select::make('guard_name')
                ->label(__('admin.permission.fields.guard_name'))
                ->options(['web' => 'web'])
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.permission.fields.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->label(__('admin.permission.fields.guard_name')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            // Create intentionally omitted — admin grants via PermissionSeeder + trenchwars:make-admin.
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
