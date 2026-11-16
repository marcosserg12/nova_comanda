<?php

use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Balcao\OrderForm;
use App\Models\Order;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

it('does not let the client rewrite locked properties', function (string $property, mixed $value) {
    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->set($property, $value);
})->with([
    'uuid' => ['uuid', 'x'],
    'editingOrderId' => ['editingOrderId', 1],
    'dailyNameProductId' => ['dailyNameProductId', 1],
])->throws(CannotUpdateLockedPropertyException::class);

it('refuses to load an order over an in-progress one', function () {
    $dish = kitchenProduct();
    $order = placeOrder([item($dish, 1)], ['customer_name' => 'João'], $this->user);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->dispatch('edit-order', orderId: $order->id)
        ->assertSet('editingOrderId', null)
        ->assertCount('lines', 1)
        ->assertDispatched('toast', type: 'error', message: 'Termine ou limpe o pedido atual antes de editar outro.');
});

it('keeps every counter binding in the new layout', function () {
    kitchenProduct(['name' => 'Filé de frango']);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSeeHtml('x-data="orderDraft"')
        ->assertSeeHtml('wire:submit="submit"')
        ->assertSeeHtml('wire:model="customerName" x-ref="name" autofocus')
        ->assertSeeHtml('@focus-name.window="sheet = false; $nextTick(() => $refs.name?.focus())"')
        ->assertSeeHtml('wire:model.live.debounce.150ms="search" @keydown.enter.prevent="$wire.addFirstSearchResult()"')
        ->assertSeeHtml('wire:click="toggleSoldOutMode"')
        ->assertSeeHtml('data-product-grid')
        ->assertSeeHtml('data-send-button')
        ->assertSeeHtml('data-order-bar')
        ->assertSee('Enviar para a cozinha');
});

it('switches between eating here and takeaway with the segmented control', function () {
    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSee('Comer aqui')
        ->assertSee('Para viagem')
        ->assertSeeHtml('data-takeaway-option="0" aria-pressed="true"')
        ->assertSeeHtml('data-takeaway-option="1" aria-pressed="false"')
        ->assertSeeHtml("wire:click=\"\$set('takeaway', true)\"")
        ->set('takeaway', true)
        ->assertSeeHtml('data-takeaway-option="0" aria-pressed="false"')
        ->assertSeeHtml('data-takeaway-option="1" aria-pressed="true"');
});

it('moves "Marcar esgotado" into the more-options menu next to the search', function () {
    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSeeHtmlInOrder(['type="search"', 'aria-label="Mais opções"', 'Marcar esgotado'])
        ->call('toggleSoldOutMode')
        ->assertSee('Concluir esgotados')
        ->assertSee('Toque num produto para marcar ou desmarcar como esgotado hoje.');
});

it('marks products in the order and sold out products on the tiles', function () {
    $dish = kitchenProduct(['name' => 'Filé de frango']);
    $soldOut = Product::factory()->create(['category_id' => $dish->category_id, 'name' => 'Picanha bovina']);
    $soldOut->toggleSoldOut();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSeeHtml('data-in-cart="0"')
        ->call('addProduct', $dish->id)
        ->call('addProduct', $dish->id)
        ->assertSeeHtml('data-in-cart="2"')
        ->assertSee('Esgotado hoje')
        ->assertSee('2 itens');
});

it('shows the dish of the day under the product name', function () {
    $dish = kitchenProduct(['name' => 'Prato do dia', 'has_daily_name' => true]);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSee('Definir prato de hoje')
        ->call('addProduct', $dish->id)
        ->set('dailyNameInput', 'Feijoada')
        ->call('saveDailyName')
        ->assertSeeHtml('text-nana-red">Feijoada</span>');
});

it('shows the number the next order will get, or the number being edited', function () {
    $dish = kitchenProduct();
    placeOrder([item($dish)]);
    $second = placeOrder([item($dish)], ['customer_name' => 'João'], $this->user);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSee('nº 003')
        ->dispatch('edit-order', orderId: $second->id)
        ->assertSee('nº 002')
        ->assertSee('Alterando pedido');
});

it('closes the sheet on Esc and returns focus to the menu trigger', function () {
    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSeeHtml('if (menu) { menu = false; $refs.trigger?.focus() } else { sheet = false }')
        ->assertSeeHtml('x-ref="trigger"');
});

it('turns the same dish tapped twice into one line and one order item', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->call('addProduct', $dish->id)
        ->assertCount('lines', 1)
        ->assertSet('lines.0.quantity', 2)
        ->set('customerName', 'Maria')
        ->call('submit')
        ->assertHasNoErrors();

    $items = Order::query()->sole()->items;
    expect($items)->toHaveCount(1)->and($items->first()->quantity)->toBe(2);
});

