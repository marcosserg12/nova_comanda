<?php

use App\Enums\Role;
use Illuminate\Support\Facades\Gate;

it('sends guests to the login page', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/estacao', '/balcao', '/cozinha']);

it('shows the home screen to every profile instead of redirecting', function (?Role $role) {
    if ($role !== null) {
        $this->actingAs(userWithRole($role));
    }

    $this->get('/')->assertOk()->assertSee('Onde você quer entrar?');
})->with([Role::Admin, Role::Balcao, Role::Cozinha, null]);

it('keeps the kitchen profile out of the counter screens', function () {
    $cozinha = userWithRole(Role::Cozinha);

    $this->actingAs($cozinha)->get('/estacao')->assertForbidden();
    $this->actingAs($cozinha)->get('/balcao')->assertForbidden();
    $this->actingAs($cozinha)->get('/cozinha')->assertOk();
});

it('lets the counter open its screens', function () {
    $balcao = userWithRole(Role::Balcao);

    $this->actingAs($balcao)->get('/estacao')->assertOk();
    $this->actingAs($balcao)->get('/balcao')->assertOk();
    $this->actingAs($balcao)->get('/cozinha')->assertOk();
});

it('serves the public screens without login', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/painel', '/acompanhar']);

it('defines the order gates per role', function () {
    $cozinha = userWithRole(Role::Cozinha);
    $balcao = userWithRole(Role::Balcao);

    expect(Gate::forUser($cozinha)->allows('mark-ready'))->toBeTrue()
        ->and(Gate::forUser($cozinha)->allows('place-orders'))->toBeFalse()
        ->and(Gate::forUser($cozinha)->allows('manage-orders'))->toBeFalse()
        ->and(Gate::forUser($balcao)->allows('place-orders'))->toBeTrue()
        ->and(Gate::forUser($balcao)->allows('manage-orders'))->toBeTrue();
});
