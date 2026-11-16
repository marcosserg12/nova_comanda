<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Contracts\View\View;

final class StationController
{
    public function __invoke(): View
    {
        return view('pages.estacao', [
            'leftWidth' => (int) Setting::get(Setting::STATION_LEFT_WIDTH),
            'rightWidth' => (int) Setting::get(Setting::STATION_RIGHT_WIDTH),
        ]);
    }
}
