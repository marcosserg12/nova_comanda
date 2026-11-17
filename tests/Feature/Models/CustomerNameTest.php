<?php

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\UpdateOrderItems;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Filament\Resources\CustomerNames\Pages\ListCustomerNames;
use App\Models\CustomerName;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
});

it('ships with hundreds of common Brazilian names, most common first', function () {
    $names = CustomerName::suggestionList();

    expect(count($names))->toBeGreaterThan(400)
        ->and(array_slice($names, 0, 3))->toBe(['Maria', 'José', 'Ana'])
        ->and($names)->toContain('João', 'Francisca', 'Helena', 'Ana Clara')
        ->and(CustomerName::query()->where('uses', '>', 0)->count())->toBe(0);
});

it('compares names without accents, case or extra spaces', function () {
    expect(CustomerName::keyFor('  João   da SILVA '))->toBe('joao da silva');
});

it('capitalizes names typed all in lower or upper case, keeping particles lower', function () {
    expect(CustomerName::pretty('maria da silva'))->toBe('Maria da Silva')
        ->and(CustomerName::pretty('JOSÉ DOS SANTOS'))->toBe('José dos Santos')
        ->and(CustomerName::pretty('zuleide Paiva'))->toBe('Zuleide Paiva')
        ->and(CustomerName::pretty('ângela'))->toBe('Ângela')
        ->and(CustomerName::pretty('McDonald'))->toBe('McDonald');
});

it('records the name of every new order and puts used names first', function () {
    $dish = kitchenProduct();

    placeOrder([item($dish)], ['customer_name' => 'Bianca']);
    placeOrder([item($dish)], ['customer_name' => 'bianca']);
    placeOrder([item($dish)], ['customer_name' => 'Zuleica Mendes']);

    $bianca = CustomerName::query()->where('search_key', 'bianca')->sole();

    expect($bianca->uses)->toBe(2)
        ->and($bianca->name)->toBe('Bianca')
        ->and(array_slice(CustomerName::suggestionList(), 0, 2))->toBe(['Bianca', 'Zuleica Mendes']);
});

it('does not count the same order twice when it is resent', function () {
    $input = orderInput([item(kitchenProduct())], ['customer_name' => 'Rogéria']);

    app(PlaceOrder::class)->handle($input);
    app(PlaceOrder::class)->handle($input);

    expect(CustomerName::query()->where('search_key', 'rogeria')->value('uses'))->toBe(1);
});

it('prefers the spelling with accents', function () {
    CustomerName::record('Joao Vitor');
    CustomerName::record('João Vitor');

    expect(CustomerName::query()->where('search_key', 'joao vitor')->sole()->name)->toBe('João Vitor');
});

it('records the new name when an order is edited with another name', function () {
    $dish = kitchenProduct();
    $order = placeOrder([item($dish)], ['customer_name' => 'Bia']);

    app(UpdateOrderItems::class)->handle($order, ['customer_name' => 'Beatriz Souza', 'takeaway' => false, 'items' => [item($dish)]]);

    expect(CustomerName::query()->where('search_key', 'beatriz souza')->value('uses'))->toBe(1);
});

it('imports names from orders placed before the suggestions existed', function () {
    $order = placeOrder([item(kitchenProduct())], ['customer_name' => 'Valdirene']);
    CustomerName::query()->delete();

    CustomerName::backfillFromOrders();

    expect(CustomerName::query()->where('search_key', 'valdirene')->sole())
        ->uses->toBe(1)
        ->last_used_at->toDateTimeString()->toBe($order->created_at->toDateTimeString());
});

it('serves the suggestion list to the counter only', function () {
    CustomerName::record('Bianca');

    $this->get('/balcao/nomes')->assertRedirect('/login');
    $this->actingAs(userWithRole(Role::Cozinha))->get('/balcao/nomes')->assertForbidden();

    $response = $this->actingAs(userWithRole(Role::Balcao))->getJson('/balcao/nomes')->assertOk();

    expect($response->json('names.0'))->toBe('Bianca')
        ->and($response->json('names'))->toContain('Maria')
        ->and($response->json('surnames'))->toContain('Silva', 'Santos', 'Souza');
});

it('lets the admin remove a misspelled name from the suggestions', function () {
    $this->actingAs(userWithRole(Role::Admin));
    CustomerName::record('Bianac');
    $typo = CustomerName::query()->where('search_key', 'bianac')->sole();

    livewire(ListCustomerNames::class)
        ->assertCanSeeTableRecords([$typo])
        ->callTableAction(DeleteAction::class, $typo);

    expect(CustomerName::query()->whereKey($typo->id)->exists())->toBeFalse()
        ->and(DB::table('customer_names')->count())->toBeGreaterThan(400);
});
