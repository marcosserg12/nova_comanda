<?php

use App\Actions\Orders\UpdateOrderItems;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Cozinha\Board;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->cozinha = userWithRole(Role::Cozinha);
});

it('shows only kitchen items with notes, takeaway and edited flags', function () {
    $dish = kitchenProduct(['name' => 'Filé de peixe']);
    $soda = cabineProduct(['name' => 'Guaraná lata']);
    $order = placeOrder([item($dish, 2, 'Sem arroz'), item($soda)], ['customer_name' => 'Rita', 'takeaway' => true]);
    app(UpdateOrderItems::class)->handle($order, ['customer_name' => 'Rita', 'takeaway' => true, 'items' => [item($dish, 2, 'Sem arroz'), item($soda)]]);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSee('001')->assertSee('Rita')
        ->assertSee('Filé de peixe')->assertSee('Sem arroz')
        ->assertSee('VIAGEM')->assertSee('ALTERADO')
        ->assertDontSee('Guaraná lata');
});

it('tags only takeaway orders with VIAGEM', function () {
    placeOrder([item(kitchenProduct())], ['customer_name' => 'Lia', 'takeaway' => false]);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSee('Lia')
        ->assertDontSee('VIAGEM');
});

it('marks ready by id and by typed number', function () {
    $dish = kitchenProduct();
    $first = placeOrder([item($dish)]);
    $second = placeOrder([item($dish)]);

    $component = Livewire::actingAs($this->cozinha)->test(Board::class)->call('markReady', $first->id);
    expect($first->fresh()->status)->toBe(OrderStatus::Ready);

    $component->call('markReadyByNumber', 2)->assertReturned($second->id);
    expect($second->fresh()->status)->toBe(OrderStatus::Ready);

    $component->call('markReadyByNumber', 99)->assertReturned(null)->assertDispatched('kitchen-not-found', number: '099');
});

it('lets the kitchen undo only inside the window', function () {
    $order = placeOrder([item(kitchenProduct())]);
    $component = Livewire::actingAs($this->cozinha)->test(Board::class)->call('markReady', $order->id);

    $component->call('undoReady', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    $component->call('markReady', $order->id);
    $this->travel(30)->seconds();
    $component->call('undoReady', $order->id)->assertForbidden();
});

it('highlights new and edited orders', function (string $kind) {
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->call('onOrdersChanged', ['orderId' => 5, 'kind' => $kind])
        ->assertDispatched('kitchen-highlight', orderId: 5, kind: $kind);
})->with(['created', 'updated']);

it('does not highlight other changes', function (string $kind) {
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->call('onOrdersChanged', ['orderId' => 5, 'kind' => $kind])
        ->assertNotDispatched('kitchen-highlight');
})->with(['ready', 'unready', 'delivered', 'canceled']);

it('turns the time orange at the warn limit and the card pink at the late limit', function () {
    placeOrder([item(kitchenProduct())], ['customer_name' => 'Rafa']);

    $this->travel(14)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('data-kitchen-state="ok"')
        ->assertSee('14 min');

    $this->travel(1)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('data-kitchen-state="warn"')
        ->assertSeeHtml('text-nana-orange');

    $this->travel(10)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('data-kitchen-state="late"')
        ->assertSeeHtml('bg-[#FFE9E9]')
        ->assertSee('25 min');
});

it('reads the lateness limits from config', function () {
    config(['comanda.kitchen_warn_minutes' => 5, 'comanda.kitchen_late_minutes' => 8]);
    placeOrder([item(kitchenProduct())]);

    $this->travel(6)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)->assertSeeHtml('data-kitchen-state="warn"');

    $this->travel(2)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)->assertSeeHtml('data-kitchen-state="late"');
});

it('says "agora" for a brand new order and shows the numpad hint', function () {
    placeOrder([item(kitchenProduct())]);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSee('agora')
        ->assertSee('para marcar pronto')
        ->assertSeeHtml('data-kitchen-grid')
        ->assertSeeHtml('data-ready-button')
        ->assertSeeHtml('tabindex="-1" @pointerdown.prevent @mousedown.prevent');
});

it('has no kitchen sound left', function () {
    expect(file_get_contents(app_path('Livewire/Cozinha/Board.php')))->not->toContain('kitchen-beep')
        ->and(file_get_contents(resource_path('views/livewire/cozinha/board.blade.php')))->not->toContain('beep')
        ->and(file_get_contents(resource_path('js/kitchen.js')))->not->toContain('beep');
});

it('opens the kitchen page without the start gate', function () {
    $this->actingAs($this->cozinha)->get('/cozinha')
        ->assertOk()
        ->assertSeeLivewire(Board::class)
        ->assertDontSee('Toque para começar');
});

it('refuses guests', function () {
    $order = placeOrder([item(kitchenProduct())]);

    Livewire::test(Board::class)->call('markReady', $order->id)->assertForbidden();
});

it('flags highlighted cards below the fold on a tappable footer pill', function () {
    $order = placeOrder([item(kitchenProduct())]);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('data-kitchen-alert-bar')
        ->assertSeeHtml('NOVO PEDIDO')
        ->assertSeeHtml('PEDIDO ALTERADO')
        ->assertSeeHtml('— toque para ver')
        ->assertSeeHtml("belowHighlighted > 0 ? 'bg-nana-yellow text-nana-ink'")
        ->assertSeeHtml('x-show="belowHighlighted === 0"')
        ->assertSeeHtml('data-kitchen-alert-bar :data-kitchen-new-below="belowHighlighted" x-show="belowHighlighted > 0" x-cloak tabindex="-1" @pointerdown.prevent @mousedown.prevent @click="scrollToBelow()"')
        ->assertSeeHtml('data-kitchen-new-below')
        ->assertSeeHtml('scrollToBelow()')
        ->assertSeeHtml('belowHighlighted > 0')
        ->assertSeeHtml('abaixo ↓')
        ->assertSeeHtml('isHighlighted('.$order->id.')')
        ->assertSeeHtml('tabindex="-1" @pointerdown.prevent @mousedown.prevent @click="scrollToBelow()"');
});

it('renders the edited-at stamp so the board can flag edits without the websocket', function () {
    $dish = kitchenProduct();
    $order = placeOrder([item($dish)], ['customer_name' => 'Rita']);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('data-edited-at=""');

    app(UpdateOrderItems::class)->handle($order, ['customer_name' => 'Rita', 'takeaway' => false, 'items' => [item($dish, 2)]]);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('data-edited-at="'.$order->fresh()->edited_at->timestamp.'"');
});

it('keeps the board root from taking focus away from the counter', function () {
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('@pointerdown.prevent @mousedown.prevent');
});
