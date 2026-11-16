<?php

use App\Enums\Role;

it('opens the admin only for the admin profile', function () {
    $this->actingAs(userWithRole(Role::Admin))->get('/admin')->assertOk();
    $this->actingAs(userWithRole(Role::Balcao))->get('/admin')->assertForbidden();
});

it('sends guests to the app login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});
