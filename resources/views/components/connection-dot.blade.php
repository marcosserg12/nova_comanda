<span x-data class="inline-flex items-center gap-2 text-sm font-semibold">
    <span class="h-3 w-3 rounded-full" :class="$store.conn.online ? 'bg-emerald-500' : 'animate-pulse bg-red-600'"></span>
    <span x-text="$store.conn.online ? 'Online' : 'Sem conexão'"></span>
</span>
