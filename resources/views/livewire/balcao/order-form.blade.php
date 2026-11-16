@use('App\Support\Money')
<div x-data="orderDraft" @focus-name.window="$refs.name?.focus()" class="flex h-full min-h-0 gap-4">
    {{-- Produtos --}}
    <section class="flex min-w-0 flex-1 flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($this->categories as $category)
                @php($active = $categoryId === $category->id && trim($search) === '')
                <button type="button" wire:key="cat-{{ $category->id }}" wire:click="selectCategory({{ $category->id }})"
                    @class(['rounded-xl px-5 py-3 text-lg font-bold', 'bg-nana-red text-white' => $active, 'bg-white shadow-sm' => ! $active])>
                    {{ $category->name }}
                </button>
            @endforeach

            <input type="search" wire:model.live.debounce.150ms="search" @keydown.enter.prevent="$wire.addFirstSearchResult()"
                   placeholder="Buscar produto…" class="ml-auto w-56 rounded-xl border border-stone-300 px-4 py-3 text-lg">

            <button type="button" wire:click="toggleSoldOutMode"
                @class(['rounded-xl px-4 py-3 font-bold', 'bg-amber-500 text-white' => $soldOutMode, 'bg-white text-stone-600 shadow-sm' => ! $soldOutMode])>
                {{ $soldOutMode ? 'Concluir esgotados' : 'Marcar esgotado' }}
            </button>
        </div>

        @if ($soldOutMode)
            <p class="rounded-lg bg-amber-100 px-4 py-2 font-semibold text-amber-900">Toque num produto para marcar ou desmarcar como esgotado hoje.</p>
        @endif

        <div class="grid min-h-0 grid-cols-2 content-start gap-3 overflow-y-auto xl:grid-cols-3">
            @foreach ($this->products as $product)
                @php($soldOut = $product->isSoldOut())
                <div wire:key="prod-{{ $product->id }}" class="relative">
                    <button type="button"
                        wire:click="{{ $soldOutMode ? "toggleSoldOut({$product->id})" : "addProduct({$product->id})" }}"
                        @disabled($soldOut && ! $soldOutMode)
                        @class([
                            'flex w-full flex-col items-start rounded-2xl p-4 text-left shadow-sm transition',
                            'bg-white hover:ring-4 hover:ring-nana-yellow' => ! $soldOut,
                            'bg-stone-200 text-stone-400' => $soldOut,
                        ])>
                        <span @class(['text-lg font-bold leading-tight', 'line-through' => $soldOut])>{{ $product->displayName() }}</span>
                        @if ($product->needsDailyName())
                            <span class="mt-1 text-sm font-semibold text-nana-red">Definir prato de hoje</span>
                        @endif
                        <span class="mt-2 text-xl font-black">{{ Money::format($product->price_cents) }}</span>
                        @if ($soldOut)
                            <span class="mt-1 text-sm font-black uppercase">Esgotado</span>
                        @endif
                    </button>
                    @if ($product->has_daily_name && ! $product->needsDailyName() && ! $soldOutMode)
                        <button type="button" wire:click="editDailyName({{ $product->id }})" title="Trocar prato de hoje"
                                class="absolute right-2 top-2 rounded-lg bg-stone-100 px-2 py-1 text-sm">✎</button>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Pedido --}}
    <form wire:submit="submit" class="flex w-[26rem] shrink-0 flex-col gap-3 rounded-2xl bg-white p-4 shadow">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-black">{{ $editingOrderId ? 'Alterando pedido' : 'Novo pedido' }}</h2>
            @if ($editingOrderId)
                <button type="button" wire:click="cancelEdit" class="text-sm font-semibold text-stone-500 underline">cancelar alteração</button>
            @endif
        </div>

        <label class="block">
            <span class="text-sm font-semibold uppercase text-stone-500">Nome do cliente</span>
            <input type="text" wire:model="customerName" x-ref="name" autofocus autocomplete="off" maxlength="60"
                   class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3 text-xl">
        </label>
        @error('customerName') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror

        <label class="flex items-center gap-3 text-lg font-semibold">
            <input type="checkbox" wire:model="takeaway" class="h-7 w-7 rounded text-nana-red"> Para viagem
        </label>

        <ul class="min-h-0 flex-1 space-y-3 overflow-y-auto">
            @forelse ($lines as $i => $line)
                <li wire:key="line-{{ $line['key'] }}" class="rounded-xl border border-stone-200 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-lg font-bold leading-tight">{{ $line['name'] }}</p>
                            <p class="text-stone-500">{{ Money::format($line['unit_price_cents'] * $line['quantity']) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="decrement('{{ $line['key'] }}')" class="h-10 w-10 rounded-lg bg-stone-100 text-2xl font-bold">−</button>
                            <span class="w-6 text-center text-xl font-black">{{ $line['quantity'] }}</span>
                            <button type="button" wire:click="increment('{{ $line['key'] }}')" class="h-10 w-10 rounded-lg bg-stone-100 text-2xl font-bold">+</button>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($this->quickNotes->get($line['category_id'], collect()) as $note)
                            @php($on = in_array($note->label, $line['chips'], true))
                            <button type="button" wire:key="chip-{{ $line['key'] }}-{{ $note->id }}"
                                    wire:click="toggleChip('{{ $line['key'] }}', @js($note->label))"
                                    @class(['rounded-full px-3 py-1 text-sm font-bold', 'bg-nana-ink text-white' => $on, 'bg-stone-100' => ! $on])>
                                {{ $note->label }}
                            </button>
                        @endforeach
                    </div>
                    <input type="text" wire:model="lines.{{ $i }}.note" placeholder="Outra observação…" maxlength="120"
                           class="mt-2 w-full rounded-lg border border-stone-300 px-3 py-2">
                    <button type="button" wire:click="remove('{{ $line['key'] }}')" class="mt-2 text-sm font-semibold text-red-700">remover</button>
                </li>
            @empty
                <li class="py-10 text-center text-stone-400">Toque nos produtos para montar o pedido.</li>
            @endforelse
        </ul>

        @if ($errors->hasAny(['lines', 'items', 'items.*', 'order']))
            <div class="rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700">
                @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        <div class="flex items-center justify-between border-t border-stone-200 pt-3 text-2xl font-black">
            <span>Total</span><span>{{ Money::format($this->totalCents) }}</span>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                class="rounded-2xl bg-nana-red py-5 text-2xl font-black text-white disabled:opacity-60">
            <span wire:loading.remove wire:target="submit">{{ $editingOrderId ? 'SALVAR ALTERAÇÃO' : 'ENVIAR PARA COZINHA' }}</span>
            <span wire:loading wire:target="submit">Enviando…</span>
        </button>
    </form>

    {{-- Prato de hoje --}}
    @if ($dailyNameProductId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50">
            <div class="w-[28rem] rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-2xl font-black">Qual é o prato de hoje?</h3>
                <input type="text" wire:model="dailyNameInput" wire:keydown.enter.prevent="saveDailyName" autofocus maxlength="60"
                       placeholder="Ex.: Strogonoff" class="mt-4 w-full rounded-xl border border-stone-300 px-4 py-3 text-xl">
                @error('dailyNameInput') <p class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                <div class="mt-4 flex justify-end gap-3">
                    <button type="button" wire:click="closeDailyName" class="rounded-xl px-4 py-3 font-semibold">Cancelar</button>
                    <button type="button" wire:click="saveDailyName" class="rounded-xl bg-nana-red px-6 py-3 font-bold text-white">Salvar</button>
                </div>
            </div>
        </div>
    @endif
</div>
