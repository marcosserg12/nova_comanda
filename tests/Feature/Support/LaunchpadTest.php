<?php

use App\Enums\Role;
use App\Support\Launchpad;
use App\Support\Screen;

it('lists the screens each profile can open, in order', function (?Role $role, array $keys) {
    $user = $role === null ? null : userWithRole($role);

    expect(array_map(fn (Screen $screen) => $screen->key, Launchpad::for($user)))->toBe($keys);
})->with([
    'admin' => [Role::Admin, ['estacao', 'balcao', 'cozinha', 'painel', 'acompanhar', 'admin']],
    'balcao' => [Role::Balcao, ['estacao', 'balcao', 'cozinha', 'painel', 'acompanhar']],
    'cozinha' => [Role::Cozinha, ['cozinha', 'painel', 'acompanhar']],
    'visitante' => [null, ['painel', 'acompanhar', 'login']],
]);

it('points every screen to its route with pt-BR labels and tags', function () {
    $screens = collect(Launchpad::for(userWithRole(Role::Admin)))->keyBy('key');

    expect($screens['estacao']->url)->toBe(route('estacao'))
        ->and($screens['estacao']->label)->toBe('Estação')
        ->and($screens['estacao']->badge)->toBe('PC do balcão')
        ->and($screens['balcao']->url)->toBe(route('balcao'))
        ->and($screens['balcao']->label)->toBe('Balcão')
        ->and($screens['cozinha']->url)->toBe(route('cozinha'))
        ->and($screens['painel']->url)->toBe(route('painel'))
        ->and($screens['painel']->label)->toBe('Painel da TV')
        ->and($screens['acompanhar']->url)->toBe(route('acompanhar'))
        ->and($screens['admin']->url)->toBe(url('/admin'))
        ->and($screens['admin']->label)->toBe('Administração')
        ->and($screens['admin']->badge)->toBe('Só admin');

    $login = collect(Launchpad::for(null))->firstWhere('key', 'login');
    expect($login->url)->toBe(route('login'))->and($login->label)->toBe('Entrar');
});

it('reads the kitchen lateness limits from config', function () {
    expect(config('comanda.kitchen_warn_minutes'))->toBe(15)
        ->and(config('comanda.kitchen_late_minutes'))->toBe(25);
});
