<?php

namespace App\Filament\Resources\QuickNotes;

use App\Filament\Resources\QuickNotes\Pages\CreateQuickNote;
use App\Filament\Resources\QuickNotes\Pages\EditQuickNote;
use App\Filament\Resources\QuickNotes\Pages\ListQuickNotes;
use App\Models\QuickNote;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuickNoteResource extends Resource
{
    protected static ?string $model = QuickNote::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $modelLabel = 'observação rápida';

    protected static ?string $pluralModelLabel = 'observações rápidas';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')->label('Categoria')->relationship('category', 'name')->required(),
            TextInput::make('label')->label('Texto do botão')->required()->maxLength(40),
            TextInput::make('position')->label('Ordem')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('label')->label('Texto')->searchable(),
                TextColumn::make('category.name')->label('Categoria'),
                TextColumn::make('position')->label('Ordem')->sortable(),
            ])
            ->filters([SelectFilter::make('category_id')->label('Categoria')->relationship('category', 'name')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuickNotes::route('/'),
            'create' => CreateQuickNote::route('/create'),
            'edit' => EditQuickNote::route('/{record}/edit'),
        ];
    }
}
