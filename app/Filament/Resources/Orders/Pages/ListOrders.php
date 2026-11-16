<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    // Filament headline-cases the plural label ("Histórico De Pedidos"); pt-BR uses sentence case.
    protected static ?string $title = 'Histórico de pedidos';
}
