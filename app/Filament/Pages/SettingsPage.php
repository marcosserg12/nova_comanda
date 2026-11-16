<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Validator;

class SettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Configurações';

    protected static ?string $title = 'Configurações';

    protected static ?string $slug = 'configuracoes';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.settings';

    public ?int $readyMinutes = 5;

    public string $restaurantName = '';

    public ?int $stationLeftWidth = 1920;

    public ?int $stationRightWidth = 1920;

    public function mount(): void
    {
        $this->readyMinutes = (int) Setting::get(Setting::READY_MINUTES);
        $this->restaurantName = (string) Setting::get(Setting::RESTAURANT_NAME);
        $this->stationLeftWidth = (int) Setting::get(Setting::STATION_LEFT_WIDTH);
        $this->stationRightWidth = (int) Setting::get(Setting::STATION_RIGHT_WIDTH);
    }

    public function save(): void
    {
        // Validates the property values explicitly: an emptied number input arrives as null
        // and must be rejected ("required") instead of being saved.
        Validator::make([
            'readyMinutes' => $this->readyMinutes,
            'restaurantName' => $this->restaurantName,
            'stationLeftWidth' => $this->stationLeftWidth,
            'stationRightWidth' => $this->stationRightWidth,
        ], [
            'readyMinutes' => ['required', 'integer', 'min:1', 'max:60'],
            'restaurantName' => ['required', 'string', 'max:60'],
            'stationLeftWidth' => ['required', 'integer', 'min:800', 'max:7680'],
            'stationRightWidth' => ['required', 'integer', 'min:600', 'max:7680'],
        ])->validate();

        Setting::put(Setting::READY_MINUTES, $this->readyMinutes);
        Setting::put(Setting::RESTAURANT_NAME, $this->restaurantName);
        Setting::put(Setting::STATION_LEFT_WIDTH, $this->stationLeftWidth);
        Setting::put(Setting::STATION_RIGHT_WIDTH, $this->stationRightWidth);

        Notification::make()->title('Configurações salvas')->success()->send();
    }
}
