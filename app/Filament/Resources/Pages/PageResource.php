<?php

namespace App\Filament\Resources\Pages;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use App\Models\Page;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static BackedEnum|string|null $navigationIcon = 'phosphor-files-duotone';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(191)
                    ->helperText('Same slug for all languages'),
                
                Tabs::make('Translations')
                    ->tabs([
                        Tabs\Tab::make('Español')
                            ->schema([
                                TextInput::make('title_i18n.es')
                                    ->label('Título')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state, $get) {
                                        if (!$get('slug')) {
                                            $set('slug', Str::slug($state));
                                        }
                                    })
                                    ->required()
                                    ->maxLength(191),
                                Textarea::make('excerpt_i18n.es')
                                    ->label('Extracto')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                RichEditor::make('body_i18n.es')
                                    ->label('Contenido')
                                    ->required()
                                    ->columnSpanFull(),
                                Textarea::make('meta_description_i18n.es')
                                    ->label('Meta Description (SEO)')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Textarea::make('meta_keywords_i18n.es')
                                    ->label('Meta Keywords (SEO)')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                        Tabs\Tab::make('English')
                            ->schema([
                                TextInput::make('title_i18n.en')
                                    ->label('Title')
                                    ->maxLength(191),
                                Textarea::make('excerpt_i18n.en')
                                    ->label('Excerpt')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                RichEditor::make('body_i18n.en')
                                    ->label('Content')
                                    ->columnSpanFull(),
                                Textarea::make('meta_description_i18n.en')
                                    ->label('Meta Description (SEO)')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Textarea::make('meta_keywords_i18n.en')
                                    ->label('Meta Keywords (SEO)')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
                
                FileUpload::make('image')
                    ->image()
                    ->label('Featured Image'),
                Select::make('author_id')
                    ->label('Author')
                    ->options(
                        User::all()
                            ->mapWithKeys(fn ($user) => [
                                $user->id => $user->name
                                    ?? $user->username
                                    ?? $user->email,
                            ])
                            ->toArray()
                    )
                    ->searchable()
                    ->required(),
                Select::make('status')
                    ->required()
                    ->options([
                        'ACTIVE' => 'Active',
                        'INACTIVE' => 'Inactive',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title_i18n.es')
                    ->label('Título (ES)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title_i18n.en')
                    ->label('Title (EN)')
                    ->searchable()
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ?: '—'),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACTIVE' => 'success',
                        'INACTIVE' => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
