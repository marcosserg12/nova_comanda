<?php

use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Filament\Pages\SalesSummaryPage;
use App\Filament\Pages\SettingsPage;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Setting;
use Illuminate\Support\Facades\Event;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->actingAs(userWithRole(Role::Admin));
});

it('lists and shows orders in the history', function () {
    $order = placeOrder([item(kitchenProduct(['name' => 'Coxão mole']), 1, 'Sem farofa')], ['customer_name' => 'Rui']);

    livewire(ListOrders::class)->assertOk()->assertCanSeeTableRecords([$order])->assertSee('Rui');
    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])->assertOk()->assertSee('Coxão mole')->assertSee('Sem farofa');
});

it('shows the sales summary for today', function () {
    placeOrder([item(kitchenProduct(['price_cents' => 2400]))]);

    livewire(SalesSummaryPage::class)->assertOk()->assertSee('R$ 24,00');
});

it('saves the settings', function () {
    livewire(SettingsPage::class)
        ->set('readyMinutes', 8)
        ->set('restaurantName', 'Nana Lanches')
        ->set('stationLeftWidth', 1920)
        ->set('stationRightWidth', 1366)
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get(Setting::READY_MINUTES))->toBe(8)
        ->and(Setting::get(Setting::STATION_RIGHT_WIDTH))->toBe(1366);
});

it('validates settings', function () {
    livewire(SettingsPage::class)->set('readyMinutes', 0)->call('save')->assertHasErrors('readyMinutes');
});

it('survives an emptied number field without crashing or saving it', function () {
    livewire(SettingsPage::class)
        ->set('readyMinutes', '')
        ->set('stationLeftWidth', '')
        ->call('save')
        ->assertOk();

    expect((int) Setting::get(Setting::READY_MINUTES))->toBe(5)
        ->and((int) Setting::get(Setting::STATION_LEFT_WIDTH))->toBe(1920);
});

it('titles the order history in pt-BR sentence case', function () {
    $this->get('/admin/orders')->assertOk()->assertSee('Histórico de pedidos')->assertDontSee('Histórico De Pedidos');
});
