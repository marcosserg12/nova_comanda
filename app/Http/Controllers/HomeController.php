<?php

namespace App\Http\Controllers;

use App\Support\Launchpad;
use App\Support\Screen;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HomeController
{
    public function __invoke(Request $request): View
    {
        $screens = Launchpad::for($request->user());

        return view('pages.inicio', [
            'screens' => $screens,
            'allowed' => collect($screens)
                ->reject(fn (Screen $screen) => $screen->key === 'login')
                ->mapWithKeys(fn (Screen $screen) => [$screen->key => $screen->url])
                ->all(),
            'choosing' => $request->boolean('escolher'),
        ]);
    }
}
