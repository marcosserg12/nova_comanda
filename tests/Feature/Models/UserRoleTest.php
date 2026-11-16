<?php

use App\Enums\Role;

it('checks roles', function () {
    $user = userWithRole(Role::Balcao);

    expect($user->hasRole(Role::Balcao))->toBeTrue()
        ->and($user->hasRole(Role::Admin, Role::Balcao))->toBeTrue()
        ->and($user->hasRole(Role::Cozinha))->toBeFalse();
});
