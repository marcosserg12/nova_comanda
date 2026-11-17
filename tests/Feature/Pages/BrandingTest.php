<?php

use App\Enums\Role;

it('shows the mascot big on the login and start pages', function () {
    $this->get('/login')->assertOk()->assertSee('images/nana-chef.png', false)->assertSee('data-brand-face', false);
    $this->get('/')->assertOk()->assertSee('images/nana-chef.png', false)->assertSee('images/nana-rosto.png', false);
});

it('shows the mascot face next to the name on every screen top bar', function (string $path) {
    $this->actingAs(userWithRole(Role::Admin))->get($path)->assertOk()->assertSee('data-brand-face', false);
})->with(['/balcao', '/estacao', '/cozinha']);

it('shows the mascot on the TV and on the customer phone', function () {
    $this->get('/painel')->assertOk()->assertSee('images/nana-chef.png', false);
    $this->get('/acompanhar')->assertOk()->assertSee('data-brand-face', false);
});

it('uses the mascot as the browser tab icon, including the admin', function () {
    $this->get('/login')->assertSee('rel="icon"', false)->assertSee('images/nana-icone-180.png', false);
    $this->actingAs(userWithRole(Role::Admin))->get('/admin')->assertOk()->assertSee('images/nana-rosto.png', false)->assertSee('favicon.ico', false);

    expect(filesize(public_path('favicon.ico')))->toBeGreaterThan(0)
        ->and(file_exists(public_path('images/nana-chef.png')))->toBeTrue()
        ->and(file_exists(public_path('images/nana-rosto.png')))->toBeTrue();
});

it('offers the counter PC installer only to the counter and the admin', function () {
    $this->get('/')->assertDontSee('data-installer-link', false);
    $this->actingAs(userWithRole(Role::Cozinha))->get('/')->assertDontSee('data-installer-link', false);

    $this->actingAs(userWithRole(Role::Balcao))->get('/?escolher=1')
        ->assertSee('data-installer-link', false)
        ->assertSee('downloads/NanaComanda-Instalador.exe', false)
        ->assertSee('Instalar no PC do balcão');

    expect(file_exists(public_path('downloads/NanaComanda-Instalador.exe')))->toBeTrue();
});
