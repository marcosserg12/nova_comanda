<?php

use App\Enums\Role;
use App\Events\BoardChanged;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Facades\Broadcast;

/** The callback registered for the private "pedidos" channel in routes/channels.php. */
function pedidosChannelCallback(): Closure
{
    $channels = (new ReflectionProperty(Broadcaster::class, 'channels'))->getValue(Broadcast::driver());

    return $channels['pedidos'];
}

it('authorizes the private pedidos channel for admin, balcao and cozinha', function (Role $role) {
    expect(pedidosChannelCallback()(userWithRole($role)))->toBeTrue();
})->with([Role::Admin, Role::Balcao, Role::Cozinha]);

it('denies the private pedidos channel to a user with no role', function () {
    expect(pedidosChannelCallback()(new User(['role' => null])))->toBeFalse();
});

it('exposes exactly the safe fields on the public board channel', function () {
    $properties = collect((new ReflectionClass(BoardChanged::class))->getProperties(ReflectionProperty::IS_PUBLIC))
        ->reject(fn (ReflectionProperty $property) => $property->isStatic() || $property->getName() === 'socket')
        ->map(fn (ReflectionProperty $property) => $property->getName())
        ->sort()->values()->all();

    expect($properties)->toBe(['firstName', 'kind', 'number', 'orderId', 'status'])
        ->and(method_exists(BoardChanged::class, 'broadcastWith'))->toBeFalse();
});
