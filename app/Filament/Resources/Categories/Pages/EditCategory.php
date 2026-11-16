<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (Category $record) => $record->products()->exists())
                ->tooltip(fn (Category $record) => $record->products()->exists() ? 'Desative a categoria em vez de excluir' : null),
        ];
    }
}
