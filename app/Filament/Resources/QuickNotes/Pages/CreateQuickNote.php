<?php

namespace App\Filament\Resources\QuickNotes\Pages;

use App\Filament\Resources\QuickNotes\QuickNoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuickNote extends CreateRecord
{
    protected static string $resource = QuickNoteResource::class;
}
