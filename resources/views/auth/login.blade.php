<x-layouts.app title="Entrar" body-class="bg-nana-red">
    <main class="flex min-h-dvh items-center justify-center p-4">
        <form method="POST" action="{{ route('login.store') }}"
              class="w-full max-w-md rounded-2xl border-3 border-nana-ink bg-nana-cream p-8 shadow-[8px_8px_0_var(--color-nana-ink)]">
            @csrf
            <h1 class="font-display text-4xl leading-none text-nana-red">{{ \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</h1>
            <p class="mt-2 text-lg font-semibold text-nana-muted">Entre para usar o balcão, a cozinha ou a administração.</p>

            <label class="mt-6 block">
                <span class="text-sm font-extrabold uppercase tracking-wider text-nana-muted">Usuário</span>
                <input name="username" value="{{ old('username') }}" autofocus autocomplete="username"
                       class="mt-1.5 block h-14 w-full rounded-lg border-3 border-nana-ink bg-white px-4 text-xl font-bold">
            </label>

            <label class="mt-4 block">
                <span class="text-sm font-extrabold uppercase tracking-wider text-nana-muted">Senha</span>
                <input type="password" name="password" autocomplete="current-password"
                       class="mt-1.5 block h-14 w-full rounded-lg border-3 border-nana-ink bg-white px-4 text-xl font-bold">
            </label>

            <label class="mt-5 flex min-h-11 max-lg:min-h-12 cursor-pointer items-center gap-3 text-lg font-bold">
                <input type="checkbox" name="remember" value="1" checked class="size-6 rounded border-3 border-nana-ink text-nana-yellow">
                Manter conectado neste aparelho
            </label>

            @error('username') <p class="mt-3 text-base font-bold text-nana-red">{{ $message }}</p> @enderror
            @error('password') <p class="mt-3 text-base font-bold text-nana-red">{{ $message }}</p> @enderror

            <x-ui.button type="submit" variant="dark" size="lg" class="mt-6 w-full">Entrar</x-ui.button>
        </form>
    </main>
</x-layouts.app>
