<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('pedidos', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Balcao, Role::Cozinha));
