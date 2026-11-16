<?php

use App\Enums\Role;
use App\Livewire\Balcao\InKitchenList;
use App\Livewire\Balcao\OrderForm;
use App\Livewire\Balcao\TodayOrders;
use App\Livewire\Cozinha\Board;
use App\Models\Setting;

it('renders counter and kitchen side by side with the configured widths', function () {
    Setting::put(Setting::STATION_LEFT_WIDTH, 1920);
    Setting::put(Setting::STATION_RIGHT_WIDTH, 1366);

    $this->actingAs(userWithRole(Role::Balcao))->get('/estacao')
        ->assertOk()
        ->assertSee('grid-template-columns: 1920px 1366px', false)
        ->assertSeeLivewire(OrderForm::class)
        ->assertSeeLivewire(TodayOrders::class)
        ->assertSeeLivewire(InKitchenList::class)
        ->assertSeeLivewire(Board::class)
        ->assertSee('Toque para começar');
});

it('renders the counter alone on /balcao', function () {
    $this->actingAs(userWithRole(Role::Balcao))->get('/balcao')
        ->assertOk()
        ->assertSeeLivewire(OrderForm::class)
        ->assertDontSeeLivewire(Board::class);
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
