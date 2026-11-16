<?php

use App\Enums\Role;

it('opens the admin only for the admin profile', function () {
    $this->actingAs(userWithRole(Role::Admin))->get('/admin')->assertOk();
    $this->actingAs(userWithRole(Role::Balcao))->get('/admin')->assertForbidden();
});

it('refuses non-admin profiles', function (Role $role) {
    $this->actingAs(userWithRole($role))->get('/admin')->assertForbidden();
})->with([Role::Balcao, Role::Cozinha]);

it('sends guests to the app login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});
