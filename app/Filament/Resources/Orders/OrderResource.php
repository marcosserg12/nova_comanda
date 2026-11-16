<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'pedido';

    protected static ?string $pluralModelLabel = 'histórico de pedidos';

    protected static ?string $navigationLabel = 'Histórico';

    // Filament headline-cases the plural label in the breadcrumb; pt-BR uses sentence case.
    protected static ?string $breadcrumb = 'Histórico de pedidos';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('business_date')->label('Dia')->date('d/m/Y')->sortable(),
                TextColumn::make('number')->label('Nº')->formatStateUsing(fn (int $state) => str_pad((string) $state, 3, '0', STR_PAD_LEFT)),
                TextColumn::make('customer_name')->label('Cliente')->searchable(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                TextColumn::make('total_cents')->label('Total')->formatStateUsing(fn (int $state) => Money::format($state)),
                TextColumn::make('created_at')->label('Hora')->dateTime('H:i'),
            ])
            ->filters([
                Filter::make('periodo')
                    ->schema([DatePicker::make('from')->label('De'), DatePicker::make('until')->label('Até')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('business_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('business_date', '<=', $date))),
                SelectFilter::make('status')->label('Status')->options(OrderStatus::options()),
                SelectFilter::make('product')->label('Produto')
                    ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $productId) => $q->whereHas('items', fn (Builder $items) => $items->where('product_id', $productId)),
                    )),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('number')->label('Número')->formatStateUsing(fn (int $state) => str_pad((string) $state, 3, '0', STR_PAD_LEFT)),
            TextEntry::make('customer_name')->label('Cliente'),
            TextEntry::make('status')->label('Status')->formatStateUsing(fn (OrderStatus $state) => $state->label()),
            TextEntry::make('total_cents')->label('Total')->formatStateUsing(fn (int $state) => Money::format($state)),
            TextEntry::make('cancel_reason')->label('Motivo do cancelamento')->placeholder('—'),
            RepeatableEntry::make('items')->label('Itens')->schema([
                TextEntry::make('quantity')->label('Qtd'),
                TextEntry::make('name_snapshot')->label('Produto'),
                TextEntry::make('notes')->label('Observação')->placeholder('—'),
                TextEntry::make('unit_price_cents')->label('Preço')->formatStateUsing(fn (int $state) => Money::format($state)),
            ])->columns(4)->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
