<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin,balcao'])->group(function () {
    Route::get('/estacao', StationController::class)->name('estacao');
    Route::view('/balcao', 'pages.balcao')->name('balcao');
});

Route::view('/cozinha', 'pages.cozinha')->middleware(['auth', 'role:admin,balcao,cozinha'])->name('cozinha');

Route::middleware('throttle:60,1')->group(function () {
    Route::view('/painel', 'pages.painel')->name('painel');
    Route::view('/acompanhar', 'pages.acompanhar')->name('acompanhar');
});
