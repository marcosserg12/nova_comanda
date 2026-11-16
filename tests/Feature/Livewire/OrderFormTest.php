<?php

use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Balcao\OrderForm;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->user = userWithRole(Role::Balcao);
});

it('adds a product as a line with its current price', function () {
    $dish = kitchenProduct(['name' => 'Filé de frango', 'price_cents' => 2400]);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->assertSet('lines.0.name', 'Filé de frango')
        ->assertSet('lines.0.unit_price_cents', 2400)
        ->assertSee('R$ 24,00');
});

it('refuses a sold out product', function () {
    $dish = kitchenProduct();
    $dish->toggleSoldOut();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->assertHasErrors('lines')
        ->assertCount('lines', 0);
});

it('asks for the dish of the day before adding it', function () {
    $dish = kitchenProduct(['name' => 'Prato do dia', 'has_daily_name' => true, 'price_cents' => 2600]);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->assertSet('dailyNameProductId', $dish->id)
        ->assertCount('lines', 0)
        ->set('dailyNameInput', 'Strogonoff')
        ->call('saveDailyName')
        ->assertSet('dailyNameProductId', null)
        ->assertSet('lines.0.name', 'Prato do dia: Strogonoff');
});

it('composes chips and free text into notes and places the order', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);

    $component = Livewire::actingAs($this->user)->test(OrderForm::class)->call('addProduct', $dish->id);
    $key = $component->get('lines')[0]['key'];
    $firstUuid = $component->get('uuid');

    $component->call('toggleChip', $key, 'Sem arroz')
        ->call('toggleChip', $key, 'C/ Ovo')
        ->set('lines.0.note', 'bem passado')
        ->call('increment', $key)
        ->set('customerName', 'Maria')
        ->set('takeaway', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('lines', [])
        ->assertSet('customerName', '')
        ->assertDispatched('toast')
        ->assertDispatched('draft-clear');

    expect($component->get('uuid'))->not->toBe($firstUuid);

    $order = Order::query()->with('items')->sole();
    expect($order->number)->toBe(1)
        ->and($order->takeaway)->toBeTrue()
        ->and($order->total_cents)->toBe(4800)
        ->and($order->items->first()->quantity)->toBe(2)
        ->and($order->items->first()->notes)->toBe('Sem arroz, C/ Ovo, bem passado');
});

it('validates name and items before sending', function () {
    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('submit')
        ->assertHasErrors(['customerName', 'lines']);
});

it('loads an order for editing and saves the change', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $juice = kitchenProduct(['price_cents' => 500]);
    $order = placeOrder([item($dish, 1, 'Sem arroz, sem cebola')], ['customer_name' => 'João'], $this->user);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->dispatch('edit-order', orderId: $order->id)
        ->assertSet('editingOrderId', $order->id)
        ->assertSet('customerName', 'João')
        ->call('addProduct', $juice->id)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('editingOrderId', null);

    expect($order->fresh()->items)->toHaveCount(2)
        ->and($order->fresh()->edited_at)->not->toBeNull()
        ->and(Order::query()->count())->toBe(1);
});

it('restores a draft re-pricing items from the database', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $uuid = (string) Str::uuid();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('restoreDraft', [
            'uuid' => $uuid,
            'customerName' => 'Ana',
            'takeaway' => true,
            'lines' => [['product_id' => $dish->id, 'quantity' => 2, 'unit_price_cents' => 1, 'chips' => ['Sem arroz'], 'note' => 'x']],
        ])
        ->assertSet('uuid', $uuid)
        ->assertSet('customerName', 'Ana')
        ->assertSet('lines.0.unit_price_cents', 2400)
        ->assertSet('lines.0.quantity', 2)
        ->assertSet('lines.0.chips', ['Sem arroz']);
});

it('lets the counter mark a product as sold out', function () {
    $dish = kitchenProduct();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('toggleSoldOutMode')
        ->assertSet('soldOutMode', true)
        ->call('toggleSoldOut', $dish->id);

    expect($dish->fresh()->isSoldOut())->toBeTrue();
});

it('forbids the kitchen profile from placing orders', function () {
    $dish = kitchenProduct();

    Livewire::actingAs(userWithRole(Role::Cozinha))->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->assertForbidden();
});
