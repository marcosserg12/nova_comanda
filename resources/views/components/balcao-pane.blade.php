<div x-data="{ tab: 'novo' }" @edit-order.window="tab = 'novo'" class="flex h-screen min-w-0 flex-col gap-3 p-4">
    <header class="flex items-center gap-3">
        <span class="text-2xl font-black text-nana-red">Nana Comanda</span>
        <nav class="ml-4 flex gap-2">
            <button type="button" @click="tab = 'novo'" :class="tab === 'novo' ? 'bg-nana-ink text-white' : 'bg-white'"
                    class="rounded-xl px-5 py-2 text-lg font-bold shadow-sm">Novo pedido</button>
            <button type="button" @click="tab = 'hoje'" :class="tab === 'hoje' ? 'bg-nana-ink text-white' : 'bg-white'"
                    class="rounded-xl px-5 py-2 text-lg font-bold shadow-sm">Pedidos de hoje</button>
        </nav>
        <div class="ml-auto flex items-center gap-4">
            <x-connection-dot />
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm font-semibold text-stone-500 underline">Sair</button>
            </form>
        </div>
    </header>
    <div class="flex min-h-0 flex-1 gap-4">
        <div class="min-w-0 flex-1">
            <div x-show="tab === 'novo'" class="h-full"><livewire:balcao.order-form /></div>
            <div x-show="tab === 'hoje'" x-cloak class="h-full"><livewire:balcao.today-orders /></div>
        </div>
        <aside class="w-72 shrink-0"><livewire:balcao.in-kitchen-list /></aside>
    </div>
</div>
