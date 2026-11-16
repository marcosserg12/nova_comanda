<?php

namespace App\Livewire\Balcao;

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\UpdateOrderItems;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\DailyCounter;
use App\Models\Order;
use App\Models\Product;
use App\Models\QuickNote;
use App\Support\BusinessDay;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderForm extends Component
{
    #[Locked]
    public string $uuid = '';

    public string $customerName = '';

    public bool $takeaway = false;

    /** @var list<array{key: string, item_id?: int|null, product_id: int, category_id: int, name: string, unit_price_cents: int, quantity: int, chips: list<string>, note: string}> */
    public array $lines = [];

    public ?int $categoryId = null;

    public string $search = '';

    public bool $soldOutMode = false;

    #[Locked]
    public ?int $editingOrderId = null;

    #[Locked]
    public ?int $dailyNameProductId = null;

    public string $dailyNameInput = '';

    public function mount(): void
    {
        $this->uuid = (string) Str::uuid();
        $this->categoryId = Category::query()->where('active', true)->orderBy('position')->value('id');
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->where('active', true)->orderBy('position')->get();
    }

    #[Computed]
    public function products(): Collection
    {
        $term = trim($this->search);

        return Product::query()
            ->where('active', true)
            ->whereHas('category', fn ($query) => $query->where('active', true))
            ->when($term !== '', fn ($query) => $query->where('name', 'like', "%{$term}%"))
            ->when($term === '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function quickNotes(): Collection
    {
        return QuickNote::query()->orderBy('position')->get()->groupBy('category_id');
    }

    #[Computed]
    public function totalCents(): int
    {
        return (int) collect($this->lines)->sum(fn (array $line) => $line['unit_price_cents'] * $line['quantity']);
    }

    /** Number shown in the order panel: the edited order's number, or a preview of the next one. */
    #[Computed]
    public function orderNumber(): string
    {
        $number = $this->editingOrderId !== null
            ? (int) Order::query()->whereKey($this->editingOrderId)->value('number')
            : (int) DailyCounter::query()->whereKey(BusinessDay::todayString())->value('last_number') + 1;

        return str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    /** @return array<int, int> product id => quantity in the order being built */
    #[Computed]
    public function cartQuantities(): array
    {
        $quantities = [];

        foreach ($this->lines as $line) {
            $quantities[$line['product_id']] = ($quantities[$line['product_id']] ?? 0) + $line['quantity'];
        }

        return $quantities;
    }

    #[Computed]
    public function itemCount(): int
    {
        return (int) collect($this->lines)->sum('quantity');
    }

    public function selectCategory(int $categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->search = '';
    }

    public function addProduct(int $productId): void
    {
        Gate::authorize('place-orders');
        $product = Product::query()->findOrFail($productId);

        if ($product->isSoldOut()) {
            $this->addError('lines', "{$product->name} está esgotado hoje.");

            return;
        }

        if ($product->needsDailyName()) {
            $this->openDailyName($product->id);

            return;
        }

        $same = collect($this->lines)->search(
            fn (array $line) => $line['product_id'] === $product->id && $line['chips'] === [] && trim($line['note']) === ''
        );

        if ($same !== false) {
            $this->lines[$same]['quantity'] = min(20, $this->lines[$same]['quantity'] + 1);
            $this->search = '';
            $this->resetErrorBag('lines');

            return;
        }

        $this->lines[] = [
            'key' => (string) Str::ulid(),
            'item_id' => null,
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'name' => $product->displayName(),
            'unit_price_cents' => $product->price_cents,
            'quantity' => 1,
            'chips' => [],
            'note' => '',
        ];
        $this->search = '';
        $this->resetErrorBag('lines');
    }

    public function addFirstSearchResult(): void
    {
        if (trim($this->search) === '') {
            return;
        }

        $first = $this->products->first();

        if ($first === null) {
            return;
        }

        if ($this->soldOutMode) {
            $this->toggleSoldOut($first->id);

            return;
        }

        $this->addProduct($first->id);
    }

    public function editDailyName(int $productId): void
    {
        Gate::authorize('place-orders');
        $this->openDailyName($productId);
    }

    public function saveDailyName(): void
    {
        Gate::authorize('place-orders');
        $this->validate(
            ['dailyNameInput' => ['required', 'string', 'max:60']],
            ['dailyNameInput.required' => 'Digite o prato de hoje.'],
        );

        $product = Product::query()->findOrFail($this->dailyNameProductId);
        $product->setDailyName($this->dailyNameInput);
        $this->closeDailyName();
        $this->addProduct($product->id);
    }

    public function closeDailyName(): void
    {
        $this->dailyNameProductId = null;
        $this->dailyNameInput = '';
    }

    public function toggleSoldOutMode(): void
    {
        Gate::authorize('manage-orders');
        $this->soldOutMode = ! $this->soldOutMode;
    }

    public function toggleSoldOut(int $productId): void
    {
        Gate::authorize('manage-orders');
        Product::query()->findOrFail($productId)->toggleSoldOut();
    }

    public function increment(string $key): void
    {
        $this->updateLine($key, fn (array $line) => [...$line, 'quantity' => min(20, $line['quantity'] + 1)]);
    }

    public function decrement(string $key): void
    {
        $this->updateLine($key, fn (array $line) => [...$line, 'quantity' => max(1, $line['quantity'] - 1)]);
    }

    public function remove(string $key): void
    {
        $this->lines = array_values(array_filter($this->lines, fn (array $line) => $line['key'] !== $key));
    }

    public function toggleChip(string $key, string $label): void
    {
        $this->updateLine($key, function (array $line) use ($label) {
            $line['chips'] = in_array($label, $line['chips'], true)
                ? array_values(array_diff($line['chips'], [$label]))
                : [...$line['chips'], $label];

            return $line;
        });
    }

    public function submit(): void
    {
        Gate::authorize('place-orders');
        $this->validate(
            ['customerName' => ['required', 'string', 'max:60'], 'lines' => ['required', 'array', 'min:1']],
            [
                'customerName.required' => 'Informe o nome do cliente.',
                'lines.required' => 'Adicione pelo menos um item.',
                'lines.min' => 'Adicione pelo menos um item.',
            ],
        );

        $payload = [
            'uuid' => $this->uuid,
            'customer_name' => trim($this->customerName),
            'takeaway' => $this->takeaway,
            'items' => $this->mergedItems(),
        ];

        if ($this->editingOrderId !== null) {
            Gate::authorize('manage-orders');
            $order = app(UpdateOrderItems::class)->handle(Order::query()->findOrFail($this->editingOrderId), $payload);
            $message = "Pedido {$order->displayNumber()} alterado!";
        } else {
            $order = app(PlaceOrder::class)->handle($payload, auth()->user());

            if (! $order->wasRecentlyCreated && $this->itemsSignature($order->items->map->only(['product_id', 'quantity', 'notes'])->all()) !== $this->itemsSignature($payload['items'])) {
                $this->dispatch('toast', type: 'error', message: "Este pedido já foi enviado como Nº {$order->displayNumber()}. Para mudar, use Editar em Pedidos de hoje.");

                return;
            }

            $message = "Pedido {$order->displayNumber()} enviado!";
        }

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('draft-clear');
        $this->dispatch('focus-name');
    }

    #[On('edit-order')]
    public function loadOrder(int $orderId): void
    {
        Gate::authorize('manage-orders');

        if ($this->editingOrderId !== null && $this->editingOrderId !== $orderId) {
            $this->dispatch('toast', type: 'error', message: 'Termine ou cancele a edição atual antes de editar outro pedido.');

            return;
        }

        if ($this->editingOrderId === null && $this->lines !== []) {
            $this->dispatch('toast', type: 'error', message: 'Termine ou limpe o pedido atual antes de editar outro.');

            return;
        }

        $order = Order::query()->with('items.product')->findOrFail($orderId);

        if ($order->status !== OrderStatus::Preparing) {
            $this->dispatch('toast', type: 'error', message: 'Só dá para alterar pedidos que ainda estão em preparo.');

            return;
        }

        $labels = QuickNote::query()->pluck('label')->all();

        $this->editingOrderId = $order->id;
        $this->uuid = $order->uuid;
        $this->customerName = $order->customer_name;
        $this->takeaway = $order->takeaway;
        $this->lines = $order->items->map(function ($item) use ($labels) {
            $parts = $item->notes ? array_map('trim', explode(',', $item->notes)) : [];

            return [
                'key' => (string) Str::ulid(),
                'item_id' => $item->id,
                'product_id' => (int) $item->product_id,
                'category_id' => (int) ($item->product?->category_id ?? 0),
                'name' => $item->name_snapshot,
                'unit_price_cents' => $item->unit_price_cents,
                'quantity' => $item->quantity,
                'chips' => array_values(array_filter($parts, fn (string $part) => in_array($part, $labels, true))),
                'note' => implode(', ', array_filter($parts, fn (string $part) => ! in_array($part, $labels, true))),
            ];
        })->all();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    /** Restores the browser draft (localStorage). Prices and names always come from the database. */
    public function restoreDraft(array $draft): void
    {
        if ($this->editingOrderId !== null || $this->lines !== []) {
            return;
        }

        $lines = [];
        foreach (array_slice((array) ($draft['lines'] ?? []), 0, 30) as $line) {
            $product = Product::query()->find((int) ($line['product_id'] ?? 0));

            if ($product === null || ! $product->active) {
                continue;
            }

            $lines[] = [
                'key' => (string) Str::ulid(),
                'item_id' => null,
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'name' => $product->displayName(),
                'unit_price_cents' => $product->price_cents,
                'quantity' => max(1, min(20, (int) ($line['quantity'] ?? 1))),
                'chips' => array_values(array_filter((array) ($line['chips'] ?? []), 'is_string')),
                'note' => Str::limit((string) ($line['note'] ?? ''), 120, ''),
            ];
        }

        $this->lines = $lines;
        $this->customerName = Str::limit((string) ($draft['customerName'] ?? ''), 60, '');
        $this->takeaway = (bool) ($draft['takeaway'] ?? false);

        if (Str::isUuid((string) ($draft['uuid'] ?? ''))) {
            $this->uuid = (string) $draft['uuid'];
        }
    }

    public function render(): View
    {
        return view('livewire.balcao.order-form');
    }

    private function openDailyName(int $productId): void
    {
        $this->dailyNameProductId = $productId;
        $this->dailyNameInput = Product::query()->find($productId)?->dailyNameToday() ?? '';
    }

    private function updateLine(string $key, callable $change): void
    {
        $this->lines = array_map(fn (array $line) => $line['key'] === $key ? $change($line) : $line, $this->lines);
    }

    /**
     * Identical lines (same product and same composed note) become one, capped at 20 per line.
     *
     * @return list<array{item_id: int|null, product_id: int, quantity: int, notes: ?string}>
     */
    private function mergedItems(): array
    {
        $merged = [];

        foreach ($this->lines as $line) {
            $notes = $this->composeNotes($line);
            $key = $line['product_id'] > 0 ? $line['product_id'].'|'.$notes : 'item'.($line['item_id'] ?? $line['key']);

            if (isset($merged[$key])) {
                $merged[$key]['quantity'] = min(20, $merged[$key]['quantity'] + $line['quantity']);

                continue;
            }

            $merged[$key] = [
                'item_id' => $line['item_id'] ?? null,
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'notes' => $notes,
            ];
        }

        return array_values($merged);
    }

    /** @param  list<array{product_id: int|null, quantity: int, notes?: ?string}>  $items */
    private function itemsSignature(array $items): array
    {
        $rows = array_map(fn (array $item) => ((int) $item['product_id']).'|'.$item['quantity'].'|'.trim((string) ($item['notes'] ?? '')), $items);
        sort($rows);

        return $rows;
    }

    private function composeNotes(array $line): ?string
    {
        $parts = $line['chips'];

        if (trim($line['note']) !== '') {
            $parts[] = trim($line['note']);
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function resetForm(): void
    {
        $this->reset(['customerName', 'takeaway', 'lines', 'search', 'editingOrderId']);
        $this->uuid = (string) Str::uuid();
        $this->resetErrorBag();
    }
}