it('merges identical lines on submit and keeps different notes apart', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);

    $component = Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id);
    $component->call('toggleChip', $component->get('lines')[0]['key'], 'Sem arroz')
        ->call('addProduct', $dish->id)
        ->call('addProduct', $dish->id);
    $component->call('toggleChip', $component->get('lines')[1]['key'], 'Sem arroz')
        ->call('addProduct', $dish->id)
        ->set('lines.2.note', 'bem passado')
        ->assertCount('lines', 3)
        ->set('customerName', 'Maria')
        ->call('submit')
        ->assertHasNoErrors();

    $items = Order::query()->sole()->items->sortBy('position')->values();
    expect($items)->toHaveCount(2)
        ->and($items[0]->notes)->toBe('Sem arroz')->and($items[0]->quantity)->toBe(3)
        ->and($items[1]->notes)->toBe('bem passado')->and($items[1]->quantity)->toBe(1);
});

it('does not announce success when a resent uuid carries different items', function () {
    $dish = kitchenProduct();
    $juice = kitchenProduct();

    $component = Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->set('customerName', 'Maria');
    placeOrder([item($dish)], ['uuid' => $component->get('uuid')], $this->user);

    $component->call('addProduct', $juice->id)
        ->call('submit')
        ->assertDispatched('toast', type: 'error', message: 'Este pedido já foi enviado como Nº 001. Para mudar, use Editar em Pedidos de hoje.')
        ->assertNotDispatched('draft-clear')
        ->assertCount('lines', 2);

    expect(Order::query()->count())->toBe(1);
});

it('keeps today success behavior when a resent uuid carries the same items', function () {
    $dish = kitchenProduct();

    $component = Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->set('customerName', 'Maria');
    placeOrder([item($dish)], ['uuid' => $component->get('uuid')], $this->user);

    $component->call('submit')
        ->assertDispatched('toast', type: 'success', message: 'Pedido 001 enviado!')
        ->assertSet('lines', []);
});

it('refuses to load another order while editing one', function () {
    $dish = kitchenProduct();
    $first = placeOrder([item($dish)], ['customer_name' => 'João'], $this->user);
    $second = placeOrder([item($dish)], ['customer_name' => 'Ana'], $this->user);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->dispatch('edit-order', orderId: $first->id)
        ->dispatch('edit-order', orderId: $second->id)
        ->assertSet('editingOrderId', $first->id)
        ->assertSet('customerName', 'João')
        ->assertDispatched('toast', type: 'error', message: 'Termine ou cancele a edição atual antes de editar outro pedido.');
});

it('does nothing on Enter in an empty search box', function () {
    $dish = kitchenProduct();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->set('categoryId', $dish->category_id)
        ->call('addFirstSearchResult')
        ->assertCount('lines', 0);
});

it('toggles sold out on the first result with Enter in sold out mode', function () {
    $dish = kitchenProduct(['name' => 'Filé de frango']);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('toggleSoldOutMode')
        ->set('search', 'Filé')
        ->call('addFirstSearchResult')
        ->assertCount('lines', 0);

    expect($dish->fresh()->isSoldOut())->toBeTrue();
});

it('adds the first search result with Enter outside sold out mode', function () {
    kitchenProduct(['name' => 'Filé de frango']);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->set('search', 'Filé')
        ->call('addFirstSearchResult')
        ->assertCount('lines', 1);
});

it('saves an edit of an order whose dish sold out and whose price changed', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $order = placeOrder([item($dish)], ['customer_name' => 'João'], $this->user);
    $dish->update(['price_cents' => 3000]);
    $dish->toggleSoldOut();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->dispatch('edit-order', orderId: $order->id)
        ->set('takeaway', true)
        ->call('submit')
        ->assertHasNoErrors();

    expect($order->fresh()->total_cents)->toBe(2400)->and($order->fresh()->takeaway)->toBeTrue();
});

it('shows Laravel validation messages in Portuguese with friendly field names', function () {
    $dish = kitchenProduct();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->set('customerName', str_repeat('a', 61))
        ->call('submit')
        ->assertHasErrors(['customerName' => 'max'])
        ->assertSee('O campo nome do cliente deve ter no máximo 60 caracteres.');
});

it('never drops quantity when merging identical lines past 20', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);

    $component = Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id);
    $component->call('toggleChip', $component->get('lines')[0]['key'], 'Sem arroz')
        ->set('lines.0.quantity', 15)
        ->call('addProduct', $dish->id);
    $component->call('toggleChip', $component->get('lines')[1]['key'], 'Sem arroz')
        ->set('lines.1.quantity', 15)
        ->set('customerName', 'Maria')
        ->call('submit')
        ->assertHasNoErrors();

    $items = Order::query()->sole()->items;
    expect($items->sum('quantity'))->toBe(30)
        ->and($items->max('quantity'))->toBeLessThanOrEqual(20)
        ->and($items->pluck('notes')->unique()->all())->toBe(['Sem arroz']);
});

it('starts a new line instead of capping when a plain line is already at 20', function () {
    $dish = kitchenProduct();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->set('lines.0.quantity', 20)
        ->call('addProduct', $dish->id)
        ->assertCount('lines', 2)
        ->assertSet('lines.0.quantity', 20)
        ->assertSet('lines.1.quantity', 1);
});
