@use('App\Support\Money')
<div x-data="orderDraft" class="h-full min-h-0">
    <div x-data="{ sheet: false, menu: false }"
         @focus-name.window="sheet = false; $nextTick(() => $refs.name?.focus())"
         @edit-order.window="sheet = true"
         @keydown.escape.window="if (menu) { menu = false; $refs.trigger?.focus() } else { sheet = false }"
         class="relative flex h-full min-h-0 flex-col gap-3 @min-[1024px]/pane:flex-row @min-[1024px]/pane:gap-4">

        {{-- Pratos --}}
        <section class="flex min-h-0 min-w-0 flex-1 flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($this->categories as $category)
                    <x-ui.chip wire:key="cat-{{ $category->id }}" wire:click="selectCategory({{ $category->id }})"
                               :active="$categoryId === $category->id && trim($search) === ''">{{ $category->name }}</x-ui.chip>
                @endforeach

                <div class="ml-auto flex items-center gap-2">
                    <input type="search" wire:model.live.debounce.150ms="search" @keydown.enter.prevent="$wire.addFirstSearchResult()"
                           placeholder="Buscar…" aria-label="Buscar produto"
                           class="min-h-11 w-44 rounded-md border-2 border-nana-ink bg-white px-3 text-base font-semibold placeholder:text-nana-muted max-lg:min-h-12">
                    <div class="relative" @click.outside="menu = false">
                        <button type="button" x-ref="trigger" @click="menu = ! menu" aria-label="Mais opções" aria-haspopup="menu" :aria-expanded="menu.toString()"
                                class="grid size-11 place-items-center rounded-md border-2 border-nana-ink bg-white text-2xl font-extrabold leading-none shadow-hard-sm max-lg:size-12">⋯</button>
                        <div x-show="menu" x-cloak role="menu"
                             class="absolute right-0 top-full z-20 mt-2 w-60 overflow-hidden rounded-lg border-2 border-nana-ink bg-white shadow-hard">
                            <button type="button" role="menuitem" wire:click="toggleSoldOutMode" @click="menu = false"
                                    class="block min-h-12 w-full px-4 text-left font-bold hover:bg-nana-yellow">
                                {{ $soldOutMode ? 'Concluir esgotados' : 'Marcar esgotado' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($soldOutMode)
                <div class="flex flex-wrap items-center gap-3 rounded-lg border-2 border-nana-ink bg-nana-yellow px-4 py-2 font-bold">
                    <span class="min-w-0 flex-1">Toque num produto para marcar ou desmarcar como esgotado hoje.</span>
                    <x-ui.button variant="dark" wire:click="toggleSoldOutMode">Concluir esgotados</x-ui.button>
                </div>
            @endif

            <div data-product-grid class="grid min-h-0 flex-1 grid-cols-2 content-start gap-3 overflow-y-auto p-2 @min-[1024px]/pane:grid-cols-3">
                @foreach ($this->products as $product)
                    @php($soldOut = $product->isSoldOut())
                    @php($inCart = $soldOut ? 0 : ($this->cartQuantities[$product->id] ?? 0))
                    @php($dailyName = $product->dailyNameToday())
                    <div wire:key="prod-{{ $product->id }}" class="relative">
                        <button type="button" data-product-tile data-in-cart="{{ $inCart }}"
                                wire:click="{{ $soldOutMode ? "toggleSoldOut({$product->id})" : "addProduct({$product->id})" }}"
                                @disabled($soldOut && ! $soldOutMode)
                                @class([
                                    'flex min-h-24 w-full flex-col items-start justify-between gap-2 rounded-lg border-2 border-nana-ink p-3 text-left',
                                    'bg-white shadow-hard active:translate-x-0.5 active:translate-y-0.5 active:shadow-none' => ! $soldOut && $inCart === 0,
                                    'bg-[#FFF7E6] shadow-hard outline-4 -outline-offset-8 outline-nana-yellow' => $inCart > 0,
                                    'bg-white opacity-45' => $soldOut,
                                ])>
                            <span class="block">
                                <span class="block text-base font-bold leading-tight">{{ $product->name }}</span>
                                @if ($dailyName !== null)
                                    <span class="mt-0.5 block text-sm font-bold text-nana-red">{{ $dailyName }}</span>
                                @elseif ($product->needsDailyName())
                                    <span class="mt-0.5 block text-sm font-bold text-nana-red">Definir prato de hoje</span>
                                @endif
                                @if ($soldOut)
                                    <span class="mt-0.5 block text-sm font-bold text-nana-muted">Esgotado hoje</span>
                                @endif
                            </span>
                            <span class="font-display text-lg leading-none">{{ Money::format($product->price_cents) }}</span>
                        </button>
                        @if ($inCart > 0)
                            <span aria-label="{{ $inCart }} no pedido"
                                  class="pointer-events-none absolute -right-2 -top-2 grid size-8 place-items-center rounded-full border-2 border-nana-ink bg-nana-red text-sm font-extrabold text-white">{{ $inCart }}</span>
                        @endif
                        @if ($product->has_daily_name && ! $product->needsDailyName() && ! $soldOutMode)
                            <button type="button" wire:click="editDailyName({{ $product->id }})" title="Trocar prato de hoje" aria-label="Trocar prato de hoje"
                                    class="absolute bottom-2 right-2 grid size-11 place-items-center rounded-md border-2 border-nana-ink bg-nana-cream text-base">✎</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Pedido --}}
        <form wire:submit="submit" data-order-panel
              class="hidden min-h-0 flex-col rounded-lg border-2 border-nana-ink bg-white p-4 shadow-hard @min-[1024px]/pane:flex @min-[1024px]/pane:w-[32cqw] @min-[1024px]/pane:shrink-0 @min-[1280px]/pane:w-[27cqw]"
              :class="{ 'flex! absolute inset-0 z-30 @min-[1024px]/pane:static': sheet }">
            <div class="flex items-baseline justify-between gap-2">
                <h2 class="font-display text-2xl leading-none">{{ $editingOrderId ? 'Alterando pedido' : 'Pedido' }}</h2>
                <span class="text-sm font-bold text-nana-muted">nº {{ $this->orderNumber }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-x-4">
                <button type="button" @click="sheet = false" class="min-h-11 text-sm font-bold text-nana-muted underline @min-[1024px]/pane:hidden">← Voltar aos pratos</button>
                @if ($editingOrderId)
                    <button type="button" wire:click="cancelEdit" class="min-h-11 text-sm font-bold text-nana-muted underline">cancelar alteração</button>
                @endif
            </div>

            <label class="mt-2 block">
                <span class="text-xs font-extrabold uppercase tracking-wider text-nana-muted">Nome do cliente</span>
                <input type="text" wire:model="customerName" x-ref="name" autofocus autocomplete="off" maxlength="60"
                       class="mt-1 block min-h-12 w-full rounded-md border-3 border-nana-red bg-white px-3 text-xl font-bold focus:border-nana-red">
            </label>
            @error('customerName') <p class="mt-1 text-sm font-bold text-nana-red">{{ $message }}</p> @enderror

            <div class="mt-3 grid grid-cols-2 gap-2" role="group" aria-label="Onde vai comer">
                <button type="button" data-takeaway-option="0" aria-pressed="{{ $takeaway ? 'false' : 'true' }}" wire:click="$set('takeaway', false)"
                        @class(['min-h-11 rounded-md border-2 border-nana-ink px-3 font-bold', 'bg-nana-ink text-nana-cream' => ! $takeaway, 'bg-white' => $takeaway])>Comer aqui</button>
                <button type="button" data-takeaway-option="1" aria-pressed="{{ $takeaway ? 'true' : 'false' }}" wire:click="$set('takeaway', true)"
                        @class(['min-h-11 rounded-md border-2 border-nana-ink px-3 font-bold', 'bg-nana-ink text-nana-cream' => $takeaway, 'bg-white' => ! $takeaway])>Para viagem</button>
            </div>

            <ul class="mt-3 min-h-0 flex-1 overflow-y-auto">
                @forelse ($lines as $i => $line)
                    @php($notes = $this->quickNotes->get($line['category_id'], collect()))
                    <li wire:key="line-{{ $line['key'] }}" class="border-b-2 border-nana-line py-2">
                        <div class="flex items-center gap-2">
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" wire:click="decrement('{{ $line['key'] }}')" aria-label="Diminuir"
                                        class="grid size-11 place-items-center rounded-md border-2 border-nana-ink bg-nana-cream text-xl font-extrabold">−</button>
                                <span class="w-6 text-center text-lg font-extrabold">{{ $line['quantity'] }}</span>
                                <button type="button" wire:click="increment('{{ $line['key'] }}')" aria-label="Aumentar"
                                        class="grid size-11 place-items-center rounded-md border-2 border-nana-ink bg-nana-cream text-xl font-extrabold">+</button>
                            </div>
                            <p class="min-w-0 flex-1 font-bold leading-tight">{{ $line['name'] }}</p>
                            <span class="shrink-0 font-bold tabular-nums">{{ Money::format($line['unit_price_cents'] * $line['quantity']) }}</span>
                        </div>
                        @if ($notes->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($notes as $note)
                                    @php($on = in_array($note->label, $line['chips'], true))
                                    <button type="button" wire:key="chip-{{ $line['key'] }}-{{ $note->id }}"
                                            wire:click="toggleChip('{{ $line['key'] }}', @js($note->label))" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                            @class([
                                                'min-h-11 rounded-full border-2 px-3 text-sm font-bold',
                                                'border-nana-ink bg-nana-ink text-nana-cream' => $on,
                                                'border-dashed border-nana-ink/60 bg-white text-nana-ink' => ! $on,
                                            ])>{{ $note->label }}</button>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-2 flex items-center gap-2">
                            <input type="text" wire:model="lines.{{ $i }}.note" placeholder="Outra observação…" maxlength="120"
                                   class="min-h-11 min-w-0 flex-1 rounded-md border-2 border-nana-line bg-white px-3 text-base">
                            <button type="button" wire:click="remove('{{ $line['key'] }}')" class="min-h-11 shrink-0 px-2 text-sm font-bold text-nana-red underline">remover</button>
                        </div>
                    </li>
                @empty
                    <li class="py-10 text-center font-semibold text-nana-muted">Toque nos pratos para montar o pedido.</li>
                @endforelse
            </ul>

            @if ($errors->hasAny(['lines', 'items', 'items.*', 'order']))
                <div class="mt-2 rounded-md border-2 border-nana-red bg-white p-2 text-sm font-bold text-nana-red">
                    @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <div data-order-total class="mt-3 flex items-baseline justify-between border-t-2 border-nana-ink pt-3 font-display text-2xl">
                <span>Total</span><span>{{ Money::format($this->totalCents) }}</span>
            </div>

            <button type="submit" data-send-button wire:loading.attr="disabled" wire:target="submit"
                    class="mt-3 min-h-14 rounded-lg border-2 border-nana-ink bg-nana-red px-4 font-display text-xl text-white shadow-hard disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">{{ $editingOrderId ? 'Salvar alteração' : 'Enviar para a cozinha' }}</span>
                <span wire:loading wire:target="submit">Enviando…</span>
            </button>
        </form>

        {{-- Barra do pedido (tablet em pé) --}}
        <button type="button" data-order-bar @click="sheet = true" x-show="! sheet"
                class="flex shrink-0 items-center gap-3 rounded-lg border-2 border-nana-ink bg-nana-ink px-4 py-3 text-left text-nana-cream @min-[1024px]/pane:hidden">
            <span class="min-w-0 flex-1">
                <span class="block truncate text-lg font-bold">
                    <span x-text="$wire.customerName || 'Novo pedido'">{{ $customerName !== '' ? $customerName : 'Novo pedido' }}</span>
                    · {{ $this->itemCount }} {{ $this->itemCount === 1 ? 'item' : 'itens' }}
                </span>
                <span class="block text-sm text-[#CFC3AD]">Toque para ver o pedido</span>
            </span>
            <span class="shrink-0 rounded-lg border-2 border-nana-cream bg-nana-red px-4 py-3 font-display text-xl text-white">{{ Money::format($this->totalCents) }} →</span>
        </button>
    </div>

    {{-- Prato de hoje --}}
    @if ($dailyNameProductId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-nana-ink/60 p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="daily-title"
                 class="w-full max-w-md rounded-xl border-3 border-nana-ink bg-nana-cream p-6 shadow-[8px_8px_0_var(--color-nana-ink)]">
                <h3 id="daily-title" class="font-display text-2xl">Qual é o prato de hoje?</h3>
                <input type="text" wire:model="dailyNameInput" wire:keydown.enter.prevent="saveDailyName" autofocus x-init="$nextTick(() => $el.focus())" maxlength="60"
                       placeholder="Ex.: Strogonoff" class="mt-4 block min-h-12 w-full rounded-md border-3 border-nana-ink bg-white px-3 text-xl font-bold">
                @error('dailyNameInput') <p class="mt-1 text-sm font-bold text-nana-red">{{ $message }}</p> @enderror
                <div class="mt-5 flex justify-end gap-3">
                    <x-ui.button variant="ghost" wire:click="closeDailyName">Cancelar</x-ui.button>
                    <x-ui.button wire:click="saveDailyName">Salvar</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
