<?php

use App\Enums\Role;
use App\Livewire\Balcao\InKitchenList;
use App\Livewire\Balcao\OrderForm;
use App\Livewire\Balcao\TodayOrders;
use App\Livewire\Cozinha\Board;
use App\Models\Setting;

it('renders counter and kitchen with the default 1366 px left monitor and no start gate', function () {
    $this->actingAs(userWithRole(Role::Balcao))->get('/estacao')
        ->assertOk()
        ->assertSee('data-configured-left-width="1366"', false)
        ->assertSee('x-data="station(1366, 1366)"', false)
        ->assertSee('data-station-left', false)
        ->assertSee('data-station-right', false)
        ->assertSee('Detectar monitores')
        ->assertSee('Cozinha no 2º monitor não encontrada')
        ->assertDontSee('Toque para começar')
        ->assertDontSee('grid-template-columns: 1366px', false)
        ->assertSeeLivewire(OrderForm::class)
        ->assertSeeLivewire(TodayOrders::class)
        ->assertSeeLivewire(InKitchenList::class)
        ->assertSeeLivewire(Board::class);
});

it('uses the widths saved by the admin', function () {
    Setting::put(Setting::STATION_LEFT_WIDTH, 1600);
    Setting::put(Setting::STATION_RIGHT_WIDTH, 1280);

    $this->actingAs(userWithRole(Role::Admin))->get('/estacao')
        ->assertOk()
        ->assertSee('data-configured-left-width="1600"', false)
        ->assertSee('x-data="station(1600, 1280)"', false);
});

it('renders the counter alone on /balcao', function () {
    $this->actingAs(userWithRole(Role::Balcao))->get('/balcao')
        ->assertOk()
        ->assertSeeLivewire(OrderForm::class)
        ->assertDontSeeLivewire(Board::class)
        ->assertDontSee('Detectar monitores');
});

it('renders the counter pane with its tabs as a size container', function () {
    $this->actingAs(userWithRole(Role::Balcao))->get('/balcao')
        ->assertOk()
        ->assertSee('x-data="balcaoPane"', false)
        ->assertSee('@container/pane', false)
        ->assertSee('Novo pedido')
        ->assertSee('Pedidos de hoje')
        ->assertSee('Na cozinha')
        ->assertSee('data-in-kitchen-column', false)
        ->assertSee('Telas')
        ->assertDontSee('Toque para começar');
});
