<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    public const PRICE_REGEX = '/^\d{1,4}([.,]\d{1,2})?$/';

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'produto';

    protected static ?string $pluralModelLabel = 'produtos';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')->label('Categoria')->relationship('category', 'name')->required(),
            TextInput::make('name')->label('Nome')->required()->maxLength(80),
            TextInput::make('price')->label('Preço (R$)')->placeholder('24,00')->required()->regex(self::PRICE_REGEX),
            Toggle::make('has_daily_name')->label('Tem nome do dia (ex.: Prato do dia)'),
            Toggle::make('active')->label('Ativo')->default(true),
            TextInput::make('position')->label('Ordem')->numeric()->default(0),
            FileUpload::make('image_path')->label('Foto')->image()->disk('public')->directory('products'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('image_path')->label('Foto')->disk('public'),
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Categoria'),
                TextInputColumn::make('price')->label('Preço (R$)')->rules(['required', 'regex:'.self::PRICE_REGEX]),
                ToggleColumn::make('sold_out_today')->label('Esgotado hoje'),
                ToggleColumn::make('active')->label('Ativo'),
            ])
            ->filters([SelectFilter::make('category_id')->label('Categoria')->relationship('category', 'name')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
