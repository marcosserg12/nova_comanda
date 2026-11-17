<?php

namespace App\Filament\Resources\CustomerNames\Pages;

use App\Filament\Resources\CustomerNames\CustomerNameResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerNames extends ListRecords
{
    protected static string $resource = CustomerNameResource::class;

    protected static ?string $title = 'Nomes de clientes';
}
