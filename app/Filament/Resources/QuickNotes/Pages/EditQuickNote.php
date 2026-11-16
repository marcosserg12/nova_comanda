<?php

namespace App\Filament\Resources\QuickNotes\Pages;

use App\Filament\Resources\QuickNotes\QuickNoteResource;
use Filament\Resources\Pages\EditRecord;

class EditQuickNote extends EditRecord
{
    protected static string $resource = QuickNoteResource::class;
}
