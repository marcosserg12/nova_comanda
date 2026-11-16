<div x-data="toasts" @toast.window="push($event.detail)"
     class="pointer-events-none fixed left-4 top-4 z-50 flex max-w-xl flex-col gap-2">
    <template x-for="toast in items" :key="toast.id">
        <div class="pointer-events-auto flex items-center gap-4 rounded-lg border-2 border-nana-ink px-5 py-3 text-lg font-bold text-white shadow-hard"
             :class="toast.type === 'error' ? 'bg-nana-red' : 'bg-nana-green'">
            <span x-text="toast.message"></span>
            <template x-if="toast.action">
                <button type="button" class="min-h-11 rounded-md border-2 border-white/70 px-3"
                        @click="toast.action.run(); dismiss(toast.id)" x-text="toast.action.label"></button>
            </template>
        </div>
    </template>
</div>
