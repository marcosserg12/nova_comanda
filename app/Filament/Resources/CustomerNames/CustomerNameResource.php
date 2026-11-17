<?php

namespace App\Filament\Resources\CustomerNames;

use App\Filament\Resources\CustomerNames\Pages\ListCustomerNames;
use App\Models\CustomerName;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Names suggested at the counter; the admin deletes misspelled ones so they stop being suggested. */
class CustomerNameResource extends Resource
{
    protected static ?string $model = CustomerName::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Nomes de clientes';

    protected static ?string $modelLabel = 'nome';

    protected static ?string $pluralModelLabel = 'nomes de clientes';

    protected static ?int $navigationSort = 4;

    protected static ?string $breadcrumb = 'Nomes de clientes';

    public static function table(Table $table): Table
    {
        return $table
            ->description('Nomes sugeridos no campo "Nome do cliente" do balcão. Apague os nomes com erro de digitação para que não sejam mais sugeridos.')
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('uses')->orderByDesc('last_used_at')->orderBy('id'))
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('uses')->label('Pedidos')->sortable(),
                TextColumn::make('last_used_at')->label('Último pedido')->since()->sortable()->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('used')
                    ->label('Origem')
                    ->placeholder('Todos')
                    ->trueLabel('Já usados em pedidos')
                    ->falseLabel('Só da lista de nomes comuns')
                    ->queries(
                        true: fn (Builder $query) => $query->where('uses', '>', 0),
                        false: fn (Builder $query) => $query->where('uses', 0),
                    ),
            ])
            ->recordActions([DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomerNames::route('/')];
    }
}
