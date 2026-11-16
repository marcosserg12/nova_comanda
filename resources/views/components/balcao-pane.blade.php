@props(['current' => 'balcao'])
<div x-data="balcaoPane" @edit-order.window="tab = 'novo'" @in-kitchen-count.window="kitchenCount = $event.detail.count"
     class="@container/pane relative flex h-full min-w-0 flex-col bg-nana-cream">
    <x-ui.topbar :current="$current">
        <x-slot:tabs>
            <button type="button" @click="tab = 'novo'" :aria-pressed="(tab === 'novo').toString()"
                    :class="tab === 'novo' ? 'bg-nana-cream text-nana-red' : 'text-[#FFE3B0] hover:bg-white/10'"
                    class="min-h-11 rounded-md px-3 font-extrabold">Novo pedido</button>
            <button type="button" @click="tab = 'cozinha'" :aria-pressed="(tab === 'cozinha').toString()"
                    :class="tab === 'cozinha' ? 'bg-nana-cream text-nana-red' : 'text-[#FFE3B0] hover:bg-white/10'"
                    class="inline-flex min-h-11 items-center gap-2 rounded-md px-3 font-extrabold @min-[1280px]/pane:hidden">
                Na cozinha
                <span x-show="kitchenCount > 0" x-cloak x-text="kitchenCount" class="rounded-full bg-nana-ink px-2 py-0.5 text-sm text-nana-cream"></span>
            </button>
            <button type="button" @click="tab = 'hoje'" :aria-pressed="(tab === 'hoje').toString()"
                    :class="tab === 'hoje' ? 'bg-nana-cream text-nana-red' : 'text-[#FFE3B0] hover:bg-white/10'"
                    class="min-h-11 rounded-md px-3 font-extrabold">
                <span class="hidden @min-[1024px]/pane:inline">Pedidos de hoje</span><span class="@min-[1024px]/pane:hidden">Hoje</span>
            </button>
        </x-slot:tabs>
        <x-slot:end>{{ $topbarEnd ?? '' }}</x-slot:end>
    </x-ui.topbar>

    <div class="flex min-h-0 flex-1 gap-4 p-4">
        <div x-show="tab === 'novo'" class="min-h-0 min-w-0 flex-1"><livewire:balcao.order-form /></div>
        <div x-show="tab === 'hoje'" x-cloak class="min-h-0 min-w-0 flex-1"><livewire:balcao.today-orders /></div>
        <aside data-in-kitchen-column class="hidden min-h-0 shrink-0 @min-[1280px]/pane:block @min-[1280px]/pane:w-[17cqw]"
               :class="{ 'block! min-w-0 flex-1 @min-[1280px]/pane:flex-none': tab === 'cozinha' }">
            <livewire:balcao.in-kitchen-list />
        </aside>
    </div>
</div>
