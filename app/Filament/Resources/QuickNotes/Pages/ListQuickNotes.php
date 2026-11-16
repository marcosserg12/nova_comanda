<?php

namespace App\Filament\Resources\QuickNotes\Pages;

use App\Filament\Resources\QuickNotes\QuickNoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuickNotes extends ListRecords
{
    protected static string $resource = QuickNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
