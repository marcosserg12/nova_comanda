<div x-data="toasts" @toast.window="push($event.detail)"
     class="pointer-events-none fixed left-4 top-4 z-50 flex max-w-xl flex-col gap-2">
    <template x-for="toast in items" :key="toast.id">
        <div class="pointer-events-auto flex items-center gap-4 rounded-xl px-5 py-3 text-lg font-semibold shadow-lg"
             :class="toast.type === 'error' ? 'bg-red-700 text-white' : 'bg-emerald-600 text-white'">
            <span x-text="toast.message"></span>
            <template x-if="toast.action">
                <button type="button" class="rounded-lg bg-white/20 px-3 py-1"
                        @click="toast.action.run(); dismiss(toast.id)" x-text="toast.action.label"></button>
            </template>
        </div>
    </template>
</div>
