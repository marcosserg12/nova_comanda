<?php

use App\Enums\Role;

it('shows guests the public tiles and the login tile instead of redirecting', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Onde você quer entrar?')
        ->assertSee('Painel da TV')
        ->assertSee('Acompanhar')
        ->assertSee('Entrar')
        ->assertSee('data-screen="painel"', false)
        ->assertSee('data-screen="acompanhar"', false)
        ->assertSee('data-screen="login"', false)
        ->assertDontSee('data-screen="estacao"', false)
        ->assertDontSee('data-screen="balcao"', false)
        ->assertDontSee('data-screen="cozinha"', false)
        ->assertDontSee('data-screen="admin"', false)
        ->assertDontSee('Administração');
});

it('shows each profile only the tiles it can open', function (Role $role, array $visible, array $hidden) {
    $response = $this->actingAs(userWithRole($role))->get('/')->assertOk();

    foreach ($visible as $key) {
        $response->assertSee('data-screen="'.$key.'"', false);
    }

    foreach ($hidden as $key) {
        $response->assertDontSee('data-screen="'.$key.'"', false);
    }
})->with([
    'admin' => [Role::Admin, ['estacao', 'balcao', 'cozinha', 'painel', 'acompanhar', 'admin'], ['login']],
    'balcao' => [Role::Balcao, ['estacao', 'balcao', 'cozinha', 'painel', 'acompanhar'], ['admin', 'login']],
    'cozinha' => [Role::Cozinha, ['cozinha', 'painel', 'acompanhar'], ['estacao', 'balcao', 'admin', 'login']],
]);

it('links every tile to its route and tags the station and the admin', function () {
    $this->actingAs(userWithRole(Role::Admin))->get('/')
        ->assertSee('href="'.route('estacao').'"', false)
        ->assertSee('href="'.route('balcao').'"', false)
        ->assertSee('href="'.route('cozinha').'"', false)
        ->assertSee('href="'.route('painel').'"', false)
        ->assertSee('href="'.route('acompanhar').'"', false)
        ->assertSee('href="'.url('/admin').'"', false)
        ->assertSee('PC do balcão')
        ->assertSee('Só admin')
        ->assertSee('Abrir sempre esta tela neste aparelho');
});

it('remembers the device choice only in the browser and lets the user forget it', function () {
    $this->get('/')
        ->assertSee('x-data="launcher(', false)
        ->assertSee('data-update-uri', false)
        ->assertDontSee('Esquecer a escolha deste aparelho');

    $this->get('/?escolher=1')
        ->assertOk()
        ->assertSee('Esquecer a escolha deste aparelho')
        ->assertSee(', true)"', false);
});
