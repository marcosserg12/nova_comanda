<x-layouts.app title="Entrar" body-class="bg-nana-red">
    <main class="flex min-h-screen items-center justify-center p-4">
        <form method="POST" action="{{ route('login.store') }}" class="w-full max-w-sm space-y-4 rounded-3xl bg-white p-8 shadow-2xl">
            @csrf
            <h1 class="text-center text-3xl font-black text-nana-red">Nana Comanda</h1>

            <label class="block">
                <span class="text-sm font-semibold uppercase text-stone-500">Usuário</span>
                <input name="username" value="{{ old('username') }}" autofocus autocomplete="username"
                       class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3 text-lg">
            </label>

            <label class="block">
                <span class="text-sm font-semibold uppercase text-stone-500">Senha</span>
                <input type="password" name="password" autocomplete="current-password"
                       class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3 text-lg">
            </label>

            <label class="flex items-center gap-2 text-stone-700">
                <input type="checkbox" name="remember" value="1" checked class="h-5 w-5 rounded">
                Manter conectado neste aparelho
            </label>

            @error('username') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
            @error('password') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror

            <button class="w-full rounded-xl bg-nana-ink py-4 text-lg font-black text-white">Entrar</button>
        </form>
    </main>
</x-layouts.app>
