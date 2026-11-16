# Nana Comanda — Redesenho das telas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle every operational screen in the approved "Lanchonete marcante" look, sized for the 18.5" 1366×768 counter monitor and for tablets, and add a home screen (tela de início) with a per-device "open always" choice, a "Telas" menu on every screen, a three-column counter, a kitchen grid that highlights new/edited orders silently, monitor detection on the Estação, and the new TV/phone/login visuals, without changing any business rule.

**Architecture:** Visual tokens live in the Tailwind 4 `@theme` of `resources/css/app.css`; fonts are bundled with `@fontsource` through Vite. Small anonymous Blade components under `components/ui/` (topbar, button, chip, badge, card, screens-menu, icon) are reused by the existing Livewire 4 views; a pure PHP `App\Support\Launchpad` builds the list of screens per profile for the home screen and the "Telas" menu. The counter pane is a CSS container (`@container/pane`), so the same markup switches between 3 columns, 2 columns + tab, and 1 column + bottom bar by its own width, whether it is standalone or the left half of the Estação.

**Tech Stack:** PHP 8.3, Laravel 13.42, Livewire 4.4 (bundled Alpine 3.17 with the focus plugin), Filament 5.11, Tailwind CSS 4.3 via `@tailwindcss/vite` (+ `@tailwindcss/forms`), Vite 8 + `laravel-vite-plugin` 3.2, `@fontsource/archivo` 5.3 and `@fontsource/archivo-black` 5.3, Laravel Reverb + Echo, Pest 4.7 with `pest-plugin-livewire` 4.1, Playwright (local install) for browser checks.

**Spec:** docs/superpowers/specs/2026-11-16-redesenho-telas-design.md (mockups: docs/superpowers/specs/mockups/2026-11-16-redesenho/)

## Global Constraints

- Project root `c:\laragon\www\nana-comanda`, branch `main`, remote `origin` (`https://github.com/marcosserg12/nova_comanda.git`). Shell: Git Bash on Windows.
- Never read `.env`. Never read from or write to `c:\laragon\www\Comanda_refatorada` or `c:\laragon\www\Comanda`.
- All user-facing copy is Brazilian Portuguese; code identifiers are English.
- Tokens (exact): `--color-nana-red: #D62828`, `--color-nana-cream: #FFF2D9`, `--color-nana-ink: #161616`, `--color-nana-yellow: #FFB703`, `--color-nana-green: #1F8A50`, `--color-nana-orange: #E8590C`, `--color-nana-muted: #6B6257`, `--color-nana-line: #E7D9C2`.
- Mockup-derived shades used only as Tailwind arbitrary values: `#FFF7E6` (product in the order), `#FFE9E9` (late kitchen card), `#FFE08A` (NOVO pulse peak), `#7CFFB2` (online dot on red), `#FFE3B0` (muted text on red), `#CFC3AD` (muted text on ink).
- Fonts: `Archivo` (weights 500, 600, 700, 800) is `font-sans`; `Archivo Black` (400) is `font-display` for titles, order numbers and prices. Both are bundled with `@fontsource/archivo` and `@fontsource/archivo-black` through Vite. No Google Fonts or Bunny Fonts request at runtime. Never put a heavy weight class on `font-display` text (`html { font-synthesis-weight: none }` prevents faux bold).
- Surfaces: white cards, `border-2 border-nana-ink`, radius 8–10 px (`rounded-lg`), hard shadow `shadow-hard` (4px 4px 0 ink) or `shadow-hard-sm` (2px 2px 0 ink). Disabled or sold-out: `opacity-45`, no shadow.
- Touch targets: at least 44 px (`min-h-11` / `size-11`), and 48 px below 1024 px of viewport width (`max-lg:min-h-12` / `max-lg:size-12`) on shared controls. Visible focus: 3 px yellow outline (global `:focus-visible` rule).
- `prefers-reduced-motion: reduce` turns the NOVO pulse into a fixed yellow outline (built into the `nana-highlight` utility).
- Kitchen: NO sound and NO full-screen flash. The `kitchen-beep` event and the kitchen's use of `beep()` disappear. The TV keeps its "Toque para começar" gate, its chime and its announcer queue.
- Every existing Livewire binding and behavior is preserved:
  - the counter draft survives F5 (`x-data="orderDraft"`, `restoreDraft`, `draft-clear`);
  - the daily-dish prompt (`dailyNameProductId`, `saveDailyName`, `editDailyName`);
  - search with Enter (`wire:model.live.debounce.150ms="search"` + `$wire.addFirstSearchResult()`);
  - sold-out mode (`toggleSoldOutMode`, `toggleSoldOut`);
  - order edit with the no-overwrite guard (`edit-order` event, `loadOrder`, `cancelEdit`);
  - cancel with reason (`startCancel`, `cancelReason`, `cancelReasonOther`, `confirmCancel`, `closeCancel`);
  - waiting time in "Pedidos de hoje" (`· há N min`);
  - kitchen numpad (digits, `NumpadEnter`, `NumpadAdd`, `NumpadDecimal`, `NumpadSubtract`), ready by id/number and the 10 s undo;
  - kitchen buttons never steal the counter's focus (`tabindex="-1"` + `@pointerdown.prevent` + `@mousedown.prevent`; the kitchen card itself also gets `@pointerdown.prevent @mousedown.prevent`);
  - TV announcer queue + chime + device gate (TV only);
  - phone vibration and the remembered number (`localStorage` key `nana.mine`).
- Every Livewire action that changes data keeps its `Gate::authorize()` call. State changes still go through `App\Actions\Orders\*`.
- Public screens (`/painel`, `/acompanhar`) expose only the order id, number, first name and status.
- R11: `orders.business_date` is filtered only through `whereDate()` / the `Order::today()` scope.
- PHP style: run `vendor/bin/pint --dirty --format agent` after changing PHP files.
- Every commit message ends with the trailer line `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>` (passed as a second `-m`).
- Implementers commit and run `git push origin main`, but never deploy and never `ssh` (the controller deploys after review).
- A dev server may already be running on `http://127.0.0.1:8000` and Reverb on `:8080`. Reuse them for browser checks. Never kill processes you did not start; never `taskkill php.exe`. If `public/hot` does not exist, run `npm run build` before a browser check so the server serves the new assets.
- Browser checks log in with a local admin user taken from the env vars `NANA_E2E_USER` and `NANA_E2E_PASSWORD` (ask the controller for them; never print them, never hardcode them). The login route allows 5 attempts per minute, so scripts log in once and reuse the storage state (`loginState()` in `tests/Browser/support.cjs`).

---

## File Map

```
app/
  Support/Screen.php                      (new) readonly DTO of one screen
  Support/Launchpad.php                   (new) screens per profile
  Http/Controllers/HomeController.php     (mod) renders the home screen
  Livewire/Balcao/OrderForm.php           (mod) orderNumber, cartQuantities, itemCount
  Livewire/Cozinha/Board.php              (mod) kitchen-highlight, lateness limits
  Livewire/Painel/PublicBoard.php         (mod) TV caps (12 preparing, 4 ready)
  Models/Setting.php                      (mod) station widths default 1366
  Filament/Pages/SettingsPage.php         (mod) property defaults 1366
config/comanda.php                        (mod) kitchen_warn_minutes, kitchen_late_minutes
package.json, package-lock.json           (mod) @fontsource/archivo, @fontsource/archivo-black
vite.config.js                            (mod) drop the unused Bunny font
resources/css/app.css                     (mod) tokens, shadows, nana-highlight, focus, reduced motion
resources/js/
  app.js                                  (mod) font imports, new registrations
  launcher.js (new) · balcao.js (new) · station.js (new)
  kitchen.js · device.js · announcer.js   (mod)
resources/views/
  components/ui/{topbar,button,chip,badge,card,screens-menu,icon}.blade.php   (new)
  components/{layouts/app,connection-dot,toasts,device-gate,balcao-pane}.blade.php (mod)
  pages/inicio.blade.php                  (new)
  pages/{balcao,cozinha,estacao,painel,acompanhar}.blade.php (mod)
  auth/login.blade.php                    (mod)
  livewire/balcao/{order-form,in-kitchen-list,today-orders}.blade.php (mod)
  livewire/cozinha/board.blade.php        (mod)
  livewire/painel/{tv,mobile}.blade.php   (mod)
  welcome.blade.php                       (deleted: unused, depended on the Bunny font manifest)
tests/
  Browser/support.cjs (new) · Browser/screenshots.cjs (new)
  Feature/Support/LaunchpadTest.php (new) · Feature/Ui/ThemeTest.php (new) · Feature/Pages/HomeTest.php (new)
  Feature/Ui/ComponentsTest.php · Feature/Models/SettingTest.php · Feature/Admin/ReportsAndSettingsTest.php
  Feature/Auth/AccessTest.php · Feature/Auth/LoginTest.php · Feature/Pages/StationTest.php
  Feature/Livewire/{OrderFormTest,CounterListsTest,KitchenBoardTest,PublicBoardTest}.php   (mod)
docs/validacoes.md                        (mod) section 5
```

Data attributes used by the browser checks (each task that renders them owns them): `data-screen`, `data-screen-link`, `data-product-grid`, `data-product-tile`, `data-in-cart`, `data-order-panel`, `data-order-total`, `data-send-button`, `data-order-bar`, `data-takeaway-option`, `data-in-kitchen-column`, `data-in-kitchen-count`, `data-in-kitchen-order`, `data-kitchen-grid`, `data-kitchen-state`, `data-kitchen-more`, `data-ready-button`, `data-station-left`, `data-station-right`, `data-configured-left-width`, `data-tv-preparing`, `data-tv-overflow`, `data-tv-ready`, `data-my-order`, `data-phone-preparing`.

Inline browser-check scripts in this plan contain no backslashes on purpose (they run through `node - <<'EOF'` in Git Bash); select elements by these data attributes, never by escaped attribute names such as `wire:key`.

---

### Task 1: Visual foundation and screen registry

**Files:**
- Create: `app/Support/Screen.php`, `app/Support/Launchpad.php`, `resources/views/components/ui/topbar.blade.php`, `resources/views/components/ui/button.blade.php`, `resources/views/components/ui/chip.blade.php`, `resources/views/components/ui/badge.blade.php`, `resources/views/components/ui/card.blade.php`, `resources/views/components/ui/screens-menu.blade.php`, `resources/views/components/ui/icon.blade.php`, `tests/Browser/support.cjs`
- Modify: `package.json`, `package-lock.json`, `vite.config.js`, `resources/css/app.css`, `resources/js/app.js`, `resources/views/components/layouts/app.blade.php`, `resources/views/components/connection-dot.blade.php`, `resources/views/components/toasts.blade.php`, `config/comanda.php`, `app/Models/Setting.php`, `app/Filament/Pages/SettingsPage.php`
- Delete: `resources/views/welcome.blade.php`
- Test: `tests/Feature/Support/LaunchpadTest.php` (new), `tests/Feature/Ui/ThemeTest.php` (new), `tests/Feature/Ui/ComponentsTest.php`, `tests/Feature/Models/SettingTest.php`, `tests/Feature/Admin/ReportsAndSettingsTest.php`

**Interfaces:**
- Consumes: `App\Enums\Role`, `App\Models\User::hasRole()`, `User::canAccessPanel()`, `Filament\Facades\Filament::getPanel('admin')` (verified: `FilamentManager::getPanel(?string $id = null, bool $isStrict = true): ?Panel`, `Panel::getPath(): string` returns `'admin'`), routes `home`, `login`, `logout`, `estacao`, `balcao`, `cozinha`, `painel`, `acompanhar`, the Alpine store `$store.conn` from `resources/js/connection.js`.
- Produces:
  - Tailwind utilities: `bg-/text-/border-nana-{red,cream,ink,yellow,green,orange,muted,line}`, `font-sans` (Archivo), `font-display` (Archivo Black), `shadow-hard`, `shadow-hard-sm` (from `@theme --shadow-*`, so they compose with `ring-*`), `nana-highlight` (6 px yellow outline pulsing with the `nana-pulse` keyframes via `--animate-nana-pulse`; fixed outline under reduced motion). Verified against `node_modules/tailwindcss` 4.3.5: container variants `@container/pane`, `@min-[1024px]/pane:*` → `@container pane (width >= 1024px)`, `@max-[1024px]/pane:*` → `(width < 1024px)`, important suffix `flex!`, `border-3`, `opacity-45`, `-outline-offset-8`.
  - `App\Support\Screen` — `final readonly class Screen { __construct(public string $key, public string $url, public string $label, public string $description, public string $icon, public ?string $badge = null) }`.
  - `App\Support\Launchpad::for(?User $user): array` (list<Screen>). Keys in order: admin `estacao, balcao, cozinha, painel, acompanhar, admin`; balcao `estacao, balcao, cozinha, painel, acompanhar`; cozinha `cozinha, painel, acompanhar`; guest `painel, acompanhar, login`.
  - `<x-ui.topbar :title? :current? :screens?>` with optional slots `badge`, `tabs`, `end`. `title` defaults to `Setting::get(Setting::RESTAURANT_NAME)`; `current` is a Screen key highlighted in the menu; `screens` defaults to `Launchpad::for(auth()->user())`.
  - `<x-ui.button variant="primary|dark|ghost|success" size="md|lg" type="button" :href?>`.
  - `<x-ui.chip :active>` (renders `<button type="button" aria-pressed>`).
  - `<x-ui.badge tone="yellow|ink|red|green|outline">`.
  - `<x-ui.card padding="p-4">`.
  - `<x-ui.screens-menu :screens? :current? :compact :up align="left|right">` — "Telas" dropdown: one link per allowed screen (`data-screen-link="{key}"`), "Tela de início" (`/?escolher=1`, `data-screen-link="inicio"`), and "Sair" for logged users.
  - `<x-ui.icon name="station|counter|kitchen|tv|phone|admin|login|menu">`.
  - `<x-connection-dot tone="dark|light">` (same `$store.conn.online` behavior).
  - `x-layouts.app` calls `Livewire::forceAssetInjection()` (verified: `LivewireManager::forceAssetInjection()` sets `SupportAutoInjectedAssets::$forceAssetInjection`), so pages without a Livewire component (home, login) still get Livewire's script and its bundled Alpine; without it Livewire injects its assets only when a component renders.
  - `config('comanda.kitchen_warn_minutes') === 15`, `config('comanda.kitchen_late_minutes') === 25`.
  - `Setting::DEFAULTS[station_left_width] === 1366`, `Setting::DEFAULTS[station_right_width] === 1366`.
  - `tests/Browser/support.cjs` exports `BASE_URL, fail, launch, loginState, open, expectNoErrors, expectNoHorizontalScroll, expectInViewport, expectLastProductReachable, kitchenStatus, placeOrder` (the last three read data attributes produced in Tasks 3 and 5).

- [ ] **Step 1: Write the failing tests.**

Create `tests/Feature/Support/LaunchpadTest.php`:

```php
<?php

use App\Enums\Role;
use App\Support\Launchpad;
use App\Support\Screen;

it('lists the screens each profile can open, in order', function (?Role $role, array $keys) {
    $user = $role === null ? null : userWithRole($role);

    expect(array_map(fn (Screen $screen) => $screen->key, Launchpad::for($user)))->toBe($keys);
})->with([
    'admin' => [Role::Admin, ['estacao', 'balcao', 'cozinha', 'painel', 'acompanhar', 'admin']],
    'balcao' => [Role::Balcao, ['estacao', 'balcao', 'cozinha', 'painel', 'acompanhar']],
    'cozinha' => [Role::Cozinha, ['cozinha', 'painel', 'acompanhar']],
    'visitante' => [null, ['painel', 'acompanhar', 'login']],
]);

it('points every screen to its route with pt-BR labels and tags', function () {
    $screens = collect(Launchpad::for(userWithRole(Role::Admin)))->keyBy('key');

    expect($screens['estacao']->url)->toBe(route('estacao'))
        ->and($screens['estacao']->label)->toBe('Estação')
        ->and($screens['estacao']->badge)->toBe('PC do balcão')
        ->and($screens['balcao']->url)->toBe(route('balcao'))
        ->and($screens['balcao']->label)->toBe('Balcão')
        ->and($screens['cozinha']->url)->toBe(route('cozinha'))
        ->and($screens['painel']->url)->toBe(route('painel'))
        ->and($screens['painel']->label)->toBe('Painel da TV')
        ->and($screens['acompanhar']->url)->toBe(route('acompanhar'))
        ->and($screens['admin']->url)->toBe(url('/admin'))
        ->and($screens['admin']->label)->toBe('Administração')
        ->and($screens['admin']->badge)->toBe('Só admin');

    $login = collect(Launchpad::for(null))->firstWhere('key', 'login');
    expect($login->url)->toBe(route('login'))->and($login->label)->toBe('Entrar');
});

it('reads the kitchen lateness limits from config', function () {
    expect(config('comanda.kitchen_warn_minutes'))->toBe(15)
        ->and(config('comanda.kitchen_late_minutes'))->toBe(25);
});
```

Create `tests/Feature/Ui/ThemeTest.php`:

```php
<?php

it('defines the redesign tokens in the Tailwind theme', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    foreach ([
        '--color-nana-red: #D62828;',
        '--color-nana-cream: #FFF2D9;',
        '--color-nana-ink: #161616;',
        '--color-nana-yellow: #FFB703;',
        '--color-nana-green: #1F8A50;',
        '--color-nana-orange: #E8590C;',
        '--color-nana-muted: #6B6257;',
        '--color-nana-line: #E7D9C2;',
        "--font-sans: 'Archivo'",
        "--font-display: 'Archivo Black'",
        '--shadow-hard: 4px 4px 0 var(--color-nana-ink);',
        '--shadow-hard-sm: 2px 2px 0 var(--color-nana-ink);',
        '@keyframes nana-pulse',
        '@utility nana-highlight',
        'prefers-reduced-motion: reduce',
    ] as $token) {
        expect($css)->toContain($token);
    }
});

it('bundles the fonts instead of loading them from the internet', function () {
    $js = file_get_contents(resource_path('js/app.js'));

    expect($js)->toContain("import '@fontsource/archivo/500.css';")
        ->toContain("import '@fontsource/archivo/600.css';")
        ->toContain("import '@fontsource/archivo/700.css';")
        ->toContain("import '@fontsource/archivo/800.css';")
        ->toContain("import '@fontsource/archivo-black/400.css';")
        ->and(file_get_contents(base_path('vite.config.js')))->not->toContain('bunny')
        ->and(file_get_contents(resource_path('views/components/layouts/app.blade.php')))->not->toContain('fonts.googleapis');
});
```

Replace `tests/Feature/Ui/ComponentsTest.php` with:

```php
<?php

use App\Enums\Role;

it('renders the device gate button', function () {
    $this->blade('<x-device-gate />')
        ->assertSee('Toque para começar')
        ->assertSee('deviceGate(false)', false);

    $this->blade('<x-device-gate :fullscreen="true" />')->assertSee('deviceGate(true)', false);
});

it('renders the toast container and the connection dot', function () {
    $this->blade('<x-toasts />')->assertSee('x-data="toasts"', false);
    $this->blade('<x-connection-dot />')->assertSee('$store.conn.online', false);
    $this->blade('<x-connection-dot tone="light" />')->assertSee('bg-[#7CFFB2]', false);
});

it('includes toasts in the layout', function () {
    $this->get('/painel')->assertSee('x-data="toasts"', false);
});

it('loads Livewire and its Alpine on every layout page, even without a component', function () {
    $this->get('/login')->assertOk()->assertSee('data-update-uri', false);
});

it('renders the button variants and sizes', function () {
    $this->blade('<x-ui.button>Enviar</x-ui.button>')
        ->assertSee('<button type="button"', false)
        ->assertSee('bg-nana-red', false)
        ->assertSee('min-h-11', false)
        ->assertSee('Enviar');

    $this->blade('<x-ui.button variant="dark" size="lg" type="submit">Entrar</x-ui.button>')
        ->assertSee('type="submit"', false)
        ->assertSee('bg-nana-ink', false)
        ->assertSee('font-display', false);

    $this->blade('<x-ui.button variant="ghost">Voltar</x-ui.button>')
        ->assertSee('bg-white', false)
        ->assertDontSee('shadow-hard', false);

    $this->blade('<x-ui.button variant="success">Pronto</x-ui.button>')->assertSee('bg-nana-green', false);

    $this->blade('<x-ui.button href="/painel">Painel</x-ui.button>')
        ->assertSee('<a href="/painel"', false)
        ->assertDontSee('<button', false);
});

it('renders chips, badges and cards in the lanchonete style', function () {
    $this->blade('<x-ui.chip :active="true">Pratos</x-ui.chip>')
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('bg-nana-yellow', false);

    $this->blade('<x-ui.chip>Sucos</x-ui.chip>')
        ->assertSee('aria-pressed="false"', false)
        ->assertSee('bg-white', false);

    $this->blade('<x-ui.badge tone="ink">VIAGEM</x-ui.badge>')->assertSee('bg-nana-ink', false)->assertSee('VIAGEM');
    $this->blade('<x-ui.badge tone="outline">NOVO</x-ui.badge>')->assertSee('bg-white', false);
    $this->blade('<x-ui.card>Conteúdo</x-ui.card>')->assertSee('shadow-hard', false)->assertSee('border-nana-ink', false);
});

it('lists only the screens the user can open in the Telas menu', function () {
    $this->actingAs(userWithRole(Role::Cozinha));

    $this->blade('<x-ui.screens-menu current="cozinha" />')
        ->assertSee('Telas')
        ->assertSee('data-screen-link="cozinha"', false)
        ->assertSee('data-screen-link="painel"', false)
        ->assertSee('data-screen-link="acompanhar"', false)
        ->assertDontSee('data-screen-link="estacao"', false)
        ->assertDontSee('data-screen-link="balcao"', false)
        ->assertDontSee('data-screen-link="admin"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('Tela de início')
        ->assertSee('?escolher=1', false)
        ->assertSee('Sair');
});

it('shows guests only the public screens and the login in the Telas menu', function () {
    $this->blade('<x-ui.screens-menu />')
        ->assertSee('data-screen-link="painel"', false)
        ->assertSee('data-screen-link="login"', false)
        ->assertDontSee('data-screen-link="cozinha"', false)
        ->assertDontSee('Sair');
});

it('renders the red topbar with the menu, tabs and connection status', function () {
    $this->actingAs(userWithRole(Role::Admin));

    $this->blade('<x-ui.topbar current="balcao"><x-slot:tabs><button type="button">Novo pedido</button></x-slot:tabs></x-ui.topbar>')
        ->assertSee('Nana Lanches')
        ->assertSee('bg-nana-red', false)
        ->assertSee('Novo pedido')
        ->assertSee('Telas')
        ->assertSee('$store.conn.online', false);

    $this->blade('<x-ui.topbar title="Cozinha" />')->assertSee('Cozinha');
});
```

Append to `tests/Feature/Models/SettingTest.php`:

```php

it('defaults the station widths to the 18.5 inch monitor', function () {
    expect(Setting::get(Setting::STATION_LEFT_WIDTH))->toBe(1366)
        ->and(Setting::get(Setting::STATION_RIGHT_WIDTH))->toBe(1366);
});
```

In `tests/Feature/Admin/ReportsAndSettingsTest.php`, in the test `survives an emptied number field without crashing or saving it`, replace

```php
        ->and((int) Setting::get(Setting::STATION_LEFT_WIDTH))->toBe(1920);
```

with

```php
        ->and((int) Setting::get(Setting::STATION_LEFT_WIDTH))->toBe(1366);
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Support/LaunchpadTest.php tests/Feature/Ui tests/Feature/Models/SettingTest.php tests/Feature/Admin/ReportsAndSettingsTest.php`
Expected: FAIL — `Class "App\Support\Launchpad" not found`, `Unable to locate a class or view for component [ui.button]`, missing tokens in `app.css`, `1920` is not `1366`, `config('comanda.kitchen_warn_minutes')` is `null`, and `/login` has no Livewire script (`data-update-uri`).

- [ ] **Step 3: Install the fonts.**

```bash
npm install -D @fontsource/archivo@^5.3.0 @fontsource/archivo-black@^5.3.0
git diff --stat package.json package-lock.json
```
Expected: `package.json` gains the two devDependencies. If the Windows npm also drops Linux-only optional entries from `package-lock.json`, keep the file as npm wrote it: `deploy/deploy.sh` already falls back to `npm install --no-save` when `npm ci` refuses the lock.

- [ ] **Step 4: Drop the unused Bunny font.** Replace `vite.config.js` with:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

Delete `resources/views/welcome.blade.php` (no route renders it; its `@fonts` directive needs the Bunny font manifest that no longer exists):

```bash
git rm resources/views/welcome.blade.php
```

- [ ] **Step 5: Write the theme.** Replace `resources/css/app.css` with:

```css
@import 'tailwindcss';
@plugin '@tailwindcss/forms';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --font-sans: 'Archivo', ui-sans-serif, system-ui, sans-serif;
    --font-display: 'Archivo Black', 'Archivo', ui-sans-serif, system-ui, sans-serif;

    --color-nana-red: #D62828;
    --color-nana-cream: #FFF2D9;
    --color-nana-ink: #161616;
    --color-nana-yellow: #FFB703;
    --color-nana-green: #1F8A50;
    --color-nana-orange: #E8590C;
    --color-nana-muted: #6B6257;
    --color-nana-line: #E7D9C2;

    --shadow-hard: 4px 4px 0 var(--color-nana-ink);
    --shadow-hard-sm: 2px 2px 0 var(--color-nana-ink);

    --animate-nana-pulse: nana-pulse 1.2s ease-in-out infinite;

    @keyframes nana-pulse {
        50% {
            outline-color: #FFE08A;
        }
    }
}

/* "NOVO" / "ALTERADO" na cozinha: contorno amarelo pulsando; fixo para quem pede menos movimento. */
@utility nana-highlight {
    outline: 6px solid var(--color-nana-yellow);
    outline-offset: 2px;
    animation: var(--animate-nana-pulse);

    @media (prefers-reduced-motion: reduce) {
        animation: none;
    }
}

@layer base {
    html {
        font-synthesis-weight: none;
    }

    :focus-visible {
        outline: 3px solid var(--color-nana-yellow);
        outline-offset: 2px;
    }

    /* Beats the @tailwindcss/forms blue ring (its selectors are at most 0,2,0). */
    input:not([type='checkbox'], [type='radio']):focus,
    textarea:focus,
    select:focus {
        box-shadow: none;
        outline: 3px solid var(--color-nana-yellow);
        outline-offset: 1px;
    }
}

[x-cloak] {
    display: none !important;
}
```

- [ ] **Step 6: Import the fonts.** Fonts are imported from the JS entry: Vite rebases the `url()`s of the `@fontsource` CSS, emits the `.woff2` files under `public/build/assets`, and `@vite` prints a `<link rel="stylesheet">` for every CSS chunk of the JS entry (verified in `Illuminate\Foundation\Vite`, which iterates `$chunk['css']`). Replace `resources/js/app.js` with:

```js
import '@fontsource/archivo/500.css';
import '@fontsource/archivo/600.css';
import '@fontsource/archivo/700.css';
import '@fontsource/archivo/800.css';
import '@fontsource/archivo-black/400.css';
import './echo';
import { registerDevice } from './device';
import { registerToasts } from './toasts';
import { registerConnection } from './connection';
import { registerNetworkErrors } from './network';
import { registerOrderDraft } from './order-draft';
import { registerKitchen } from './kitchen';
import { registerAnnouncer } from './announcer';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    registerDevice(Alpine);
    registerToasts(Alpine);
    registerConnection(Alpine);
    registerOrderDraft(Alpine);
    registerKitchen(Alpine);
    registerAnnouncer(Alpine);
});

registerNetworkErrors();
```

- [ ] **Step 7: Config and settings.**

Replace `config/comanda.php` with:

```php
<?php

return [
    'backup_path' => env('BACKUP_PATH', storage_path('app/backups')),
    'backup_keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    /** Minutes after which a kitchen card shows its waiting time in orange. */
    'kitchen_warn_minutes' => 15,

    /** Minutes after which a kitchen card turns pink with a red time badge. */
    'kitchen_late_minutes' => 25,
];
```

In `app/Models/Setting.php`, replace

```php
        self::STATION_LEFT_WIDTH => 1920,
        self::STATION_RIGHT_WIDTH => 1920,
```

with

```php
        self::STATION_LEFT_WIDTH => 1366,
        self::STATION_RIGHT_WIDTH => 1366,
```

In `app/Filament/Pages/SettingsPage.php` (the values are overwritten in `mount()`; this only keeps the defaults consistent), replace

```php
    public ?int $stationLeftWidth = 1920;

    public ?int $stationRightWidth = 1920;
```

with

```php
    public ?int $stationLeftWidth = 1366;

    public ?int $stationRightWidth = 1366;
```

- [ ] **Step 8: The screen registry.**

Create `app/Support/Screen.php`:

```php
<?php

namespace App\Support;

/** One destination of the home screen and of the "Telas" menu. */
final readonly class Screen
{
    public function __construct(
        public string $key,
        public string $url,
        public string $label,
        public string $description,
        public string $icon,
        public ?string $badge = null,
    ) {}
}
```

Create `app/Support/Launchpad.php`:

```php
<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;

/** Builds the screens a profile can open. Mirrors the route middleware; it never grants access by itself. */
final class Launchpad
{
    /** @return list<Screen> */
    public static function for(?User $user): array
    {
        if ($user === null) {
            return [
                self::painel(),
                self::acompanhar(),
                new Screen('login', route('login'), 'Entrar', 'Para usar o balcão, a cozinha ou a administração', 'login'),
            ];
        }

        $screens = [];

        if ($user->hasRole(Role::Admin, Role::Balcao)) {
            $screens[] = new Screen('estacao', route('estacao'), 'Estação', 'Balcão e cozinha nos dois monitores', 'station', 'PC do balcão');
            $screens[] = new Screen('balcao', route('balcao'), 'Balcão', 'Só lançar e acompanhar pedidos', 'counter');
        }

        if ($user->hasRole(Role::Admin, Role::Balcao, Role::Cozinha)) {
            $screens[] = new Screen('cozinha', route('cozinha'), 'Cozinha', 'Pedidos para preparar e “Pronto”', 'kitchen');
        }

        $screens[] = self::painel();
        $screens[] = self::acompanhar();

        $panel = Filament::getPanel('admin');

        if ($user->canAccessPanel($panel)) {
            $screens[] = new Screen('admin', url($panel->getPath()), 'Administração', 'Preços, cardápio, vendas, usuários', 'admin', 'Só admin');
        }

        return $screens;
    }

    private static function painel(): Screen
    {
        return new Screen('painel', route('painel'), 'Painel da TV', 'Números preparando e prontos', 'tv');
    }

    private static function acompanhar(): Screen
    {
        return new Screen('acompanhar', route('acompanhar'), 'Acompanhar', 'Tela do cliente no celular (QR)', 'phone');
    }
}
```

- [ ] **Step 9: The UI components.**

Create `resources/views/components/ui/icon.blade.php`:

```blade
@props(['name'])
@php
    $paths = [
        'station' => '<rect x="2" y="4" width="9" height="12" rx="1"/><rect x="13" y="4" width="9" height="12" rx="1"/><path d="M6 20h12"/>',
        'counter' => '<path d="M4 7h16M4 12h16M4 17h10"/>',
        'kitchen' => '<path d="M6 13h12l-1 7H7z"/><path d="M8 13V9a4 4 0 0 1 8 0v4"/>',
        'tv' => '<rect x="2" y="4" width="20" height="13" rx="1"/><path d="M8 21h8M12 17v4"/>',
        'phone' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
        'admin' => '<path d="M4 21V10M10 21V4M16 21v-7M22 21H2"/>',
        'login' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    ];
@endphp
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>{!! $paths[$name] ?? '' !!}</svg>
```

Create `resources/views/components/ui/button.blade.php`:

```blade
@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'href' => null])
@php
    $classes = [
        'inline-flex items-center justify-center gap-2 rounded-lg border-2 border-nana-ink leading-none transition active:translate-x-0.5 active:translate-y-0.5 active:shadow-none disabled:opacity-45 disabled:shadow-none',
        match ($variant) {
            'dark' => 'bg-nana-ink text-nana-cream shadow-hard-sm',
            'ghost' => 'bg-white text-nana-ink',
            'success' => 'bg-nana-green text-white shadow-hard-sm',
            default => 'bg-nana-red text-white shadow-hard-sm',
        },
        match ($size) {
            'lg' => 'min-h-14 px-6 font-display text-xl',
            default => 'min-h-11 px-4 text-base font-extrabold max-lg:min-h-12',
        },
    ];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
```

Create `resources/views/components/ui/chip.blade.php`:

```blade
@props(['active' => false])
<button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" {{ $attributes->class([
    'inline-flex min-h-11 items-center rounded-full border-2 border-nana-ink px-4 text-base font-extrabold shadow-hard-sm max-lg:min-h-12',
    'bg-nana-yellow' => $active,
    'bg-white' => ! $active,
]) }}>{{ $slot }}</button>
```

Create `resources/views/components/ui/badge.blade.php`:

```blade
@props(['tone' => 'yellow'])
<span {{ $attributes->class([
    'inline-flex items-center rounded-md border-2 border-nana-ink px-2 py-0.5 text-sm font-extrabold leading-tight tracking-wide',
    match ($tone) {
        'ink' => 'bg-nana-ink text-nana-cream',
        'red' => 'bg-nana-red text-white',
        'green' => 'bg-nana-green text-white',
        'outline' => 'bg-white text-nana-ink',
        default => 'bg-nana-yellow text-nana-ink',
    },
]) }}>{{ $slot }}</span>
```

Create `resources/views/components/ui/card.blade.php`:

```blade
@props(['padding' => 'p-4'])
<div {{ $attributes->class(['rounded-lg border-2 border-nana-ink bg-white shadow-hard', $padding]) }}>{{ $slot }}</div>
```

Create `resources/views/components/ui/screens-menu.blade.php`:

```blade
@props(['screens' => null, 'current' => null, 'compact' => false, 'up' => false, 'align' => 'left'])
@php
    $screens ??= \App\Support\Launchpad::for(auth()->user());
    $itemClass = 'flex min-h-12 w-full items-center px-4 text-left font-bold hover:bg-nana-yellow focus-visible:bg-nana-yellow';
@endphp
<div x-data="{ open: false }" @keydown.escape="open = false; $refs.toggle.focus()" @click.outside="open = false" class="relative">
    <button type="button" x-ref="toggle" @click="open = ! open" aria-haspopup="menu" :aria-expanded="open.toString()" aria-label="Telas"
            @keydown.arrow-down.prevent="open = true; $nextTick(() => $focus.within($refs.items).first())"
            class="inline-flex min-h-11 items-center gap-2 rounded-md border-2 border-nana-ink bg-nana-cream px-3 font-extrabold text-nana-ink shadow-hard-sm max-lg:min-h-12">
        <x-ui.icon name="menu" class="size-5" />
        @unless ($compact)
            <span>Telas</span>
        @endunless
    </button>
    <div x-ref="items" x-show="open" x-cloak role="menu" aria-label="Telas"
         @keydown.arrow-down.prevent="$focus.within($refs.items).wrap().next()"
         @keydown.arrow-up.prevent="$focus.within($refs.items).wrap().previous()"
         @class([
             'absolute z-50 w-72 overflow-hidden rounded-lg border-2 border-nana-ink bg-white text-nana-ink shadow-hard',
             'top-full mt-2' => ! $up,
             'bottom-full mb-2' => $up,
             'left-0' => $align === 'left',
             'right-0' => $align === 'right',
         ])>
        @foreach ($screens as $screen)
            <a href="{{ $screen->url }}" role="menuitem" data-screen-link="{{ $screen->key }}"
               @if ($screen->key === $current) aria-current="page" @endif
               @class([$itemClass, 'border-b-2 border-nana-line', 'bg-nana-yellow' => $screen->key === $current])>{{ $screen->label }}</a>
        @endforeach
        <a href="{{ route('home', ['escolher' => 1]) }}" role="menuitem" data-screen-link="inicio" class="{{ $itemClass }}">Tela de início</a>
        @auth
            <form method="POST" action="{{ route('logout') }}" class="border-t-2 border-nana-ink">
                @csrf
                <button type="submit" role="menuitem" class="{{ $itemClass }} text-nana-red">Sair</button>
            </form>
        @endauth
    </div>
</div>
```

Create `resources/views/components/ui/topbar.blade.php`:

```blade
@props(['title' => null, 'current' => null, 'screens' => null])
<header {{ $attributes->class('flex shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b-3 border-nana-ink bg-nana-red px-4 py-2 text-nana-cream') }}>
    <span class="font-display text-2xl leading-none">{{ $title ?? \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</span>
    @isset($badge)
        {{ $badge }}
    @endisset
    <x-ui.screens-menu :screens="$screens" :current="$current" />
    @isset($tabs)
        <nav aria-label="Seções" class="flex flex-wrap items-center gap-1">{{ $tabs }}</nav>
    @endisset
    <div class="ml-auto flex items-center gap-4">
        @isset($end)
            {{ $end }}
        @endisset
        <x-connection-dot tone="light" />
    </div>
</header>
```

Replace `resources/views/components/connection-dot.blade.php` with:

```blade
@props(['tone' => 'dark'])
@php($onColor = $tone === 'light' ? 'bg-[#7CFFB2]' : 'bg-nana-green')
<span x-data {{ $attributes->class(['inline-flex items-center gap-2 text-sm font-bold', 'text-[#FFE3B0]' => $tone === 'light']) }}>
    <span class="size-3 rounded-full" :class="$store.conn.online ? '{{ $onColor }}' : 'animate-pulse bg-nana-yellow'"></span>
    <span x-text="$store.conn.online ? 'Online' : 'Sem conexão'">Online</span>
</span>
```

Replace `resources/views/components/toasts.blade.php` with:

```blade
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
```

Replace `resources/views/components/layouts/app.blade.php` with:

```blade
@props(['title' => 'Nana Comanda', 'bodyClass' => 'bg-nana-cream'])
@php(\Livewire\Livewire::forceAssetInjection())
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#D62828">
    <title>{{ $title }} · Nana Comanda</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh font-sans font-medium text-nana-ink antialiased {{ $bodyClass }}">
    {{ $slot }}
    <x-toasts />
</body>
</html>
```

- [ ] **Step 10: Browser helpers.** Create `tests/Browser/support.cjs` (plain Node, no test runner; Pest ignores `.cjs` files and `phpunit.xml` only lists `tests/Unit` and `tests/Feature`):

```js
/*
 * Helpers for the redesign's browser checks (Playwright via plain node).
 * Env: NANA_BASE_URL (default http://127.0.0.1:8000), NANA_E2E_USER / NANA_E2E_PASSWORD (local admin user),
 * NANA_PLAYWRIGHT_PATH / NANA_CHROME_PATH to override the local Playwright install.
 */
const PLAYWRIGHT_PATH = process.env.NANA_PLAYWRIGHT_PATH
    || 'C:/Users/mstjs/AppData/Roaming/npm/node_modules/@playwright/cli/node_modules/playwright';
const CHROME_PATH = process.env.NANA_CHROME_PATH
    || 'C:/Users/mstjs/AppData/Local/ms-playwright/chromium-1248/chrome-win64/chrome.exe';
const BASE_URL = (process.env.NANA_BASE_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '[::1]'];

const { chromium } = require(PLAYWRIGHT_PATH);

function fail(message) {
    throw new Error(message);
}

async function launch() {
    return chromium.launch({ executablePath: CHROME_PATH, headless: true });
}

/** Logs in once and returns the storage state; the login route accepts only 5 attempts per minute. */
async function loginState(browser) {
    const username = process.env.NANA_E2E_USER;
    const password = process.env.NANA_E2E_PASSWORD;
    if (!username || !password) {
        fail('Defina NANA_E2E_USER e NANA_E2E_PASSWORD (usuário admin local) antes de rodar.');
    }
    const context = await browser.newContext({ baseURL: BASE_URL });
    const page = await context.newPage();
    await page.goto('/login');
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.click('button[type="submit"]'),
    ]);
    const state = await context.storageState();
    await context.close();
    return state;
}

async function open(browser, path, { width, height, state = null, touch = false, init = null, reducedMotion = 'no-preference' } = {}) {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        viewport: { width, height },
        hasTouch: touch,
        reducedMotion,
        storageState: state ?? undefined,
    });
    if (init) {
        await context.addInitScript(init);
    }
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('request', (request) => {
        const url = request.url();
        if (/^(https?|wss?):/.test(url) && !LOCAL_HOSTS.includes(new URL(url).hostname)) {
            errors.push(`requisição externa: ${url}`);
        }
    });
    await page.goto(path, { waitUntil: 'load' });
    await page.waitForFunction(() => window.Alpine !== undefined && document.readyState === 'complete');
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(400);
    return { context, page, errors };
}

function expectNoErrors(errors, label) {
    if (errors.length > 0) {
        fail(`${label}: ${errors.join(' | ')}`);
    }
}

async function expectNoHorizontalScroll(page, label) {
    const m = await page.evaluate(() => ({ scroll: document.scrollingElement.scrollWidth, inner: window.innerWidth }));
    if (m.scroll > m.inner) {
        fail(`${label}: rolagem horizontal (${m.scroll}px > ${m.inner}px)`);
    }
}

/** Every rendered element matching the selector must sit entirely inside the viewport. Returns how many. */
async function expectInViewport(page, selector, label) {
    const r = await page.evaluate((sel) => {
        const els = [...document.querySelectorAll(sel)].filter((el) => el.getClientRects().length > 0);
        const cut = els.filter((el) => {
            const b = el.getBoundingClientRect();
            return b.top < -0.5 || b.left < -0.5 || b.bottom > window.innerHeight + 0.5 || b.right > window.innerWidth + 0.5;
        });
        return { count: els.length, cut: cut.length };
    }, selector);
    if (r.count === 0) {
        fail(`${label}: nada visível para ${selector}`);
    }
    if (r.cut > 0) {
        fail(`${label}: ${r.cut} de ${r.count} elementos ${selector} cortados`);
    }
    return r.count;
}

async function expectLastProductReachable(page, label) {
    const problem = await page.evaluate(async () => {
        const grid = document.querySelector('[data-product-grid]');
        if (!grid || grid.getClientRects().length === 0) {
            return 'grade de pratos não visível';
        }
        grid.scrollTop = grid.scrollHeight;
        await new Promise((resolve) => requestAnimationFrame(resolve));
        const tiles = grid.querySelectorAll('[data-product-tile]');
        if (tiles.length === 0) {
            return 'nenhum prato';
        }
        const last = tiles[tiles.length - 1].getBoundingClientRect();
        const box = grid.getBoundingClientRect();
        const ok = last.bottom <= box.bottom + 1 && last.top >= box.top - 1 && box.bottom <= window.innerHeight + 0.5;
        grid.scrollTop = 0;
        return ok ? '' : 'último prato cortado';
    });
    if (problem) {
        fail(`${label}: ${problem}`);
    }
}

/** Kitchen grid status: total cards, fully visible ones, cut PRONTO buttons and the footer text. */
async function kitchenStatus(page, scope = '') {
    return page.evaluate((root) => {
        const grid = document.querySelector(`${root} [data-kitchen-grid]`.trim());
        if (!grid) {
            return { total: 0, visible: 0, below: 0, buttonsCut: 0, moreText: '', maxItemsVisible: 0 };
        }
        const limit = grid.getBoundingClientRect();
        const cards = [...grid.querySelectorAll('[data-order-id]')];
        const visible = cards.filter((card) => card.getBoundingClientRect().bottom <= limit.bottom + 1);
        const buttonsCut = visible.filter((card) => {
            const b = card.querySelector('[data-ready-button]').getBoundingClientRect();
            return b.bottom > limit.bottom + 1 || b.right > window.innerWidth + 0.5;
        }).length;
        const more = document.querySelector(`${root} [data-kitchen-more]`.trim());
        const moreText = more && more.getClientRects().length > 0 ? more.textContent.trim() : '';
        const maxItemsVisible = Math.max(0, ...visible.map((card) => card.querySelectorAll('li').length));
        return { total: cards.length, visible: visible.length, below: cards.length - visible.length, buttonsCut, moreText, maxItemsVisible };
    }, scope);
}

/** Places a one-item order on a wide counter screen (name field and product grid visible). */
async function placeOrder(page, customerName, productName = 'Filé de frango') {
    await page.locator('[data-product-tile]', { hasText: productName }).first().click();
    const name = page.locator('[x-ref="name"]');
    await name.fill(customerName);
    await name.press('Enter');
    await page.locator('text=enviado!').first().waitFor({ timeout: 10000 });
    await page.waitForTimeout(300);
}

module.exports = {
    BASE_URL,
    fail,
    launch,
    loginState,
    open,
    expectNoErrors,
    expectNoHorizontalScroll,
    expectInViewport,
    expectLastProductReachable,
    kitchenStatus,
    placeOrder,
};
```

- [ ] **Step 11: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Support/LaunchpadTest.php tests/Feature/Ui tests/Feature/Models/SettingTest.php tests/Feature/Admin/ReportsAndSettingsTest.php`
Expected: PASS.

- [ ] **Step 12: Browser check (fonts bundled, no external font request).** Run `npm run build` (skip if `public/hot` exists), then:

```bash
node - <<'EOF'
const s = require(process.cwd() + '/tests/Browser/support.cjs');
(async () => {
    const browser = await s.launch();
    try {
        const { page, errors } = await s.open(browser, '/login', { width: 1366, height: 768 });
        const fonts = await page.evaluate(async () => {
            const black = await document.fonts.load('400 24px "Archivo Black"');
            const sans = await document.fonts.load('800 24px "Archivo"');
            return { black: black.length, sans: sans.length, body: getComputedStyle(document.body).fontFamily };
        });
        if (fonts.black === 0 || fonts.sans === 0) s.fail(`fontes não carregaram: ${JSON.stringify(fonts)}`);
        if (!fonts.body.startsWith('Archivo')) s.fail(`fonte do corpo: ${fonts.body}`);
        s.expectNoErrors(errors, 'login');
        console.log('OK', fonts);
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error.message); process.exit(1); });
EOF
```
Expected: `OK { black: 1, sans: 1, body: 'Archivo, ui-sans-serif, ...' }` and no "requisição externa" error.

- [ ] **Step 13: Run the full suite**

Run: `./vendor/bin/pest`
Expected: all PASS (144 existing tests plus the new ones).

- [ ] **Step 14: Format and build**

Run: `vendor/bin/pint --dirty --format agent && npm run build`
Expected: Pint reports no remaining issues; the build succeeds and `public/build/assets` contains `archivo-*-500-normal-*.woff2` and `archivo-black-*-400-normal-*.woff2`.

- [ ] **Step 15: Commit and push**

```bash
git add -A
git commit -m "feat: base visual do redesenho (cores, fontes empacotadas, componentes e registro de telas)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push origin main
```

---
### Task 2: Home screen (tela de início) and login restyle

**Files:**
- Create: `resources/views/pages/inicio.blade.php`, `resources/js/launcher.js`
- Modify: `app/Http/Controllers/HomeController.php`, `resources/js/app.js`, `resources/views/auth/login.blade.php`
- Test: `tests/Feature/Pages/HomeTest.php` (new), `tests/Feature/Auth/AccessTest.php`, `tests/Feature/Auth/LoginTest.php`

**Interfaces:**
- Consumes: `App\Support\Launchpad::for()`, `App\Support\Screen`, `<x-ui.icon>`, `<x-ui.button>`, tokens and `shadow-hard` from Task 1; `Setting::get(Setting::RESTAURANT_NAME)`; `LoginController::store()` (unchanged: `redirect()->intended(route('home'))`).
- Produces:
  - `GET /` (route name `home`, open to guests) renders `pages.inicio` with `$screens` (list<Screen>), `$allowed` (array<string, string>: key → url, without `login`) and `$choosing` (bool from `?escolher=1`).
  - Each tile is `<a href="{url}" data-screen="{key}">`.
  - Alpine data `launcher(allowed, choosing)` in `resources/js/launcher.js`, `localStorage` key `nana.home` (stores a Screen key; `login` is never stored). Methods: `choose(key)`, `forget()`; state: `remember`, `stored`, `redirecting`.
  - The "Telas" menu link "Tela de início" (Task 1) lands on `/?escolher=1`, which never auto-redirects and shows "Esquecer a escolha deste aparelho".

- [ ] **Step 1: Write the failing tests.**

Create `tests/Feature/Pages/HomeTest.php`:

```php
<?php

use App\Enums\Role;

it('shows guests the public tiles and the login tile instead of redirecting', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Onde você quer entrar?')
        ->assertSee('Painel da TV')
        ->assertSee('Acompanhar')
        ->assertSee('Entrar')
        ->assertSee('data-screen="painel"', false)
        ->assertSee('data-screen="acompanhar"', false)
        ->assertSee('data-screen="login"', false)
        ->assertDontSee('data-screen="estacao"', false)
        ->assertDontSee('data-screen="balcao"', false)
        ->assertDontSee('data-screen="cozinha"', false)
        ->assertDontSee('data-screen="admin"', false)
        ->assertDontSee('Administração');
});

it('shows each profile only the tiles it can open', function (Role $role, array $visible, array $hidden) {
    $response = $this->actingAs(userWithRole($role))->get('/')->assertOk();

    foreach ($visible as $key) {
        $response->assertSee('data-screen="'.$key.'"', false);
    }

    foreach ($hidden as $key) {
        $response->assertDontSee('data-screen="'.$key.'"', false);
    }
})->with([
    'admin' => [Role::Admin, ['estacao', 'balcao', 'cozinha', 'painel', 'acompanhar', 'admin'], ['login']],
    'balcao' => [Role::Balcao, ['estacao', 'balcao', 'cozinha', 'painel', 'acompanhar'], ['admin', 'login']],
    'cozinha' => [Role::Cozinha, ['cozinha', 'painel', 'acompanhar'], ['estacao', 'balcao', 'admin', 'login']],
]);

it('links every tile to its route and tags the station and the admin', function () {
    $this->actingAs(userWithRole(Role::Admin))->get('/')
        ->assertSee('href="'.route('estacao').'"', false)
        ->assertSee('href="'.route('balcao').'"', false)
        ->assertSee('href="'.route('cozinha').'"', false)
        ->assertSee('href="'.route('painel').'"', false)
        ->assertSee('href="'.route('acompanhar').'"', false)
        ->assertSee('href="'.url('/admin').'"', false)
        ->assertSee('PC do balcão')
        ->assertSee('Só admin')
        ->assertSee('Abrir sempre esta tela neste aparelho');
});

it('remembers the device choice only in the browser and lets the user forget it', function () {
    $this->get('/')
        ->assertSee('x-data="launcher(', false)
        ->assertSee('data-update-uri', false)
        ->assertDontSee('Esquecer a escolha deste aparelho');

    $this->get('/?escolher=1')
        ->assertOk()
        ->assertSee('Esquecer a escolha deste aparelho')
        ->assertSee(', true)"', false);
});
```

In `tests/Feature/Auth/AccessTest.php`, replace the test

```php
it('redirects home by role', function (Role $role, string $route) {
    $this->actingAs(userWithRole($role))->get('/')->assertRedirect(route($route));
})->with([[Role::Admin, 'estacao'], [Role::Balcao, 'estacao'], [Role::Cozinha, 'cozinha']]);
```

with

```php
it('shows the home screen to every profile instead of redirecting', function (?Role $role) {
    if ($role !== null) {
        $this->actingAs(userWithRole($role));
    }

    $this->get('/')->assertOk()->assertSee('Onde você quer entrar?');
})->with([Role::Admin, Role::Balcao, Role::Cozinha, null]);
```

Append to `tests/Feature/Auth/LoginTest.php`:

```php

it('sends the user back to the screen they tried to open', function () {
    User::factory()->create(['username' => 'cozinha', 'password' => 'segredo123', 'role' => Role::Cozinha]);

    $this->get('/cozinha')->assertRedirect(route('login'));

    $this->post('/login', ['username' => 'cozinha', 'password' => 'segredo123'])
        ->assertRedirect(route('cozinha'));
});

it('renders the restyled login with the same fields', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Entre para usar o balcão, a cozinha ou a administração.')
        ->assertSee('name="username"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="remember"', false)
        ->assertSee('Manter conectado neste aparelho')
        ->assertSee('Entrar');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Pages/HomeTest.php tests/Feature/Auth`
Expected: FAIL — `/` answers 302 (redirect to login or by role) instead of 200, and the login page lacks the new subtitle.

- [ ] **Step 3: Render the home screen.** Replace `app/Http/Controllers/HomeController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Support\Launchpad;
use App\Support\Screen;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HomeController
{
    public function __invoke(Request $request): View
    {
        $screens = Launchpad::for($request->user());

        return view('pages.inicio', [
            'screens' => $screens,
            'allowed' => collect($screens)
                ->reject(fn (Screen $screen) => $screen->key === 'login')
                ->mapWithKeys(fn (Screen $screen) => [$screen->key => $screen->url])
                ->all(),
            'choosing' => $request->boolean('escolher'),
        ]);
    }
}
```

- [ ] **Step 4: Create the view** `resources/views/pages/inicio.blade.php` (mockup 02: 3×2 tiles from 1024 px, 2 columns on a portrait tablet, 1 column on a phone):

```blade
<x-layouts.app title="Início">
    <div x-data="launcher(@js($allowed), @js($choosing))" class="flex min-h-dvh flex-col">
        <header class="flex items-center gap-4 border-b-3 border-nana-ink bg-nana-red px-4 py-3 text-nana-cream lg:px-8">
            <span class="font-display text-2xl leading-none lg:text-3xl">{{ \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</span>
            @auth
                <div class="ml-auto flex items-center gap-3 font-semibold">
                    <span>Olá, <b class="rounded-md bg-nana-cream px-2 py-1 font-extrabold text-nana-red">{{ auth()->user()->username }}</b></span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="min-h-11 rounded-md border-2 border-nana-cream px-3 font-extrabold">Sair</button>
                    </form>
                </div>
            @endauth
        </header>

        <main x-show="! redirecting" class="flex flex-1 flex-col px-4 py-6 lg:px-10 lg:py-8">
            <h1 class="font-display text-3xl leading-none lg:text-4xl">Onde você quer entrar?</h1>
            <p class="mt-2 text-lg text-nana-muted">Toque em uma tela. Dá para trocar a qualquer momento pelo botão “Telas” no topo.</p>

            <div @class([
                'mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3',
                'lg:flex-1 lg:auto-rows-fr' => count($screens) > 3,
            ])>
                @foreach ($screens as $screen)
                    @php($main = $screen->key === 'estacao')
                    <a href="{{ $screen->url }}" data-screen="{{ $screen->key }}" @click="choose('{{ $screen->key }}')"
                       @class([
                           'relative flex min-h-44 flex-col justify-end gap-1 rounded-xl border-3 border-nana-ink p-5 shadow-hard transition active:translate-x-0.5 active:translate-y-0.5 active:shadow-none',
                           'bg-nana-red text-nana-cream' => $main,
                           'bg-white text-nana-ink' => ! $main,
                       ])>
                        @if ($screen->badge)
                            <span @class([
                                'absolute right-4 top-4 rounded-md border-2 border-nana-ink px-2 py-1 text-xs font-extrabold uppercase tracking-wide',
                                'bg-nana-ink text-nana-cream' => $screen->key === 'admin',
                                'bg-nana-yellow text-nana-ink' => $screen->key !== 'admin',
                            ])>{{ $screen->badge }}</span>
                        @endif
                        <span @class([
                            'mb-auto grid size-16 place-items-center rounded-lg border-2 border-nana-ink text-nana-ink',
                            'bg-nana-cream' => $main,
                            'bg-white' => $screen->key === 'admin',
                            'bg-nana-yellow' => ! $main && $screen->key !== 'admin',
                        ])>
                            <x-ui.icon :name="$screen->icon" class="size-9" />
                        </span>
                        <span class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-display text-3xl leading-tight">{{ $screen->label }}</span>
                            <span x-show="stored === '{{ $screen->key }}'" x-cloak
                                  class="rounded-md border-2 border-nana-ink bg-nana-cream px-2 py-1 text-xs font-extrabold uppercase tracking-wide text-nana-ink">Abre sempre aqui</span>
                        </span>
                        <span @class(['text-lg', 'text-[#FFE3B0]' => $main, 'text-nana-muted' => ! $main])>{{ $screen->description }}</span>
                    </a>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3">
                <label class="flex min-h-11 cursor-pointer flex-wrap items-center gap-3 text-lg font-semibold">
                    <input type="checkbox" x-model="remember" class="size-7 rounded border-2 border-nana-ink text-nana-yellow">
                    Abrir sempre esta tela neste aparelho
                    <small class="text-base font-medium text-nana-muted">(dá para desfazer no botão “Telas”)</small>
                </label>
                @if ($choosing)
                    <x-ui.button variant="ghost" x-show="stored" x-cloak @click="forget()">Esquecer a escolha deste aparelho</x-ui.button>
                @endif
            </div>
        </main>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: The launcher.** Create `resources/js/launcher.js`:

```js
const KEY = 'nana.home';

function read() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
}

function write(value) {
    try { localStorage.setItem(KEY, value); } catch (e) { /* armazenamento indisponível */ }
}

function clear() {
    try { localStorage.removeItem(KEY); } catch (e) { /* armazenamento indisponível */ }
}

export function registerLauncher(Alpine) {
    Alpine.data('launcher', (allowed = {}, choosing = false) => ({
        remember: false,
        stored: null,
        redirecting: false,
        init() {
            this.stored = read();
            if (!choosing && this.stored && Object.hasOwn(allowed, this.stored)) {
                this.redirecting = true;
                window.location.replace(allowed[this.stored]);
            }
        },
        choose(key) {
            if (this.remember && key !== 'login') {
                write(key);
                this.stored = key;
            }
        },
        forget() {
            clear();
            this.stored = null;
            this.remember = false;
        },
    }));
}
```

Replace `resources/js/app.js` with:

```js
import '@fontsource/archivo/500.css';
import '@fontsource/archivo/600.css';
import '@fontsource/archivo/700.css';
import '@fontsource/archivo/800.css';
import '@fontsource/archivo-black/400.css';
import './echo';
import { registerDevice } from './device';
import { registerToasts } from './toasts';
import { registerConnection } from './connection';
import { registerNetworkErrors } from './network';
import { registerOrderDraft } from './order-draft';
import { registerKitchen } from './kitchen';
import { registerAnnouncer } from './announcer';
import { registerLauncher } from './launcher';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    registerDevice(Alpine);
    registerToasts(Alpine);
    registerConnection(Alpine);
    registerOrderDraft(Alpine);
    registerKitchen(Alpine);
    registerAnnouncer(Alpine);
    registerLauncher(Alpine);
});

registerNetworkErrors();
```

- [ ] **Step 6: Restyle the login** (mockup 05; same form action, fields, names, `remember` checked by default and error display). Replace `resources/views/auth/login.blade.php` with:

```blade
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

            <label class="mt-5 flex min-h-11 cursor-pointer items-center gap-3 text-lg font-bold">
                <input type="checkbox" name="remember" value="1" checked class="size-6 rounded border-3 border-nana-ink text-nana-yellow">
                Manter conectado neste aparelho
            </label>

            @error('username') <p class="mt-3 text-base font-bold text-nana-red">{{ $message }}</p> @enderror
            @error('password') <p class="mt-3 text-base font-bold text-nana-red">{{ $message }}</p> @enderror

            <x-ui.button type="submit" variant="dark" size="lg" class="mt-6 w-full">Entrar</x-ui.button>
        </form>
    </main>
</x-layouts.app>
```

- [ ] **Step 7: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Pages/HomeTest.php tests/Feature/Auth tests/Feature/Admin/AdminAccessTest.php`
Expected: PASS (`/admin` for guests still redirects to `route('login')`).

- [ ] **Step 8: Browser check.** Run `npm run build` (skip if `public/hot` exists), then:

```bash
node - <<'EOF'
const s = require(process.cwd() + '/tests/Browser/support.cjs');
(async () => {
    const browser = await s.launch();
    try {
        const state = await s.loginState(browser);

        for (const [width, height] of [[1366, 768], [800, 1280], [390, 844]]) {
            const { page, context, errors } = await s.open(browser, '/?escolher=1', { width, height, state, touch: width < 1024 });
            await s.expectNoHorizontalScroll(page, `início ${width}x${height}`);
            const columns = await page.evaluate(() => getComputedStyle(document.querySelector('[data-screen]').parentElement).gridTemplateColumns.split(' ').length);
            const expected = width >= 1024 ? 3 : (width >= 768 ? 2 : 1);
            if (columns !== expected) s.fail(`início ${width}x${height}: ${columns} colunas (esperado ${expected})`);
            if (width === 1366) await s.expectInViewport(page, '[data-screen]', 'início 1366x768: blocos');
            s.expectNoErrors(errors, `início ${width}x${height}`);
            await context.close();
        }

        const { page, context } = await s.open(browser, '/?escolher=1', { width: 1366, height: 768, state });
        await page.check('input[x-model="remember"]');
        await Promise.all([page.waitForURL('**/cozinha'), page.click('[data-screen="cozinha"]')]);
        await page.goto('/');
        await page.waitForURL('**/cozinha', { timeout: 5000 });
        await page.goto('/?escolher=1');
        await page.waitForTimeout(800);
        if (new URL(page.url()).pathname !== '/') s.fail('/?escolher=1 não deveria redirecionar');
        await page.click('text=Esquecer a escolha deste aparelho');
        await page.goto('/');
        await page.waitForTimeout(800);
        if (new URL(page.url()).pathname !== '/') s.fail('depois de esquecer, / deveria mostrar a tela de início');
        await context.close();

        const guest = await s.open(browser, '/login', { width: 1366, height: 768 });
        await s.expectNoHorizontalScroll(guest.page, 'login');
        await s.expectInViewport(guest.page, 'button[type="submit"]', 'login: botão Entrar');
        s.expectNoErrors(guest.errors, 'login');
        await guest.context.close();

        console.log('OK');
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error.message); process.exit(1); });
EOF
```
Expected: `OK`. Also open `http://127.0.0.1:8000/` as a guest in a normal browser and compare with mockup 02: red top bar, "Onde você quer entrar?", white tiles with a yellow icon square, hard black shadows.

- [ ] **Step 9: Run the full suite**

Run: `./vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 10: Format and build**

Run: `vendor/bin/pint --dirty --format agent && npm run build`
Expected: no Pint issues left; the build succeeds.

- [ ] **Step 11: Commit and push**

```bash
git add -A
git commit -m "feat: tela de início com escolha por aparelho e login no estilo novo" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push origin main
```

---
### Task 3: Counter (balcão) — products and order panel, responsive by container width

**Files:**
- Create: `resources/js/balcao.js`
- Modify: `resources/views/livewire/balcao/order-form.blade.php`, `app/Livewire/Balcao/OrderForm.php`, `resources/views/components/balcao-pane.blade.php`, `resources/views/pages/balcao.blade.php`, `resources/js/app.js`
- Test: `tests/Feature/Livewire/OrderFormTest.php`, `tests/Feature/Pages/StationTest.php`

**Interfaces:**
- Consumes: `<x-ui.topbar :current>` (slots `tabs`, `end`), `<x-ui.chip :active>`, `<x-ui.button>`, `shadow-hard`, `shadow-hard-sm`, `font-display`, tokens (Task 1); the existing `OrderForm` API (`categories`, `products`, `quickNotes`, `totalCents`, `selectCategory`, `addProduct`, `addFirstSearchResult`, `editDailyName`, `saveDailyName`, `closeDailyName`, `toggleSoldOutMode`, `toggleSoldOut`, `increment`, `decrement`, `remove`, `toggleChip`, `submit`, `loadOrder` via `edit-order`, `cancelEdit`, `restoreDraft`) and `orderDraft` from `resources/js/order-draft.js` (unchanged).
- Produces:
  - `OrderForm` computed properties: `orderNumber(): string` (3 digits; next number = today's `daily_counters.last_number + 1`, or the edited order's number), `cartQuantities(): array<int, int>` (product id → quantity in the order being built), `itemCount(): int`.
  - `<x-balcao-pane :current="'balcao'">` with an optional named slot `topbar-end` (forwarded to the topbar's `end` slot; Task 6 uses it). Root element: `x-data="balcaoPane"` and class `@container/pane`.
  - Alpine data `balcaoPane` (`resources/js/balcao.js`): `tab` (`'novo' | 'hoje' | 'cozinha'`), `kitchenCount` (set by the window event `in-kitchen-count` with `detail.count`, emitted by `InKitchenList` in Task 4); resets `tab` from `'cozinha'` to `'novo'` when the pane becomes ≥ 1280 px wide.
  - Layout by pane width (container queries on `pane`): ≥ 1280 px → products (grid 3) | order `w-[27cqw]` | "Na cozinha" `w-[17cqw]`; 1024–1279 px → products (grid 3) | order `w-[32cqw]`, "Na cozinha" as a tab; < 1024 px → products (grid 2) + bottom bar `data-order-bar` that opens the order as an overlay sheet.
  - Data attributes: `data-product-grid`, `data-product-tile`, `data-in-cart="{n}"`, `data-order-panel`, `data-order-total`, `data-send-button`, `data-order-bar`, `data-takeaway-option="0|1"`, `data-in-kitchen-column`.

Bindings that must survive this task (asserted in Step 1): `x-data="orderDraft"`, `wire:submit="submit"`, `wire:model="customerName"` with `x-ref="name"` and `autofocus`, `@focus-name.window`, `wire:model.live.debounce.150ms="search"` with `@keydown.enter.prevent="$wire.addFirstSearchResult()"`, `wire:click="selectCategory(..)"`, `wire:click="addProduct(..)"` / `toggleSoldOut(..)`, `wire:click="toggleSoldOutMode"`, `wire:click="editDailyName(..)"`, `wire:click="increment|decrement|remove(..)"`, `wire:click="toggleChip(..)"`, `wire:model="lines.{i}.note"`, `wire:click="cancelEdit"`, `wire:model="dailyNameInput"` with `wire:keydown.enter.prevent="saveDailyName"`, `wire:loading` on submit, and the pane's `@edit-order.window="tab = 'novo'"`.

- [ ] **Step 1: Write the failing tests.** Append to `tests/Feature/Livewire/OrderFormTest.php`:

```php

it('keeps every counter binding in the new layout', function () {
    kitchenProduct(['name' => 'Filé de frango']);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSeeHtml('x-data="orderDraft"')
        ->assertSeeHtml('wire:submit="submit"')
        ->assertSeeHtml('wire:model="customerName" x-ref="name" autofocus')
        ->assertSeeHtml('@focus-name.window="sheet = false; $nextTick(() => $refs.name?.focus())"')
        ->assertSeeHtml('wire:model.live.debounce.150ms="search" @keydown.enter.prevent="$wire.addFirstSearchResult()"')
        ->assertSeeHtml('wire:click="toggleSoldOutMode"')
        ->assertSeeHtml('data-product-grid')
        ->assertSeeHtml('data-send-button')
        ->assertSeeHtml('data-order-bar')
        ->assertSee('Enviar para a cozinha');
});

it('switches between eating here and takeaway with the segmented control', function () {
    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSee('Comer aqui')
        ->assertSee('Para viagem')
        ->assertSeeHtml('data-takeaway-option="0" aria-pressed="true"')
        ->assertSeeHtml('data-takeaway-option="1" aria-pressed="false"')
        ->assertSeeHtml("wire:click=\"\$set('takeaway', true)\"")
        ->set('takeaway', true)
        ->assertSeeHtml('data-takeaway-option="0" aria-pressed="false"')
        ->assertSeeHtml('data-takeaway-option="1" aria-pressed="true"');
});

it('moves "Marcar esgotado" into the more-options menu next to the search', function () {
    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSeeHtmlInOrder(['type="search"', 'aria-label="Mais opções"', 'Marcar esgotado'])
        ->call('toggleSoldOutMode')
        ->assertSee('Concluir esgotados')
        ->assertSee('Toque num produto para marcar ou desmarcar como esgotado hoje.');
});

it('marks products in the order and sold out products on the tiles', function () {
    $dish = kitchenProduct(['name' => 'Filé de frango']);
    $soldOut = Product::factory()->create(['category_id' => $dish->category_id, 'name' => 'Picanha bovina']);
    $soldOut->toggleSoldOut();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSeeHtml('data-in-cart="0"')
        ->call('addProduct', $dish->id)
        ->call('addProduct', $dish->id)
        ->assertSeeHtml('data-in-cart="2"')
        ->assertSee('Esgotado hoje')
        ->assertSee('2 itens');
});

it('shows the dish of the day under the product name', function () {
    $dish = kitchenProduct(['name' => 'Prato do dia', 'has_daily_name' => true]);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSee('Definir prato de hoje')
        ->call('addProduct', $dish->id)
        ->set('dailyNameInput', 'Feijoada')
        ->call('saveDailyName')
        ->assertSeeHtml('text-nana-red">Feijoada</span>');
});

it('shows the number the next order will get, or the number being edited', function () {
    $dish = kitchenProduct();
    placeOrder([item($dish)]);
    $second = placeOrder([item($dish)], ['customer_name' => 'João'], $this->user);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->assertSee('nº 003')
        ->dispatch('edit-order', orderId: $second->id)
        ->assertSee('nº 002')
        ->assertSee('Alterando pedido');
});
```

Add `use App\Models\Product;` to the `use` block at the top of `tests/Feature/Livewire/OrderFormTest.php` (after `use App\Models\Order;`).

Append to `tests/Feature/Pages/StationTest.php`:

```php

it('renders the counter pane with its tabs as a size container', function () {
    $this->actingAs(userWithRole(Role::Balcao))->get('/balcao')
        ->assertOk()
        ->assertSee('x-data="balcaoPane"', false)
        ->assertSee('@container/pane', false)
        ->assertSee('Novo pedido')
        ->assertSee('Pedidos de hoje')
        ->assertSee('Na cozinha')
        ->assertSee('data-in-kitchen-column', false)
        ->assertSee('Telas')
        ->assertDontSee('Toque para começar');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Livewire/OrderFormTest.php tests/Feature/Pages/StationTest.php`
Expected: FAIL — the markup has no `data-product-grid`, no segmented control, no "Mais opções" menu, no `nº`, and the pane has no `balcaoPane`.

- [ ] **Step 3: Add the computed properties to `app/Livewire/Balcao/OrderForm.php`.**

Add these imports to the `use` block (keep it alphabetical):

```php
use App\Models\DailyCounter;
use App\Support\BusinessDay;
```

Add these three methods right after the existing `totalCents()` method:

```php
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
```

- [ ] **Step 4: Replace `resources/views/livewire/balcao/order-form.blade.php`** with:

```blade
@use('App\Support\Money')
<div x-data="orderDraft" class="h-full min-h-0">
    <div x-data="{ sheet: false, menu: false }"
         @focus-name.window="sheet = false; $nextTick(() => $refs.name?.focus())"
         @edit-order.window="sheet = true"
         @keydown.escape.window="menu = false"
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
                        <button type="button" @click="menu = ! menu" aria-label="Mais opções" aria-haspopup="menu" :aria-expanded="menu.toString()"
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
```

Notes for the implementer:
- The nested `x-data="{ sheet, menu }"` holds `x-ref="name"`, so `@focus-name.window` lives on that nested element (Alpine registers a ref on its closest `x-data` root; a listener on the outer `orderDraft` root would not see it).
- The order panel is `hidden` below 1024 px of pane width; `sheet = true` adds `flex! absolute inset-0 z-30` (the `@min-[1024px]/pane:static` variant cancels the overlay if the pane widens). The bottom bar is a normal flex child, not `position: fixed`, so it stays inside the pane in every context.
- The grid has `p-2` so the quantity bubble (`-top-2 -right-2`) and the hard shadow are not clipped by `overflow-y-auto`.

- [ ] **Step 5: Replace `resources/views/components/balcao-pane.blade.php`** with:

```blade
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
```

`cqw` units resolve against the nearest size container, which is this pane: do not add another `@container` between the pane and the order panel or the aside.

- [ ] **Step 6: The pane's Alpine data.** Create `resources/js/balcao.js`:

```js
const WIDE = 1280;

export function registerBalcao(Alpine) {
    Alpine.data('balcaoPane', () => ({
        tab: 'novo',
        kitchenCount: 0,
        observer: null,
        init() {
            // "Na cozinha" is a tab only below 1280 px; when the pane widens the column is back on screen.
            this.observer = new ResizeObserver(([entry]) => {
                if (entry.contentRect.width >= WIDE && this.tab === 'cozinha') {
                    this.tab = 'novo';
                }
            });
            this.observer.observe(this.$root);
        },
        destroy() {
            this.observer?.disconnect();
        },
    }));
}
```

Replace `resources/js/app.js` with:

```js
import '@fontsource/archivo/500.css';
import '@fontsource/archivo/600.css';
import '@fontsource/archivo/700.css';
import '@fontsource/archivo/800.css';
import '@fontsource/archivo-black/400.css';
import './echo';
import { registerDevice } from './device';
import { registerToasts } from './toasts';
import { registerConnection } from './connection';
import { registerNetworkErrors } from './network';
import { registerOrderDraft } from './order-draft';
import { registerKitchen } from './kitchen';
import { registerAnnouncer } from './announcer';
import { registerLauncher } from './launcher';
import { registerBalcao } from './balcao';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    registerDevice(Alpine);
    registerToasts(Alpine);
    registerConnection(Alpine);
    registerOrderDraft(Alpine);
    registerKitchen(Alpine);
    registerAnnouncer(Alpine);
    registerLauncher(Alpine);
    registerBalcao(Alpine);
});

registerNetworkErrors();
```

- [ ] **Step 7: The standalone page.** Replace `resources/views/pages/balcao.blade.php` with:

```blade
<x-layouts.app title="Balcão" body-class="bg-nana-cream overflow-hidden">
    <div class="h-dvh"><x-balcao-pane /></div>
</x-layouts.app>
```

(`pages/estacao.blade.php` keeps rendering `<x-balcao-pane />` inside its fixed grid until Task 6.)

- [ ] **Step 8: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Livewire/OrderFormTest.php tests/Feature/Pages/StationTest.php`
Expected: PASS (the existing OrderForm assertions are unchanged: they only check behavior and `R$ 24,00`).

- [ ] **Step 9: Browser check.** Run `npm run build` (skip if `public/hot` exists), then:

```bash
node - <<'EOF'
const s = require(process.cwd() + '/tests/Browser/support.cjs');
(async () => {
    const browser = await s.launch();
    try {
        const state = await s.loginState(browser);

        for (const [width, height] of [[1366, 768], [1280, 800], [1180, 820]]) {
            const label = `balcão ${width}x${height}`;
            const { page, context, errors } = await s.open(browser, '/balcao', { width, height, state, touch: width < 1366 });
            await s.expectNoHorizontalScroll(page, label);
            await s.expectInViewport(page, '[data-send-button]', `${label}: Enviar`);
            await s.expectInViewport(page, '[data-order-total]', `${label}: total`);
            await s.expectLastProductReachable(page, label);
            const layout = await page.evaluate(() => ({
                columns: getComputedStyle(document.querySelector('[data-product-grid]')).gridTemplateColumns.split(' ').length,
                aside: document.querySelector('[data-in-kitchen-column]').getClientRects().length > 0,
            }));
            if (layout.columns !== 3) s.fail(`${label}: grade com ${layout.columns} colunas (esperado 3)`);
            if (width >= 1280 && !layout.aside) s.fail(`${label}: coluna "Na cozinha" deveria estar visível`);
            if (width < 1280) {
                if (layout.aside) s.fail(`${label}: "Na cozinha" deveria virar aba`);
                await page.click('button:has-text("Na cozinha")');
                await s.expectInViewport(page, '[data-in-kitchen-column]', `${label}: aba Na cozinha`);
            }
            s.expectNoErrors(errors, label);
            await context.close();
        }

        const portrait = await s.open(browser, '/balcao', { width: 800, height: 1280, state, touch: true });
        await s.expectNoHorizontalScroll(portrait.page, 'balcão 800x1280');
        await s.expectInViewport(portrait.page, '[data-order-bar]', 'balcão 800x1280: barra do pedido');
        await s.expectLastProductReachable(portrait.page, 'balcão 800x1280');
        const portraitColumns = await portrait.page.evaluate(() => getComputedStyle(document.querySelector('[data-product-grid]')).gridTemplateColumns.split(' ').length);
        if (portraitColumns !== 2) s.fail(`balcão 800x1280: grade com ${portraitColumns} colunas (esperado 2)`);
        await portrait.page.tap('[data-order-bar]');
        await s.expectInViewport(portrait.page, '[data-send-button]', 'balcão 800x1280: Enviar no pedido aberto');
        await portrait.page.tap('text=← Voltar aos pratos');
        await s.expectInViewport(portrait.page, '[data-order-bar]', 'balcão 800x1280: barra de volta');
        s.expectNoErrors(portrait.errors, 'balcão 800x1280');
        await portrait.context.close();

        const draft = await s.open(browser, '/balcao', { width: 1366, height: 768, state });
        await draft.page.locator('[data-product-tile]', { hasText: 'Filé de frango' }).first().click();
        await draft.page.fill('[x-ref="name"]', 'Rascunho Teste');
        await draft.page.waitForTimeout(1500);
        await draft.page.reload();
        await draft.page.waitForFunction(() => document.querySelector('[x-ref="name"]')?.value === 'Rascunho Teste', null, { timeout: 8000 });
        await draft.page.locator('[data-in-cart="1"]').first().waitFor({ timeout: 8000 });
        await draft.page.click('text=remover');
        await draft.page.fill('[x-ref="name"]', '');
        await draft.page.waitForTimeout(1500);
        await draft.context.close();

        console.log('OK');
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error.message); process.exit(1); });
EOF
```
Expected: `OK`. Then compare a manual screenshot at 1366×768 with option B of mockup 03 (three columns, yellow outline + red bubble on the product in the order, "Esgotado hoje" faded) and the 800×1280 view with the portrait drawing of option A (two-column grid, black bottom bar "Nome · N itens", red price pill).

- [ ] **Step 10: Run the full suite**

Run: `./vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 11: Format and build**

Run: `vendor/bin/pint --dirty --format agent && npm run build`
Expected: no Pint issues left; the build succeeds.

- [ ] **Step 12: Commit and push**

```bash
git add -A
git commit -m "feat: balcão em três colunas que se adapta à largura (pratos, pedido e barra no tablet)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push origin main
```

---
### Task 4: Counter — "Na cozinha" column and "Pedidos de hoje"

**Files:**
- Modify: `resources/views/livewire/balcao/in-kitchen-list.blade.php`, `resources/views/livewire/balcao/today-orders.blade.php`
- Test: `tests/Feature/Livewire/CounterListsTest.php`

**Interfaces:**
- Consumes: `<x-ui.button>`, `<x-ui.badge>`, `shadow-hard`, tokens (Task 1); the pane listener `@in-kitchen-count.window` and the "Na cozinha" tab counter (Task 3); the existing `InKitchenList` (`orders`, `markReady`) and `TodayOrders` (`orders`, `search`, `markReady`, `markDelivered`, `backToPreparing`, `edit`, `startCancel`, `closeCancel`, `confirmCancel`, `cancelingOrderId`, `cancelReason`, `cancelReasonOther`, `CANCEL_REASONS`) APIs, unchanged.
- Produces:
  - `InKitchenList` renders a hidden `<span data-in-kitchen-count="{n}">` keyed by the count (`wire:key="ik-count-{n}"`) whose `x-init` runs `$dispatch('in-kitchen-count', { count: n })`. When the count changes, Livewire replaces the keyed span, so `x-init` runs again and the tab counter follows the list.
  - "Na cozinha" column: compact rows `<li data-in-kitchen-order="{id}">` (number in `font-display`, name, green "Pronto"). The counter's undo stays where it already is: "Voltar p/ preparo" in "Pedidos de hoje" (`backToPreparing`); the kitchen keeps its own 10 s undo bar.
  - "Pedidos de hoje": card list (no table), so it wraps instead of scrolling sideways on a portrait tablet.

Existing assertions in `CounterListsTest.php` keep working unchanged: they check `001`, the customer names, `toast` dispatches, `há 7 min`, the cancel flow and the gates, all of which this task keeps.

- [ ] **Step 1: Write the failing tests.** Append to `tests/Feature/Livewire/CounterListsTest.php`:

```php

it('reports the in-kitchen count to the counter tab', function () {
    placeOrder([item($this->dish)], ['customer_name' => 'Carla']);
    placeOrder([item($this->dish)], ['customer_name' => 'Bruno']);

    Livewire::actingAs($this->user)->test(InKitchenList::class)
        ->assertSee('Na cozinha · 2')
        ->assertSeeHtml('data-in-kitchen-count="2"')
        ->assertSeeHtml('wire:key="ik-count-2"')
        ->assertSeeHtml("x-init=\"\$dispatch('in-kitchen-count', { count: 2 })\"");
});

it('renders today orders as cards with status, waiting time and the right actions', function () {
    $preparing = placeOrder([item($this->dish)], ['customer_name' => 'Carla', 'takeaway' => true]);
    $ready = placeOrder([item($this->dish)], ['customer_name' => 'Bruno']);
    app(TransitionOrder::class)->markReady($ready);

    Livewire::actingAs($this->user)->test(TodayOrders::class)
        ->assertSee('Preparando')
        ->assertSee('Pronto')
        ->assertSee('VIAGEM')
        ->assertSee('Editar')
        ->assertSee('Entregue')
        ->assertSee('Voltar p/ preparo')
        ->assertSeeHtml('wire:click="edit('.$preparing->id.')"')
        ->assertSeeHtml('wire:click="markDelivered('.$ready->id.')"')
        ->assertSeeHtml('wire:click="startCancel('.$preparing->id.')"')
        ->call('startCancel', $preparing->id)
        ->assertSee('Cancelar pedido')
        ->assertSee('Cliente desistiu')
        ->assertSeeHtml('role="dialog"');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Livewire/CounterListsTest.php`
Expected: FAIL — no `data-in-kitchen-count`, the heading is `Na cozinha (2)`, and the cancel modal has no `role="dialog"`.

- [ ] **Step 3: Replace `resources/views/livewire/balcao/in-kitchen-list.blade.php`** with:

```blade
<div wire:poll.20s class="flex h-full min-h-0 flex-col rounded-lg border-2 border-nana-ink bg-white p-3 shadow-hard">
    <span hidden x-data wire:key="ik-count-{{ $this->orders->count() }}" data-in-kitchen-count="{{ $this->orders->count() }}"
          x-init="$dispatch('in-kitchen-count', { count: {{ $this->orders->count() }} })"></span>
    <h3 class="mb-2 font-display text-lg leading-none">Na cozinha · {{ $this->orders->count() }}</h3>
    <ul class="min-h-0 flex-1 space-y-2 overflow-y-auto">
        @forelse ($this->orders as $order)
            <li wire:key="ik-{{ $order->id }}" data-in-kitchen-order="{{ $order->id }}" class="flex items-center gap-2 rounded-md border-2 border-nana-line px-2 py-1.5">
                <div class="min-w-0 flex-1">
                    <p class="font-display text-xl leading-none tabular-nums">{{ $order->displayNumber() }}</p>
                    <p class="truncate text-sm font-semibold text-nana-muted">{{ $order->customer_name }}</p>
                </div>
                <button type="button" wire:click="markReady({{ $order->id }})"
                        class="min-h-11 shrink-0 rounded-md border-2 border-nana-ink bg-nana-green px-3 text-sm font-extrabold text-white">Pronto</button>
            </li>
        @empty
            <li class="py-8 text-center font-semibold text-nana-muted">Nada na cozinha.</li>
        @endforelse
    </ul>
</div>
```

- [ ] **Step 4: Replace `resources/views/livewire/balcao/today-orders.blade.php`** with:

```blade
@use('App\Enums\OrderStatus')
@use('App\Support\Money')
<div wire:poll.20s class="flex h-full min-h-0 flex-col gap-3">
    <input type="search" wire:model.live.debounce.200ms="search" placeholder="Buscar por número ou nome…" aria-label="Buscar pedido"
           class="min-h-12 w-full rounded-lg border-2 border-nana-ink bg-white px-4 text-lg font-semibold placeholder:text-nana-muted">

    <div class="min-h-0 flex-1 overflow-y-auto rounded-lg border-2 border-nana-ink bg-white shadow-hard">
        <ul class="divide-y-2 divide-nana-line">
            @forelse ($this->orders as $order)
                @php
                    $open = in_array($order->status, [OrderStatus::Preparing, OrderStatus::Ready], true);
                    $tone = match ($order->status) {
                        OrderStatus::Preparing => 'yellow',
                        OrderStatus::Ready => 'green',
                        OrderStatus::Delivered => 'outline',
                        OrderStatus::Canceled => 'red',
                    };
                @endphp
                <li wire:key="today-{{ $order->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                    <span class="w-14 font-display text-2xl leading-none tabular-nums">{{ $order->displayNumber() }}</span>
                    <span class="min-w-0 flex-1 basis-40">
                        <span class="block truncate text-lg font-bold">{{ $order->customer_name }}</span>
                        <span class="block text-sm font-semibold tabular-nums text-nana-muted">
                            {{ $order->created_at->format('H:i') }}
                            @if ($open)
                                · há {{ (int) floor(abs($order->created_at->diffInMinutes(now()))) }} min
                            @endif
                        </span>
                    </span>
                    @if ($order->takeaway)
                        <x-ui.badge tone="ink">VIAGEM</x-ui.badge>
                    @endif
                    <x-ui.badge :tone="$tone">{{ $order->status->label() }}</x-ui.badge>
                    <span class="w-24 text-right font-display text-lg tabular-nums">{{ Money::format($order->total_cents) }}</span>
                    <div class="flex flex-wrap justify-end gap-2">
                        @if ($order->status === OrderStatus::Preparing)
                            <x-ui.button variant="success" wire:click="markReady({{ $order->id }})">Pronto</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="edit({{ $order->id }})">Editar</x-ui.button>
                        @elseif ($order->status === OrderStatus::Ready)
                            <x-ui.button variant="dark" wire:click="markDelivered({{ $order->id }})">Entregue</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="backToPreparing({{ $order->id }})">Voltar p/ preparo</x-ui.button>
                        @endif
                        @if ($open)
                            <x-ui.button variant="ghost" class="text-nana-red" wire:click="startCancel({{ $order->id }})">Cancelar</x-ui.button>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-4 py-12 text-center font-semibold text-nana-muted">Nenhum pedido hoje.</li>
            @endforelse
        </ul>
    </div>

    @if ($cancelingOrderId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-nana-ink/60 p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="cancel-title"
                 class="w-full max-w-md rounded-xl border-3 border-nana-ink bg-nana-cream p-6 shadow-[8px_8px_0_var(--color-nana-ink)]">
                <h3 id="cancel-title" class="font-display text-2xl">Cancelar pedido</h3>
                <div class="mt-4 space-y-2">
                    @foreach (\App\Livewire\Balcao\TodayOrders::CANCEL_REASONS as $reason)
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border-2 border-nana-ink bg-white px-3 text-lg font-semibold">
                            <input type="radio" wire:model.live="cancelReason" value="{{ $reason }}" class="size-5 border-2 border-nana-ink text-nana-red"> {{ $reason }}
                        </label>
                    @endforeach
                    @if ($cancelReason === 'Outro')
                        <input type="text" wire:model="cancelReasonOther" maxlength="100" placeholder="Qual o motivo?"
                               class="block min-h-12 w-full rounded-lg border-2 border-nana-ink bg-white px-4 text-lg">
                    @endif
                    @error('cancelReason') <p class="text-sm font-bold text-nana-red">{{ $message }}</p> @enderror
                    @error('cancelReasonOther') <p class="text-sm font-bold text-nana-red">{{ $message }}</p> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <x-ui.button variant="ghost" wire:click="closeCancel">Voltar</x-ui.button>
                    <x-ui.button wire:click="confirmCancel">Cancelar pedido</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
```

`OrderStatus` has exactly four cases (`Preparing`, `Ready`, `Delivered`, `Canceled`), so the `match` is exhaustive.

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Livewire/CounterListsTest.php`
Expected: PASS (including the untouched `shows the waiting time for orders in progress`, which still finds `há 7 min`).

- [ ] **Step 6: Browser check.** Run `npm run build` (skip if `public/hot` exists), then:

```bash
node - <<'EOF'
const s = require(process.cwd() + '/tests/Browser/support.cjs');
(async () => {
    const browser = await s.launch();
    try {
        const state = await s.loginState(browser);

        const wide = await s.open(browser, '/balcao', { width: 1366, height: 768, state });
        await s.placeOrder(wide.page, 'Lista Teste');
        const listed = await wide.page.evaluate(() => document.querySelectorAll('[data-in-kitchen-column] [data-in-kitchen-order]').length);
        const reported = await wide.page.evaluate(() => Number(document.querySelector('[data-in-kitchen-count]').dataset.inKitchenCount));
        if (listed !== reported) s.fail(`contador ${reported} ≠ lista ${listed}`);
        await wide.page.click('button:has-text("Pedidos de hoje")');
        await s.expectNoHorizontalScroll(wide.page, 'pedidos de hoje 1366x768');
        await wide.page.locator('button:has-text("Cancelar")').first().click();
        await wide.page.locator('[role="dialog"]').waitFor();
        await s.expectInViewport(wide.page, '[role="dialog"]', 'modal de cancelamento');
        await wide.page.click('[role="dialog"] button:has-text("Voltar")');
        s.expectNoErrors(wide.errors, 'balcão 1366x768');
        await wide.context.close();

        const tablet = await s.open(browser, '/balcao', { width: 1180, height: 820, state, touch: true });
        await tablet.page.waitForFunction(() => {
            const badge = [...document.querySelectorAll('button')].find((b) => b.textContent.includes('Na cozinha'))?.querySelector('span');
            const count = Number(document.querySelector('[data-in-kitchen-count]').dataset.inKitchenCount);
            return count === 0 || (badge && badge.textContent.trim() === String(count));
        }, null, { timeout: 5000 });
        await tablet.context.close();

        const portrait = await s.open(browser, '/balcao', { width: 800, height: 1280, state, touch: true });
        await portrait.page.tap('button:has-text("Hoje")');
        await s.expectNoHorizontalScroll(portrait.page, 'pedidos de hoje 800x1280');
        s.expectNoErrors(portrait.errors, 'balcão 800x1280');
        await portrait.context.close();

        console.log('OK');
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error.message); process.exit(1); });
EOF
```
Expected: `OK`. The order "Lista Teste" stays in the kitchen queue (it is used again by later checks).

- [ ] **Step 7: Run the full suite**

Run: `./vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 8: Build**

Run: `npm run build`
Expected: the build succeeds. (No PHP changed in this task.)

- [ ] **Step 9: Commit and push**

```bash
git add -A
git commit -m "feat: listas do balcão no estilo novo (na cozinha com contador e pedidos de hoje em cartões)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push origin main
```

---
### Task 5: Kitchen (cozinha) — grid, NOVO/ALTERADO highlight, lateness, no sound

**Files:**
- Modify: `app/Livewire/Cozinha/Board.php`, `resources/views/livewire/cozinha/board.blade.php`, `resources/js/kitchen.js`, `resources/js/device.js`, `resources/views/pages/cozinha.blade.php`
- Test: `tests/Feature/Livewire/KitchenBoardTest.php`

**Interfaces:**
- Consumes: `nana-highlight` utility, `shadow-hard`, `font-display`, tokens, `<x-ui.topbar title current>` (slots `badge`, `end`), `<x-ui.badge>`, `config('comanda.kitchen_warn_minutes')`, `config('comanda.kitchen_late_minutes')` (Task 1); `KitchenQueue`, `TransitionOrder`, gate `mark-ready` (unchanged).
- Produces:
  - `Board::onOrdersChanged(array $payload = []): void` dispatches the browser event `kitchen-highlight` with `orderId: int` and `kind: 'created'|'updated'` only for those two kinds (never for `ready`, `unready`, `delivered`, `canceled`). The `kitchen-beep` event no longer exists.
  - `Board::render()` passes `$warnMinutes` and `$lateMinutes` (ints from config) to the view.
  - Card markup: `<article data-order-id data-number data-kitchen-state="ok|warn|late">`, PRONTO button `data-ready-button`, grid `data-kitchen-grid` (auto-fill columns of at least 300 px → 4 columns at 1366 px), footer counter `data-kitchen-more` ("+N pedidos abaixo ↓").
  - Alpine data `kitchenBoard`: existing numpad/undo API plus `highlights` (order id → kind), `highlight({orderId, kind})`, `acknowledge(id)`, `isHighlighted(id)`, `highlightKind(id)`, `below` (cards not fully visible), `scheduleOverflow()`, `updateOverflow()`, `isActive()` (numpad keys are ignored while the board is hidden, e.g. the Estação in single-monitor mode in Task 6).
  - `device.js` exports `audio()`, `beep()` (used only by the TV gate), `chime()`, `requestWakeLock()`, `keepScreenAwake()` (requests the screen wake lock now and again on every `visibilitychange` to visible; idempotent). `deviceGate` keeps its API for the TV.
  - `/cozinha` has no "Toque para começar".

Existing assertions in `KitchenBoardTest.php`: `shows only kitchen items with notes, takeaway and edited flags` keeps passing (VIAGEM and the persistent ALTERADO badge for edited orders stay); `beeps for new and edited orders only` is replaced by the highlight tests below because the spec removes the kitchen beep.

- [ ] **Step 1: Write the failing tests.** Replace `tests/Feature/Livewire/KitchenBoardTest.php` with:

```php
<?php

use App\Actions\Orders\UpdateOrderItems;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Cozinha\Board;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->cozinha = userWithRole(Role::Cozinha);
});

it('shows only kitchen items with notes, takeaway and edited flags', function () {
    $dish = kitchenProduct(['name' => 'Filé de peixe']);
    $soda = cabineProduct(['name' => 'Guaraná lata']);
    $order = placeOrder([item($dish, 2, 'Sem arroz'), item($soda)], ['customer_name' => 'Rita', 'takeaway' => true]);
    app(UpdateOrderItems::class)->handle($order, ['customer_name' => 'Rita', 'takeaway' => true, 'items' => [item($dish, 2, 'Sem arroz'), item($soda)]]);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSee('001')->assertSee('Rita')
        ->assertSee('Filé de peixe')->assertSee('Sem arroz')
        ->assertSee('VIAGEM')->assertSee('ALTERADO')
        ->assertDontSee('Guaraná lata');
});

it('tags only takeaway orders with VIAGEM', function () {
    placeOrder([item(kitchenProduct())], ['customer_name' => 'Lia', 'takeaway' => false]);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSee('Lia')
        ->assertDontSee('VIAGEM');
});

it('marks ready by id and by typed number', function () {
    $dish = kitchenProduct();
    $first = placeOrder([item($dish)]);
    $second = placeOrder([item($dish)]);

    $component = Livewire::actingAs($this->cozinha)->test(Board::class)->call('markReady', $first->id);
    expect($first->fresh()->status)->toBe(OrderStatus::Ready);

    $component->call('markReadyByNumber', 2)->assertReturned($second->id);
    expect($second->fresh()->status)->toBe(OrderStatus::Ready);

    $component->call('markReadyByNumber', 99)->assertReturned(null)->assertDispatched('kitchen-not-found', number: '099');
});

it('lets the kitchen undo only inside the window', function () {
    $order = placeOrder([item(kitchenProduct())]);
    $component = Livewire::actingAs($this->cozinha)->test(Board::class)->call('markReady', $order->id);

    $component->call('undoReady', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    $component->call('markReady', $order->id);
    $this->travel(30)->seconds();
    $component->call('undoReady', $order->id)->assertForbidden();
});

it('highlights new and edited orders', function (string $kind) {
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->call('onOrdersChanged', ['orderId' => 5, 'kind' => $kind])
        ->assertDispatched('kitchen-highlight', orderId: 5, kind: $kind);
})->with(['created', 'updated']);

it('does not highlight other changes', function (string $kind) {
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->call('onOrdersChanged', ['orderId' => 5, 'kind' => $kind])
        ->assertNotDispatched('kitchen-highlight');
})->with(['ready', 'unready', 'delivered', 'canceled']);

it('turns the time orange at the warn limit and the card pink at the late limit', function () {
    placeOrder([item(kitchenProduct())], ['customer_name' => 'Rafa']);

    $this->travel(14)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('data-kitchen-state="ok"')
        ->assertSee('14 min');

    $this->travel(1)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('data-kitchen-state="warn"')
        ->assertSeeHtml('text-nana-orange');

    $this->travel(10)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSeeHtml('data-kitchen-state="late"')
        ->assertSeeHtml('bg-[#FFE9E9]')
        ->assertSee('25 min');
});

it('reads the lateness limits from config', function () {
    config(['comanda.kitchen_warn_minutes' => 5, 'comanda.kitchen_late_minutes' => 8]);
    placeOrder([item(kitchenProduct())]);

    $this->travel(6)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)->assertSeeHtml('data-kitchen-state="warn"');

    $this->travel(2)->minutes();
    Livewire::actingAs($this->cozinha)->test(Board::class)->assertSeeHtml('data-kitchen-state="late"');
});

it('says "agora" for a brand new order and shows the numpad hint', function () {
    placeOrder([item(kitchenProduct())]);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSee('agora')
        ->assertSee('para marcar pronto')
        ->assertSeeHtml('data-kitchen-grid')
        ->assertSeeHtml('data-ready-button')
        ->assertSeeHtml('tabindex="-1" @pointerdown.prevent @mousedown.prevent');
});

it('has no kitchen sound left', function () {
    expect(file_get_contents(app_path('Livewire/Cozinha/Board.php')))->not->toContain('kitchen-beep')
        ->and(file_get_contents(resource_path('views/livewire/cozinha/board.blade.php')))->not->toContain('beep')
        ->and(file_get_contents(resource_path('js/kitchen.js')))->not->toContain('beep');
});

it('opens the kitchen page without the start gate', function () {
    $this->actingAs($this->cozinha)->get('/cozinha')
        ->assertOk()
        ->assertSeeLivewire(Board::class)
        ->assertDontSee('Toque para começar');
});

it('refuses guests', function () {
    $order = placeOrder([item(kitchenProduct())]);

    Livewire::test(Board::class)->call('markReady', $order->id)->assertForbidden();
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Livewire/KitchenBoardTest.php`
Expected: FAIL — `kitchen-highlight` is never dispatched, there is no `data-kitchen-state`, the board still mentions `beep`, and `/cozinha` still shows "Toque para começar".

- [ ] **Step 3: Update the component.** In `app/Livewire/Cozinha/Board.php`, replace the `onOrdersChanged()` and `render()` methods with:

```php
    #[On('echo-private:pedidos,OrdersChanged')]
    public function onOrdersChanged(array $payload = []): void
    {
        $kind = $payload['kind'] ?? null;

        if (in_array($kind, ['created', 'updated'], true)) {
            $this->dispatch('kitchen-highlight', orderId: (int) ($payload['orderId'] ?? 0), kind: $kind);
        }
    }
```

```php
    public function render(): View
    {
        return view('livewire.cozinha.board', [
            'warnMinutes' => (int) config('comanda.kitchen_warn_minutes'),
            'lateMinutes' => (int) config('comanda.kitchen_late_minutes'),
        ]);
    }
```

The rest of the class (`orders`, `markReady`, `markReadyByNumber`, `undoReady`, `onReconnected`) stays as it is.

- [ ] **Step 4: Replace `resources/views/livewire/cozinha/board.blade.php`** with (mockup 04, state 2):

```blade
<div x-data="kitchenBoard" @kitchen-highlight.window="highlight($event.detail)" @kitchen-not-found.window="notFound($event.detail.number)"
     wire:poll.20s class="relative flex h-full min-h-0 flex-col bg-nana-cream text-nana-ink">
    <x-ui.topbar title="Cozinha" current="cozinha">
        <x-slot:badge>
            <span class="rounded-md bg-nana-ink px-2.5 py-1 text-lg font-extrabold leading-none text-nana-cream tabular-nums">{{ $this->orders->count() }}</span>
        </x-slot:badge>
        <x-slot:end>
            <span x-show="typed" x-cloak class="rounded-md border-2 border-nana-ink bg-nana-yellow px-3 py-1 text-xl font-extrabold text-nana-ink" x-text="'Nº ' + typed"></span>
            <span x-data="clock" x-text="time" class="text-xl font-extrabold tabular-nums"></span>
        </x-slot:end>
    </x-ui.topbar>

    <div data-kitchen-grid @scroll.passive="scheduleOverflow()"
         class="grid min-h-0 flex-1 auto-rows-min grid-cols-[repeat(auto-fill,minmax(300px,1fr))] content-start items-start gap-4 overflow-y-auto p-4">
        @forelse ($this->orders as $index => $order)
            @php($minutes = (int) floor(abs($order->created_at->diffInMinutes(now()))))
            @php($state = $minutes >= $lateMinutes ? 'late' : ($minutes >= $warnMinutes ? 'warn' : 'ok'))
            <article wire:key="k-{{ $order->id }}" data-order-id="{{ $order->id }}" data-number="{{ $order->displayNumber() }}" data-kitchen-state="{{ $state }}"
                     @pointerdown.prevent @mousedown.prevent @click="acknowledge({{ $order->id }})"
                     :class="{ 'nana-highlight': isHighlighted({{ $order->id }}), 'ring-4 ring-nana-ink ring-offset-4 ring-offset-nana-cream': selected === {{ $index }} }"
                     @class([
                         'flex flex-col rounded-lg border-2 border-nana-ink shadow-hard',
                         'bg-white' => $state !== 'late',
                         'bg-[#FFE9E9]' => $state === 'late',
                     ])>
                <header class="flex items-baseline justify-between gap-2 px-4 pt-3">
                    <span class="flex items-center gap-2">
                        <span class="font-display text-[2.6rem] leading-none tabular-nums">{{ $order->displayNumber() }}</span>
                        <span x-show="highlightKind({{ $order->id }}) === 'created'" x-cloak
                              class="rounded-md border-2 border-nana-ink bg-nana-yellow px-2 py-0.5 text-base font-extrabold leading-tight">NOVO</span>
                        <span x-show="highlightKind({{ $order->id }}) === 'updated'" x-cloak
                              class="rounded-md border-2 border-nana-ink bg-nana-yellow px-2 py-0.5 text-base font-extrabold leading-tight">ALTERADO</span>
                    </span>
                    <span @class([
                        'text-xl font-extrabold tabular-nums',
                        'text-nana-muted' => $state === 'ok',
                        'text-nana-orange' => $state === 'warn',
                        'rounded-md border-2 border-nana-ink bg-nana-red px-2 py-0.5 text-white' => $state === 'late',
                    ])>{{ $minutes === 0 ? 'agora' : $minutes.' min' }}</span>
                </header>
                <p class="truncate px-4 text-2xl font-extrabold leading-tight">{{ $order->customer_name }}</p>
                @if ($order->takeaway || $order->edited_at)
                    <div class="flex flex-wrap gap-2 px-4 pt-1">
                        @if ($order->takeaway)
                            <x-ui.badge tone="outline">VIAGEM</x-ui.badge>
                        @endif
                        @if ($order->edited_at)
                            <x-ui.badge tone="red" x-show="highlightKind({{ $order->id }}) !== 'updated'">ALTERADO</x-ui.badge>
                        @endif
                    </div>
                @endif
                <ul class="space-y-1 px-4 pb-3 pt-2 text-xl font-semibold leading-snug">
                    @foreach ($order->items as $item)
                        <li>
                            <span class="font-extrabold">{{ $item->quantity }}×</span> {{ $item->name_snapshot }}
                            @if ($item->notes)
                                <span class="font-extrabold text-nana-red">{{ $item->notes }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <button type="button" data-ready-button tabindex="-1" @pointerdown.prevent @mousedown.prevent
                        @click.stop="ready({{ $order->id }}, '{{ $order->displayNumber() }}')"
                        class="mt-auto w-full rounded-b-md border-t-2 border-nana-ink bg-nana-green py-3.5 font-display text-2xl tracking-wide text-white active:brightness-90">PRONTO</button>
            </article>
        @empty
            <p class="col-span-full py-24 text-center text-2xl font-bold text-nana-muted">Nenhum pedido na fila.</p>
        @endforelse
    </div>

    <footer class="flex shrink-0 items-center gap-3 bg-nana-ink px-4 py-2.5 text-base font-bold text-nana-cream">
        <span>Teclado numérico: digite <kbd class="rounded bg-nana-cream px-1.5 py-0.5 font-extrabold text-nana-ink">14</kbd> e <kbd class="rounded bg-nana-cream px-1.5 py-0.5 font-extrabold text-nana-ink">Enter</kbd> para marcar pronto</span>
        <span data-kitchen-more x-show="below > 0" x-cloak x-text="'+' + below + (below === 1 ? ' pedido abaixo ↓' : ' pedidos abaixo ↓')"
              class="ml-auto rounded-md bg-nana-yellow px-3 py-1 font-extrabold text-nana-ink"></span>
    </footer>

    <div x-show="undoBar" x-cloak
         class="absolute inset-x-4 bottom-16 z-10 flex items-center justify-between gap-4 rounded-lg border-2 border-nana-ink bg-nana-green px-5 py-3 text-xl font-extrabold text-white shadow-hard">
        <span x-text="undoBar"></span>
        <button type="button" tabindex="-1" @pointerdown.prevent @mousedown.prevent @click="undo()"
                class="min-h-11 rounded-md border-2 border-nana-ink bg-white px-4 text-nana-ink">Desfazer (−)</button>
    </div>
</div>
```

The article itself also prevents `pointerdown`/`mousedown`, so tapping a card to acknowledge it never moves focus away from the counter's name field in the Estação (preventing those events does not cancel `click`, as validated in phase 1, docs/validacoes.md §1).

- [ ] **Step 5: Replace `resources/js/kitchen.js`** with:

```js
import { keepScreenAwake } from './device';

const UNDO_MS = 10000;
const HIGHLIGHT_MS = 60000;
const HIGHLIGHT_KINDS = ['created', 'updated'];

export function registerKitchen(Alpine) {
    Alpine.data('kitchenBoard', () => {
        const highlightTimers = new Map();
        let resizeObserver = null;
        let mutationObserver = null;
        let frame = null;

        return {
            selected: 0,
            typed: '',
            lastReady: null,
            undoBar: '',
            undoTimer: null,
            keyHandler: null,
            highlights: {},
            below: 0,
            init() {
                this.keyHandler = (event) => this.onKey(event);
                window.addEventListener('keydown', this.keyHandler, { capture: true });
                keepScreenAwake();
                this.$nextTick(() => {
                    const grid = this.grid();
                    if (!grid) {
                        return;
                    }
                    resizeObserver = new ResizeObserver(() => this.scheduleOverflow());
                    resizeObserver.observe(grid);
                    mutationObserver = new MutationObserver(() => this.scheduleOverflow());
                    mutationObserver.observe(grid, { childList: true, subtree: true, characterData: true });
                    this.updateOverflow();
                });
            },
            destroy() {
                window.removeEventListener('keydown', this.keyHandler, { capture: true });
                resizeObserver?.disconnect();
                mutationObserver?.disconnect();
                cancelAnimationFrame(frame);
                highlightTimers.forEach((timer) => clearTimeout(timer));
                highlightTimers.clear();
                clearTimeout(this.undoTimer);
            },
            grid() {
                return this.$root.querySelector('[data-kitchen-grid]');
            },
            cards() {
                return [...this.$root.querySelectorAll('[data-order-id]')];
            },
            isActive() {
                // Hidden board (Estação with one monitor): leave the numpad to the counter.
                return this.$root.offsetParent !== null;
            },
            highlight({ orderId, kind }) {
                const id = Number(orderId);
                if (!id || !HIGHLIGHT_KINDS.includes(kind)) {
                    return;
                }
                clearTimeout(highlightTimers.get(id));
                this.highlights[id] = kind;
                highlightTimers.set(id, setTimeout(() => this.acknowledge(id), HIGHLIGHT_MS));
            },
            acknowledge(id) {
                clearTimeout(highlightTimers.get(id));
                highlightTimers.delete(id);
                delete this.highlights[id];
            },
            isHighlighted(id) {
                return id in this.highlights;
            },
            highlightKind(id) {
                return this.highlights[id] ?? null;
            },
            scheduleOverflow() {
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => this.updateOverflow());
            },
            updateOverflow() {
                const grid = this.grid();
                if (!grid) {
                    this.below = 0;
                    return;
                }
                const bottom = grid.getBoundingClientRect().bottom;
                this.below = this.cards().filter((card) => card.getBoundingClientRect().bottom > bottom + 1).length;
            },
            notFound(number) {
                this.undoBar = '';
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: `Pedido ${number} não está na fila.` } }));
            },
            onKey(event) {
                if (!event.code || !event.code.startsWith('Numpad') || !this.isActive()) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();

                const count = this.cards().length;
                if (this.selected > count - 1) {
                    this.selected = Math.max(0, count - 1);
                }

                const digit = event.code.match(/^Numpad(\d)$/);
                if (digit) {
                    this.typed = (this.typed + digit[1]).slice(-3);
                } else if (event.code === 'NumpadEnter') {
                    this.confirm();
                } else if (event.code === 'NumpadAdd') {
                    this.selected = count === 0 ? 0 : (this.selected + 1) % count;
                } else if (event.code === 'NumpadDecimal') {
                    this.typed = '';
                } else if (event.code === 'NumpadSubtract') {
                    this.undo();
                }
            },
            async confirm() {
                if (this.typed) {
                    const number = parseInt(this.typed, 10);
                    this.typed = '';
                    const id = await this.$wire.markReadyByNumber(number);
                    if (id) {
                        this.remember(id, String(number).padStart(3, '0'));
                    }
                    return;
                }
                const card = this.cards()[this.selected];
                if (card) {
                    this.ready(parseInt(card.dataset.orderId, 10), card.dataset.number);
                }
            },
            ready(id, number) {
                this.acknowledge(id);
                this.$wire.markReady(id);
                this.remember(id, number);
            },
            remember(id, number) {
                this.lastReady = { id, at: Date.now() };
                this.undoBar = `Pedido ${number} pronto`;
                clearTimeout(this.undoTimer);
                this.undoTimer = setTimeout(() => { this.undoBar = ''; this.lastReady = null; }, UNDO_MS);
            },
            undo() {
                if (!this.lastReady || Date.now() - this.lastReady.at > UNDO_MS) {
                    return;
                }
                this.$wire.undoReady(this.lastReady.id);
                this.lastReady = null;
                this.undoBar = '';
                clearTimeout(this.undoTimer);
            },
        };
    });
}
```

- [ ] **Step 6: Wake lock without a gesture.** Replace `resources/js/device.js` with:

```js
let audioCtx = null;
let wakeLock = null;
let keepingAwake = false;

export function audio() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioCtx;
}

function tone(freq, start, duration, volume = 0.3) {
    const ctx = audio();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0, ctx.currentTime + start);
    gain.gain.linearRampToValueAtTime(volume, ctx.currentTime + start + 0.02);
    gain.gain.linearRampToValueAtTime(0, ctx.currentTime + start + duration);
    osc.connect(gain).connect(ctx.destination);
    osc.start(ctx.currentTime + start);
    osc.stop(ctx.currentTime + start + duration + 0.05);
}

/** Short confirmation sound of the TV "Toque para começar" gate. */
export function beep() {
    tone(880, 0, 0.18);
    tone(880, 0.25, 0.18);
}

export function chime() {
    tone(660, 0, 0.35, 0.4);
    tone(880, 0.3, 0.35, 0.4);
    tone(1320, 0.6, 0.6, 0.4);
}

export async function requestWakeLock() {
    try {
        if ('wakeLock' in navigator && document.visibilityState === 'visible') {
            wakeLock = await navigator.wakeLock.request('screen');
        }
    } catch (e) {
        // sem suporte ou negado: segue sem travar a tela
    }
}

/** Keeps the screen on: asks now and again whenever the page becomes visible. Needs no user gesture. */
export function keepScreenAwake() {
    if (keepingAwake) {
        return;
    }
    keepingAwake = true;
    requestWakeLock();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            requestWakeLock();
        }
    });
}

export function registerDevice(Alpine) {
    Alpine.data('deviceGate', (fullscreen = false) => ({
        open: true,
        init() {
            // Fully Kiosk / Chrome com autoplay liberado: o áudio já nasce destravado.
            if (audio().state === 'running') {
                keepScreenAwake();
                this.open = false;
            }
        },
        async start() {
            await audio().resume();
            beep();
            keepScreenAwake();
            if (fullscreen && document.documentElement.requestFullscreen) {
                try {
                    await document.documentElement.requestFullscreen();
                } catch (e) {
                    // tela cheia recusada: segue normal
                }
            }
            this.open = false;
        },
    }));

    Alpine.data('clock', () => ({
        time: '',
        init() {
            const tick = () => {
                this.time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            };
            tick();
            setInterval(tick, 10000);
        },
    }));
}
```

- [ ] **Step 7: The kitchen page.** Replace `resources/views/pages/cozinha.blade.php` with:

```blade
<x-layouts.app title="Cozinha" body-class="bg-nana-cream overflow-hidden">
    <div class="h-dvh"><livewire:cozinha.board /></div>
</x-layouts.app>
```

(`pages/estacao.blade.php` keeps its `<x-device-gate />` until Task 6.)

- [ ] **Step 8: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Livewire/KitchenBoardTest.php tests/Feature/Pages/StationTest.php`
Expected: PASS (StationTest still expects the Estação gate; Task 6 changes that).

- [ ] **Step 9: Browser check.** Run `npm run build` (skip if `public/hot` exists), then (Reverb must be running for the NOVO highlight):

```bash
node - <<'EOF'
const s = require(process.cwd() + '/tests/Browser/support.cjs');
const countTones = () => {
    window.__tones = 0;
    const Ctx = window.AudioContext || window.webkitAudioContext;
    const original = Ctx.prototype.createOscillator;
    Ctx.prototype.createOscillator = function () { window.__tones += 1; return original.call(this); };
};
(async () => {
    const browser = await s.launch();
    try {
        const state = await s.loginState(browser);
        const counter = await s.open(browser, '/balcao', { width: 1366, height: 768, state });
        const kitchen = await s.open(browser, '/cozinha', { width: 1366, height: 768, state, touch: true, init: countTones });

        let status = await s.kitchenStatus(kitchen.page);
        for (let i = status.total; i < 10; i += 1) {
            await s.placeOrder(counter.page, `Cozinha ${i + 1}`);
        }
        await kitchen.page.waitForFunction(() => document.querySelectorAll('[data-kitchen-grid] [data-order-id]').length >= 10, null, { timeout: 15000 });
        await kitchen.page.waitForTimeout(500);

        status = await s.kitchenStatus(kitchen.page);
        const expected = status.below > 0 ? `+${status.below} ${status.below === 1 ? 'pedido abaixo' : 'pedidos abaixo'} ↓` : '';
        if (status.moreText !== expected) s.fail(`rodapé "${status.moreText}" ≠ "${expected}"`);
        if (status.buttonsCut > 0) s.fail(`${status.buttonsCut} botões PRONTO cortados`);
        if (status.total === 10 && status.maxItemsVisible <= 2 && status.visible !== 8) s.fail(`${status.visible} pedidos inteiros visíveis (esperado 8 com 10 pedidos)`);
        await s.expectNoHorizontalScroll(kitchen.page, 'cozinha 1366x768');
        const columns = await kitchen.page.evaluate(() => getComputedStyle(document.querySelector('[data-kitchen-grid]')).gridTemplateColumns.split(' ').length);
        if (columns !== 4) s.fail(`cozinha 1366x768: ${columns} colunas (esperado 4)`);

        await s.placeOrder(counter.page, 'Novo Destaque');
        const card = kitchen.page.locator('[data-order-id]', { hasText: 'Novo Destaque' });
        await card.locator('text=NOVO').waitFor({ state: 'visible', timeout: 10000 });
        if (!(await card.evaluate((el) => el.classList.contains('nana-highlight')))) s.fail('cartão novo sem contorno pulsando');
        await card.locator('p').first().tap();
        await card.locator('text=NOVO').waitFor({ state: 'hidden', timeout: 3000 });
        const tones = await kitchen.page.evaluate(() => window.__tones);
        if (tones !== 0) s.fail(`a cozinha tocou ${tones} sons`);
        if (await kitchen.page.locator('text=Toque para começar').count()) s.fail('a cozinha ainda tem "Toque para começar"');
        s.expectNoErrors(kitchen.errors, 'cozinha');
        await kitchen.context.close();

        const calm = await s.open(browser, '/cozinha', { width: 1366, height: 768, state, reducedMotion: 'reduce' });
        await s.placeOrder(counter.page, 'Sem Movimento');
        const calmCard = calm.page.locator('[data-order-id]', { hasText: 'Sem Movimento' });
        await calmCard.locator('text=NOVO').waitFor({ state: 'visible', timeout: 10000 });
        const animation = await calmCard.evaluate((el) => ({ name: getComputedStyle(el).animationName, outline: getComputedStyle(el).outlineWidth }));
        if (animation.name !== 'none' || animation.outline !== '6px') s.fail(`movimento reduzido: ${JSON.stringify(animation)}`);
        await calm.context.close();
        await counter.context.close();

        console.log('OK', status);
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error.message); process.exit(1); });
EOF
```
Expected: `OK { total: 10, visible: 8, below: 2, buttonsCut: 0, moreText: '+2 pedidos abaixo ↓', ... }` when the kitchen started empty (other numbers if the local queue already had orders; the footer text must always match `below`). Compare a manual screenshot with mockup 04 state 2. The test orders stay in the local queue; Task 8 reuses them.

- [ ] **Step 10: Run the full suite**

Run: `./vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 11: Format and build**

Run: `vendor/bin/pint --dirty --format agent && npm run build`
Expected: no Pint issues left; the build succeeds.

- [ ] **Step 12: Commit and push**

```bash
git add -A
git commit -m "feat: cozinha em grade com destaque NOVO/ALTERADO, atraso por cor e sem som" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push origin main
```

---
### Task 6: Estação — monitor detection and single-monitor mode

**Files:**
- Create: `resources/js/station.js`
- Modify: `resources/views/pages/estacao.blade.php`, `resources/js/app.js`
- Test: `tests/Feature/Pages/StationTest.php`

`app/Http/Controllers/StationController.php` already passes `leftWidth` and `rightWidth` from `Setting` (defaults 1366 since Task 1); it does not change.

**Interfaces:**
- Consumes: `<x-balcao-pane current="estacao">` with the `topbar-end` slot (Task 3); `kitchenBoard.isActive()` (Task 5: a hidden board ignores numpad keys); toasts (`window` event `toast` with `detail.type` and `detail.message`); `StationController` view data `$leftWidth`, `$rightWidth` (ints).
- Produces:
  - Alpine data `station(configuredLeft = 1366, configuredRight = 1366)` in `resources/js/station.js`: `leftWidth` (the `localStorage` value `nana.station.left` when it is an integer ≥ 800, else `configuredLeft`), `rightWidth`, `viewport`, getters `single` (`viewport < leftWidth + 600`) and `columns` (`'minmax(0, 1fr)'` in single mode, else `` `${leftWidth}px minmax(0, ${rightWidth}px)` ``), `detecting`, `detect()` (feature-detects `window.getScreenDetails()`, stores the width of the left-most screen, reports through a toast).
  - Page markup: root `x-data="station({left}, {right})" data-configured-left-width="{left}"`, columns `data-station-left` (balcão pane) and `data-station-right` (kitchen board, hidden in single mode). No device gate.
  - Copy: button "Detectar monitores"; single-mode note "Cozinha no 2º monitor não encontrada"; failure/unsupported toasts point to "Administração → Configurações".

- [ ] **Step 1: Write the failing tests.** Replace `tests/Feature/Pages/StationTest.php` with:

```php
<?php

use App\Enums\Role;
use App\Livewire\Balcao\InKitchenList;
use App\Livewire\Balcao\OrderForm;
use App\Livewire\Balcao\TodayOrders;
use App\Livewire\Cozinha\Board;
use App\Models\Setting;

it('renders counter and kitchen with the default 1366 px left monitor and no start gate', function () {
    $this->actingAs(userWithRole(Role::Balcao))->get('/estacao')
        ->assertOk()
        ->assertSee('data-configured-left-width="1366"', false)
        ->assertSee('x-data="station(1366, 1366)"', false)
        ->assertSee('data-station-left', false)
        ->assertSee('data-station-right', false)
        ->assertSee('Detectar monitores')
        ->assertSee('Cozinha no 2º monitor não encontrada')
        ->assertDontSee('Toque para começar')
        ->assertDontSee('grid-template-columns: 1366px', false)
        ->assertSeeLivewire(OrderForm::class)
        ->assertSeeLivewire(TodayOrders::class)
        ->assertSeeLivewire(InKitchenList::class)
        ->assertSeeLivewire(Board::class);
});

it('uses the widths saved by the admin', function () {
    Setting::put(Setting::STATION_LEFT_WIDTH, 1600);
    Setting::put(Setting::STATION_RIGHT_WIDTH, 1280);

    $this->actingAs(userWithRole(Role::Admin))->get('/estacao')
        ->assertOk()
        ->assertSee('data-configured-left-width="1600"', false)
        ->assertSee('x-data="station(1600, 1280)"', false);
});

it('renders the counter alone on /balcao', function () {
    $this->actingAs(userWithRole(Role::Balcao))->get('/balcao')
        ->assertOk()
        ->assertSeeLivewire(OrderForm::class)
        ->assertDontSeeLivewire(Board::class)
        ->assertDontSee('Detectar monitores');
});

it('renders the counter pane with its tabs as a size container', function () {
    $this->actingAs(userWithRole(Role::Balcao))->get('/balcao')
        ->assertOk()
        ->assertSee('x-data="balcaoPane"', false)
        ->assertSee('@container/pane', false)
        ->assertSee('Novo pedido')
        ->assertSee('Pedidos de hoje')
        ->assertSee('Na cozinha')
        ->assertSee('data-in-kitchen-column', false)
        ->assertSee('Telas')
        ->assertDontSee('Toque para começar');
});
```

(The first test replaces the old `renders counter and kitchen side by side with the configured widths`, which asserted the fixed inline grid `grid-template-columns: 1920px 1366px` and the start gate — both removed by the spec.)

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Pages/StationTest.php`
Expected: FAIL — the page still has the inline fixed grid, the device gate and no `station(...)`.

- [ ] **Step 3: Create `resources/js/station.js`:**

```js
const KEY = 'nana.station.left';
const KITCHEN_MIN = 600;
const SETTINGS_HINT = 'Ajuste a largura em Administração → Configurações.';

function storedLeft() {
    try {
        const value = parseInt(localStorage.getItem(KEY) || '', 10);
        return Number.isInteger(value) && value >= 800 ? value : null;
    } catch (e) {
        return null;
    }
}

function storeLeft(value) {
    try { localStorage.setItem(KEY, String(value)); } catch (e) { /* armazenamento indisponível */ }
}

function toast(type, message) {
    window.dispatchEvent(new CustomEvent('toast', { detail: { type, message, timeout: 6000 } }));
}

export function registerStation(Alpine) {
    Alpine.data('station', (configuredLeft = 1366, configuredRight = 1366) => ({
        leftWidth: storedLeft() ?? configuredLeft,
        rightWidth: configuredRight,
        viewport: window.innerWidth,
        detecting: false,
        onResize: null,
        init() {
            this.onResize = () => { this.viewport = window.innerWidth; };
            window.addEventListener('resize', this.onResize);
        },
        destroy() {
            window.removeEventListener('resize', this.onResize);
        },
        get single() {
            return this.viewport < this.leftWidth + KITCHEN_MIN;
        },
        get columns() {
            return this.single ? 'minmax(0, 1fr)' : `${this.leftWidth}px minmax(0, ${this.rightWidth}px)`;
        },
        async detect() {
            if (!('getScreenDetails' in window)) {
                toast('error', `Este navegador não detecta os monitores. ${SETTINGS_HINT}`);
                return;
            }
            this.detecting = true;
            try {
                const details = await window.getScreenDetails();
                const screens = [...details.screens].sort((a, b) => a.left - b.left);
                this.leftWidth = screens[0].width;
                storeLeft(screens[0].width);
                toast('success', screens.length > 1
                    ? `Monitor do balcão: ${screens[0].width} px. Cozinha no monitor ao lado.`
                    : `Só um monitor encontrado (${screens[0].width} px).`);
            } catch (e) {
                toast('error', `Não foi possível detectar os monitores. ${SETTINGS_HINT}`);
            } finally {
                this.detecting = false;
            }
        },
    }));
}
```

Replace `resources/js/app.js` with:

```js
import '@fontsource/archivo/500.css';
import '@fontsource/archivo/600.css';
import '@fontsource/archivo/700.css';
import '@fontsource/archivo/800.css';
import '@fontsource/archivo-black/400.css';
import './echo';
import { registerDevice } from './device';
import { registerToasts } from './toasts';
import { registerConnection } from './connection';
import { registerNetworkErrors } from './network';
import { registerOrderDraft } from './order-draft';
import { registerKitchen } from './kitchen';
import { registerAnnouncer } from './announcer';
import { registerLauncher } from './launcher';
import { registerBalcao } from './balcao';
import { registerStation } from './station';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    registerDevice(Alpine);
    registerToasts(Alpine);
    registerConnection(Alpine);
    registerOrderDraft(Alpine);
    registerKitchen(Alpine);
    registerAnnouncer(Alpine);
    registerLauncher(Alpine);
    registerBalcao(Alpine);
    registerStation(Alpine);
});

registerNetworkErrors();
```

- [ ] **Step 4: Replace `resources/views/pages/estacao.blade.php`** with:

```blade
<x-layouts.app title="Estação" body-class="bg-nana-cream overflow-hidden">
    <div x-data="station({{ $leftWidth }}, {{ $rightWidth }})" data-configured-left-width="{{ $leftWidth }}"
         class="grid h-dvh" style="grid-template-columns: minmax(0, 1fr)" :style="{ gridTemplateColumns: columns }">
        <div data-station-left class="h-dvh min-w-0">
            <x-balcao-pane current="estacao">
                <x-slot:topbar-end>
                    <span x-show="single" x-cloak class="text-sm font-bold text-[#FFE3B0]">Cozinha no 2º monitor não encontrada</span>
                    <button type="button" @click="detect()" :disabled="detecting"
                            class="min-h-11 rounded-md border-2 border-nana-cream px-3 text-sm font-extrabold text-nana-cream disabled:opacity-45">Detectar monitores</button>
                </x-slot:topbar-end>
            </x-balcao-pane>
        </div>
        <div data-station-right x-show="! single" x-cloak class="h-dvh min-w-0 border-l-4 border-nana-ink">
            <livewire:cozinha.board />
        </div>
    </div>
</x-layouts.app>
```

In single-monitor mode the pane takes the whole window, so its container width makes it the three-column counter with "Na cozinha"; the kitchen board stays mounted but hidden and ignores the numpad (`isActive()`), so numpad digits reach the counter.

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Pages/StationTest.php tests/Feature/Ui/ComponentsTest.php`
Expected: PASS (the device gate component itself still renders for the TV).

- [ ] **Step 6: Browser check.** Run `npm run build` (skip if `public/hot` exists), then:

```bash
node - <<'EOF'
const s = require(process.cwd() + '/tests/Browser/support.cjs');
const leftIs1366 = () => { try { localStorage.setItem('nana.station.left', '1366'); } catch (e) {} };
(async () => {
    const browser = await s.launch();
    try {
        const state = await s.loginState(browser);

        const two = await s.open(browser, '/estacao', { width: 2732, height: 768, state, touch: true, init: leftIs1366 });
        const widths = await two.page.evaluate(() => ({
            left: document.querySelector('[data-station-left]').getBoundingClientRect().width,
            right: document.querySelector('[data-station-right]').getBoundingClientRect().width,
        }));
        if (Math.round(widths.left) !== 1366 || Math.round(widths.right) !== 1366) s.fail(`larguras ${JSON.stringify(widths)} (esperado 1366 + 1366)`);
        await s.expectNoHorizontalScroll(two.page, 'estação 2732x768');
        await s.expectInViewport(two.page, '[data-send-button]', 'estação: Enviar');
        if (await two.page.locator('text=Toque para começar').count()) s.fail('a estação ainda tem "Toque para começar"');
        const status = await s.kitchenStatus(two.page, '[data-station-right]');
        if (status.buttonsCut > 0) s.fail(`estação: ${status.buttonsCut} botões PRONTO cortados`);

        const name = two.page.locator('[x-ref="name"]');
        await name.click();
        await two.page.keyboard.type('Foco');
        const firstCard = two.page.locator('[data-station-right] [data-order-id]').first();
        if (await firstCard.count()) {
            await firstCard.locator('p').first().tap();
            await firstCard.locator('[data-ready-button]').tap();
            await two.page.locator('[data-station-right] button:has-text("Desfazer")').tap();
        }
        await two.page.keyboard.type('Teste');
        const focus = await two.page.evaluate(() => ({ ref: document.activeElement?.getAttribute('x-ref'), value: document.querySelector('[x-ref="name"]').value }));
        if (focus.ref !== 'name' || focus.value !== 'FocoTeste') s.fail(`foco perdido: ${JSON.stringify(focus)}`);
        await two.page.keyboard.press('Numpad1');
        const afterNumpad = await two.page.evaluate(() => document.querySelector('[x-ref="name"]').value);
        if (afterNumpad !== 'FocoTeste') s.fail('dígito do teclado numérico caiu no nome com a cozinha visível');
        await two.page.keyboard.press('NumpadDecimal');
        await name.fill('');
        await two.page.click('button:has-text("Detectar monitores")');
        await two.page.locator('[x-data="toasts"] span').first().waitFor({ timeout: 5000 });
        s.expectNoErrors(two.errors, 'estação 2732x768');
        await two.context.close();

        const one = await s.open(browser, '/estacao', { width: 1366, height: 768, state, init: leftIs1366 });
        const single = await one.page.evaluate(() => ({
            kitchenVisible: document.querySelector('[data-station-right]').getClientRects().length > 0,
            noteVisible: [...document.querySelectorAll('span')].some((el) => el.textContent.includes('Cozinha no 2º monitor não encontrada') && el.getClientRects().length > 0),
            aside: document.querySelector('[data-in-kitchen-column]').getClientRects().length > 0,
        }));
        if (single.kitchenVisible || !single.noteVisible || !single.aside) s.fail(`modo um monitor: ${JSON.stringify(single)}`);
        await s.expectNoHorizontalScroll(one.page, 'estação 1366x768');
        await s.expectInViewport(one.page, '[data-send-button]', 'estação 1366x768: Enviar');
        await one.page.click('[x-ref="name"]');
        await one.page.keyboard.press('Numpad4');
        const typed = await one.page.evaluate(() => document.querySelector('[x-ref="name"]').value);
        if (typed !== '4') s.fail(`modo um monitor: teclado numérico deveria digitar no nome (veio "${typed}")`);
        await one.page.fill('[x-ref="name"]', '');
        s.expectNoErrors(one.errors, 'estação 1366x768');
        await one.context.close();

        console.log('OK', widths);
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error.message); process.exit(1); });
EOF
```
Expected: `OK { left: 1366, right: 1366 }`. A detection toast appears either way (headless Chrome usually denies the window-management permission, which shows the "Administração → Configurações" message). The real two-monitor test is done by the owner in the store (spec §12).

- [ ] **Step 7: Run the full suite**

Run: `./vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 8: Build**

Run: `npm run build`
Expected: the build succeeds. (No PHP changed in this task.)

- [ ] **Step 9: Commit and push**

```bash
git add -A
git commit -m "feat: estação detecta os monitores e mostra só o balcão quando há um monitor" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push origin main
```

---
### Task 7: TV panel and phone tracking

**Files:**
- Modify: `app/Livewire/Painel/PublicBoard.php`, `resources/views/livewire/painel/tv.blade.php`, `resources/views/livewire/painel/mobile.blade.php`, `resources/views/pages/painel.blade.php`, `resources/views/pages/acompanhar.blade.php`, `resources/views/components/device-gate.blade.php`, `resources/js/announcer.js`
- Test: `tests/Feature/Livewire/PublicBoardTest.php`

**Interfaces:**
- Consumes: `BoardQuery::get()` (unchanged shape: `preparing` oldest first, `ready` newest first, entries `{id, number, first_name}`), `QrCode::dataUri()`, `announcer` Alpine data (queue + `chime()`, unchanged), `deviceGate` (Task 5), `<x-ui.screens-menu compact up align>` (Task 1), tokens, `font-display`.
- Produces:
  - `PublicBoard::TV_PREPARING_LIMIT = 12`, `PublicBoard::TV_READY_LIMIT = 4`; computed `tvPreparing(): array` (first 12 preparing, oldest first), `tvPreparingOverflow(): int` (how many more), `tvReady(): array` (4 newest ready).
  - TV markup: preparing tiles `data-tv-preparing="{number}"`, overflow tile `data-tv-overflow` showing `+N`, ready cards `data-tv-ready="{number}"`; phone state cards `data-my-order="preparing|ready"` and preparing rows `data-phone-preparing="{number}"`; overlay "PEDIDO PRONTO / número / nome / Retire no balcão" for the announcer's 8 s.
  - Alpine data `trackMine`: keeps `mine` + `localStorage` `nana.mine`, `isMine(number)` and vibration; adds `ready`, `preparing` (lists of numbers), `sync(ready, preparing)`, getters `myNumber` and `myState` (`'empty' | 'preparing' | 'ready' | 'unknown'`). The phone view calls `sync()` from a hidden span keyed by a hash of both lists, so every new board render re-runs it.
  - Public views still expose only id, number and first name.

Existing assertions in `PublicBoardTest.php`: `->assertSee('Prontos')` becomes `->assertSee('Pronto!')` (new TV title from mockup 05); everything else is kept.

- [ ] **Step 1: Write the failing tests.** Replace `tests/Feature/Livewire/PublicBoardTest.php` with:

```php
<?php

use App\Actions\Orders\TransitionOrder;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Painel\PublicBoard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
});

it('shows number and first name only, without items or surnames', function () {
    $dish = kitchenProduct(['name' => 'Filé mignon']);
    placeOrder([item($dish)], ['customer_name' => 'Maria Souza']);
    $ready = placeOrder([item($dish)], ['customer_name' => 'João Silva']);
    app(TransitionOrder::class)->markReady($ready);

    Livewire::test(PublicBoard::class, ['variant' => 'tv'])
        ->assertSee('Preparando')->assertSee('Pronto!')
        ->assertSee('001')->assertSee('Maria')
        ->assertSee('002')->assertSee('João')
        ->assertDontSee('Souza')->assertDontSee('Silva')->assertDontSee('Filé mignon')
        ->assertSee('data:image/svg+xml;base64,', false);
});

it('shows at most 12 preparing orders on the TV, oldest first, and counts the rest', function () {
    $dish = kitchenProduct();
    foreach (range(1, 14) as $index) {
        placeOrder([item($dish)], ['customer_name' => "Cliente{$index}"]);
    }

    $html = Livewire::test(PublicBoard::class, ['variant' => 'tv'])
        ->assertSeeHtml('data-tv-preparing="001"')
        ->assertSeeHtml('data-tv-preparing="012"')
        ->assertDontSeeHtml('data-tv-preparing="013"')
        ->assertSeeHtml('data-tv-overflow')
        ->assertSee('+2')
        ->html();

    expect(substr_count($html, 'data-tv-preparing="'))->toBe(12);
});

it('lists ready orders newest first and keeps the announcement copy', function () {
    $dish = kitchenProduct();
    $first = placeOrder([item($dish)], ['customer_name' => 'Ana']);
    $second = placeOrder([item($dish)], ['customer_name' => 'Bia']);
    app(TransitionOrder::class)->markReady($first);
    $this->travel(1)->minutes();
    app(TransitionOrder::class)->markReady($second);

    Livewire::test(PublicBoard::class, ['variant' => 'tv'])
        ->assertSeeHtmlInOrder(['data-tv-ready="002"', 'data-tv-ready="001"'])
        ->assertSee('PEDIDO PRONTO')
        ->assertSee('Retire no balcão')
        ->assertSee('Acompanhe pelo celular')
        ->assertDontSeeHtml('data-tv-overflow');
});

it('announces ready orders and cancels undone announcements', function () {
    Livewire::test(PublicBoard::class, ['variant' => 'tv'])
        ->call('onBoardChanged', ['orderId' => 7, 'kind' => 'ready', 'number' => '007', 'firstName' => 'Ana', 'status' => 'ready'])
        ->assertDispatched('announce', orderId: 7, number: '007', name: 'Ana')
        ->call('onBoardChanged', ['orderId' => 7, 'kind' => 'unready', 'number' => '007', 'firstName' => 'Ana', 'status' => 'preparing'])
        ->assertDispatched('announce-cancel', orderId: 7);
});

it('renders the phone layout', function () {
    placeOrder([item(kitchenProduct())], ['customer_name' => 'Paula']);

    Livewire::test(PublicBoard::class, ['variant' => 'mobile'])
        ->assertSee('Seu número')->assertSee('001')->assertSee('Paula');
});

it('renders the phone tracker with both states of my order and short lists only', function () {
    $dish = kitchenProduct(['name' => 'Filé mignon']);
    placeOrder([item($dish)], ['customer_name' => 'Paula Souza']);
    $ready = placeOrder([item($dish)], ['customer_name' => 'Rui Lima']);
    app(TransitionOrder::class)->markReady($ready);

    Livewire::test(PublicBoard::class, ['variant' => 'mobile'])
        ->assertSee('Acompanhe seu pedido')
        ->assertSee('Preparando…')
        ->assertSee('PRONTO!')
        ->assertSee('Retire no balcão')
        ->assertSeeHtml('x-init="sync(')
        ->assertSee('Paula')->assertSee('Rui')
        ->assertDontSee('Souza')->assertDontSee('Lima')->assertDontSee('Filé mignon');
});

it('serves both public pages to guests', function () {
    $this->get('/painel')->assertOk()->assertSeeLivewire(PublicBoard::class)->assertSee('Toque para começar');
    $this->get('/acompanhar')->assertOk()->assertSeeLivewire(PublicBoard::class)->assertDontSee('Toque para começar');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Livewire/PublicBoardTest.php`
Expected: FAIL — the TV title is still "Prontos", there is no `data-tv-preparing`, no "+2", no "Retire no balcão" and no `sync(`.

- [ ] **Step 3: Cap the TV lists.** In `app/Livewire/Painel/PublicBoard.php`, add the constants at the top of the class body (right after the opening `{` that follows `class PublicBoard extends Component`, before `#[Locked]`):

```php
    public const TV_PREPARING_LIMIT = 12;

    public const TV_READY_LIMIT = 4;
```

and add these methods right after the existing `board()` method:

```php
    /** @return list<array{id: int, number: string, first_name: string}> */
    #[Computed]
    public function tvPreparing(): array
    {
        return array_slice($this->board['preparing'], 0, self::TV_PREPARING_LIMIT);
    }

    #[Computed]
    public function tvPreparingOverflow(): int
    {
        return max(0, count($this->board['preparing']) - self::TV_PREPARING_LIMIT);
    }

    /** @return list<array{id: int, number: string, first_name: string}> */
    #[Computed]
    public function tvReady(): array
    {
        return array_slice($this->board['ready'], 0, self::TV_READY_LIMIT);
    }
```

- [ ] **Step 4: Replace `resources/views/livewire/painel/tv.blade.php`** with (mockup 05; sizes in `vw` mirror the mockup's `cqw`, so the layout scales with any 16:9 TV):

```blade
<div x-data="announcer(8000)" @announce.window="enqueue($event.detail)" @announce-cancel.window="cancel($event.detail.orderId)"
     wire:poll.20s class="relative grid h-dvh grid-cols-[minmax(0,1fr)_36vw] overflow-hidden bg-nana-cream text-nana-ink">
    <section class="flex min-h-0 flex-col p-[2.3vw]">
        <h1 class="flex items-center gap-[1.2vw] font-display text-[3.6vw] leading-none">
            Preparando
            <span class="rounded-[0.6vw] bg-nana-ink px-[1vw] py-[0.5vw] font-sans text-[1.8vw] font-extrabold text-nana-cream">{{ count($this->board['preparing']) }}</span>
        </h1>
        <div class="mt-[1.6vw] grid grid-cols-4 gap-[1.2vw]">
            @foreach ($this->tvPreparing as $entry)
                <div wire:key="p-{{ $entry['id'] }}" data-tv-preparing="{{ $entry['number'] }}"
                     class="rounded-[0.8vw] border-[0.25vw] border-nana-ink bg-white px-[0.6vw] py-[1.1vw] text-center shadow-[0.35vw_0.35vw_0_var(--color-nana-ink)]">
                    <p class="font-display text-[4.2vw] leading-none tabular-nums">{{ $entry['number'] }}</p>
                    <p class="mt-[0.3vw] truncate text-[1.7vw] font-bold text-nana-muted">{{ $entry['first_name'] }}</p>
                </div>
            @endforeach
            @if ($this->tvPreparingOverflow > 0)
                <div data-tv-overflow class="grid place-items-center rounded-[0.8vw] border-[0.25vw] border-nana-ink bg-nana-ink px-[0.6vw] py-[1.1vw] text-nana-cream">
                    <p class="font-display text-[4.2vw] leading-none">+{{ $this->tvPreparingOverflow }}</p>
                </div>
            @endif
        </div>
        <div class="mt-auto flex items-end justify-between gap-[1vw] pt-[1vw]">
            <span class="font-display text-[2vw] leading-none text-nana-red">{{ \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</span>
            <div class="opacity-40 transition hover:opacity-100 focus-within:opacity-100">
                <x-ui.screens-menu compact up current="painel" />
            </div>
        </div>
    </section>

    <section class="flex min-h-0 flex-col border-l-[0.4vw] border-nana-ink bg-nana-red p-[2.3vw] text-nana-cream">
        <h1 class="flex items-center gap-[1.2vw] font-display text-[3.6vw] leading-none">
            Pronto!
            <span class="rounded-[0.6vw] bg-nana-cream px-[1vw] py-[0.5vw] font-sans text-[1.8vw] font-extrabold text-nana-red">{{ count($this->board['ready']) }}</span>
        </h1>
        <div class="mt-[1.6vw] flex min-h-0 flex-1 flex-col gap-[1.2vw] overflow-hidden">
            @foreach ($this->tvReady as $entry)
                <div wire:key="r-{{ $entry['id'] }}" data-tv-ready="{{ $entry['number'] }}"
                     class="flex items-center justify-between gap-[1vw] rounded-[0.8vw] border-[0.25vw] border-nana-ink bg-nana-cream px-[1.8vw] py-[0.8vw] text-nana-ink shadow-[0.35vw_0.35vw_0_var(--color-nana-ink)]">
                    <span class="font-display text-[4.2vw] leading-none tabular-nums">{{ $entry['number'] }}</span>
                    <span class="truncate text-[2.4vw] font-extrabold">{{ $entry['first_name'] }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-[1.2vw] flex items-end justify-between gap-[1vw]">
            <div class="w-[12vw] rounded-[0.8vw] border-[0.25vw] border-nana-ink bg-white p-[0.9vw] text-center text-nana-ink">
                <img src="{{ $qr }}" alt="QR code para acompanhar o pedido" class="aspect-square w-full">
                <p class="mt-[0.6vw] text-[1.1vw] font-extrabold leading-tight">Acompanhe pelo celular</p>
            </div>
            <span x-data="clock" x-text="time" class="font-display text-[3.6vw] leading-none tabular-nums"></span>
        </div>
    </section>

    <div x-show="current" x-cloak class="absolute inset-0 z-30 flex flex-col items-center justify-center border-[1.4vw] border-nana-ink bg-nana-yellow text-nana-ink">
        <p class="font-display text-[5vw] leading-none">PEDIDO PRONTO</p>
        <p class="mt-[1vw] font-display text-[17vw] leading-none tabular-nums" x-text="current?.number"></p>
        <p class="text-[6vw] font-extrabold leading-none" x-text="current?.name"></p>
        <p class="mt-[1.5vw] rounded-[0.8vw] bg-nana-ink px-[1.6vw] py-[0.8vw] text-[2.4vw] font-bold text-nana-cream">Retire no balcão</p>
        <div class="absolute bottom-0 left-0 h-[1.2vw] bg-nana-ink" :style="`width: ${progress}%`"></div>
    </div>
</div>
```

- [ ] **Step 5: Replace `resources/views/livewire/painel/mobile.blade.php`** with:

```blade
<div x-data="trackMine" @announce.window="onAnnounce($event.detail)" wire:poll.20s class="mx-auto flex min-h-dvh max-w-md flex-col bg-nana-cream">
    @php($readyNumbers = array_column($this->board['ready'], 'number'))
    @php($preparingNumbers = array_column($this->board['preparing'], 'number'))
    <span hidden wire:key="sync-{{ md5(json_encode([$readyNumbers, $preparingNumbers])) }}" x-init="sync(@js($readyNumbers), @js($preparingNumbers))"></span>

    <header class="relative border-b-4 border-nana-ink bg-nana-red px-4 pb-4 pt-5 text-center text-nana-cream">
        <h1 class="font-display text-3xl leading-none">{{ \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</h1>
        <p class="mt-1.5 text-sm font-bold text-[#FFE3B0]">Acompanhe seu pedido</p>
        <div class="absolute right-3 top-3">
            <x-ui.screens-menu compact align="right" current="acompanhar" />
        </div>
    </header>

    <main class="flex flex-col gap-4 p-5">
        <label class="flex items-center justify-between gap-3 rounded-xl border-3 border-nana-ink bg-white px-4 py-2">
            <input type="tel" inputmode="numeric" maxlength="3" x-model="mine" placeholder="Ex.: 42"
                   class="min-w-0 flex-1 border-0 p-0 font-display text-3xl placeholder:text-nana-line">
            <span class="shrink-0 text-xs font-extrabold uppercase tracking-wider text-nana-muted">Seu número</span>
        </label>

        <div x-show="myState === 'empty'" class="rounded-xl border-3 border-dashed border-nana-muted p-5 text-center text-lg font-bold text-nana-muted">
            Digite o número do seu pedido para acompanhar.
        </div>
        <div data-my-order="preparing" x-show="myState === 'preparing'" x-cloak class="rounded-xl border-3 border-nana-ink bg-white p-5 text-center shadow-hard">
            <p class="text-base font-extrabold text-nana-muted">Pedido</p>
            <p class="my-2 font-display text-7xl leading-none tabular-nums" x-text="myNumber"></p>
            <span class="inline-block rounded-lg border-2 border-nana-ink bg-nana-cream px-4 py-2 text-lg font-extrabold">Preparando…</span>
        </div>
        <div data-my-order="ready" x-show="myState === 'ready'" x-cloak class="rounded-xl border-3 border-nana-ink bg-nana-yellow p-5 text-center shadow-hard">
            <p class="text-base font-extrabold">Seu pedido está</p>
            <p class="my-2 font-display text-5xl leading-none">PRONTO!</p>
            <span class="inline-block rounded-lg border-2 border-nana-ink bg-nana-ink px-4 py-2 text-lg font-extrabold text-nana-cream">Retire no balcão</span>
        </div>
        <div x-show="myState === 'unknown'" x-cloak class="rounded-xl border-3 border-nana-ink bg-white p-5 text-center text-lg font-bold">
            Não encontramos o pedido <span class="font-display" x-text="myNumber"></span> na fila agora.
        </div>

        <section>
            <h2 class="font-display text-xl">Prontos</h2>
            <ul class="mt-2 space-y-1 text-lg font-bold text-nana-muted">
                @forelse ($this->board['ready'] as $entry)
                    <li wire:key="mr-{{ $entry['id'] }}" class="rounded-md px-2 py-1" :class="isMine('{{ $entry['number'] }}') ? 'bg-nana-yellow text-nana-ink' : ''">
                        <span class="mr-2 font-display text-xl text-nana-ink tabular-nums">{{ $entry['number'] }}</span>{{ $entry['first_name'] }} — pronto
                    </li>
                @empty
                    <li class="px-2 py-1">Nenhum pedido pronto agora.</li>
                @endforelse
            </ul>
        </section>

        <section>
            <h2 class="font-display text-xl">Preparando</h2>
            <ul class="mt-2 space-y-1 text-lg font-bold text-nana-muted">
                @forelse ($this->board['preparing'] as $entry)
                    <li wire:key="mp-{{ $entry['id'] }}" data-phone-preparing="{{ $entry['number'] }}" class="rounded-md px-2 py-1" :class="isMine('{{ $entry['number'] }}') ? 'bg-white text-nana-ink' : ''">
                        <span class="mr-2 font-display text-xl text-nana-ink tabular-nums">{{ $entry['number'] }}</span>{{ $entry['first_name'] }} — preparando
                    </li>
                @empty
                    <li class="px-2 py-1">Nenhum pedido em preparo.</li>
                @endforelse
            </ul>
        </section>
    </main>
</div>
```

The hidden span's `wire:key` changes whenever either list changes, so Livewire replaces it and Alpine runs `x-init="sync(...)"` again with the new lists (Alpine only runs `x-init` once per element).

- [ ] **Step 6: Update `trackMine`.** In `resources/js/announcer.js`, replace the whole `Alpine.data('trackMine', ...)` block (from `Alpine.data('trackMine', () => ({` to its closing `}));`) with:

```js
    Alpine.data('trackMine', () => ({
        mine: '',
        ready: [],
        preparing: [],
        init() {
            try { this.mine = localStorage.getItem('nana.mine') || ''; } catch (e) { this.mine = ''; }
            this.$watch('mine', (value) => {
                try { localStorage.setItem('nana.mine', value); } catch (e) { /* armazenamento indisponível */ }
            });
        },
        pad(value) {
            const number = parseInt(value, 10);
            return Number.isInteger(number) && number > 0 ? String(number).padStart(3, '0') : '';
        },
        get myNumber() {
            return this.pad(this.mine);
        },
        get myState() {
            if (!this.myNumber) {
                return 'empty';
            }
            if (this.ready.includes(this.myNumber)) {
                return 'ready';
            }
            return this.preparing.includes(this.myNumber) ? 'preparing' : 'unknown';
        },
        sync(ready, preparing) {
            this.ready = ready;
            this.preparing = preparing;
        },
        isMine(number) {
            return this.myNumber !== '' && number === this.myNumber;
        },
        onAnnounce({ number }) {
            if (!this.isMine(number)) {
                return;
            }
            if (!this.ready.includes(number)) {
                this.ready = [...this.ready, number];
            }
            if (navigator.vibrate) {
                navigator.vibrate([300, 150, 300, 150, 300]);
            }
        },
    }));
```

The `announcer` block above it does not change.

- [ ] **Step 7: Pages and the TV gate.**

Replace `resources/views/pages/painel.blade.php` with:

```blade
<x-layouts.app title="Painel" body-class="bg-nana-cream overflow-hidden">
    <x-device-gate :fullscreen="true" />
    <livewire:painel.public-board variant="tv" />
</x-layouts.app>
```

Replace `resources/views/pages/acompanhar.blade.php` with:

```blade
<x-layouts.app title="Acompanhe seu pedido" body-class="bg-nana-cream">
    <livewire:painel.public-board variant="mobile" />
</x-layouts.app>
```

Replace `resources/views/components/device-gate.blade.php` with:

```blade
@props(['fullscreen' => false])
<div x-data="deviceGate({{ $fullscreen ? 'true' : 'false' }})" x-show="open" x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center bg-nana-ink/90 p-6">
    <button type="button" @click="start()"
            class="rounded-2xl border-4 border-nana-ink bg-nana-yellow px-12 py-10 font-display text-4xl text-nana-ink shadow-[8px_8px_0_var(--color-nana-cream)]">
        Toque para começar
    </button>
</div>
```

- [ ] **Step 8: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Livewire/PublicBoardTest.php tests/Feature/Queries/QueriesTest.php tests/Feature/Ui/ComponentsTest.php`
Expected: PASS (`BoardQuery` output is unchanged, so `QueriesTest` still passes).

- [ ] **Step 9: Browser check.** Run `npm run build` (skip if `public/hot` exists), then:

```bash
node - <<'EOF'
const s = require(process.cwd() + '/tests/Browser/support.cjs');
(async () => {
    const browser = await s.launch();
    try {
        const state = await s.loginState(browser);
        const counter = await s.open(browser, '/balcao', { width: 1366, height: 768, state });
        await s.placeOrder(counter.page, 'Telão Teste');

        const kitchen = await s.open(browser, '/cozinha', { width: 1366, height: 768, state });
        const card = kitchen.page.locator('[data-order-id]', { hasText: 'Telão Teste' });
        await card.waitFor({ timeout: 10000 });
        const mine = await card.getAttribute('data-number');

        const tv = await s.open(browser, '/painel', { width: 1920, height: 1080 });
        await tv.page.click('text=Toque para começar');
        await s.expectNoHorizontalScroll(tv.page, 'TV 1920x1080');
        await s.expectInViewport(tv.page, '[data-tv-preparing]', 'TV: preparando');
        if (await tv.page.locator('[data-tv-ready]').count()) await s.expectInViewport(tv.page, '[data-tv-ready]', 'TV: prontos');
        if (await tv.page.locator('[data-tv-overflow]').count()) await s.expectInViewport(tv.page, '[data-tv-overflow]', 'TV: +N');
        await s.expectInViewport(tv.page, 'img[alt="QR code para acompanhar o pedido"]', 'TV: QR');
        const shown = await tv.page.locator('[data-tv-preparing]').count();
        if (shown > 12) s.fail(`TV mostra ${shown} preparando (máximo 12)`);

        const phone = await s.open(browser, '/acompanhar', { width: 390, height: 844, touch: true });
        await s.expectNoHorizontalScroll(phone.page, 'celular 390x844');
        await phone.page.fill('[x-model="mine"]', String(parseInt(mine, 10)));
        await phone.page.locator('[data-my-order="preparing"]').waitFor({ state: 'visible', timeout: 5000 });

        await card.locator('[data-ready-button]').click();

        await tv.page.locator('text=PEDIDO PRONTO').waitFor({ state: 'visible', timeout: 10000 });
        await tv.page.locator('text=Retire no balcão').waitFor({ state: 'visible' });
        await phone.page.locator('[data-my-order="ready"]').waitFor({ state: 'visible', timeout: 10000 });
        await s.expectInViewport(phone.page, '[data-my-order="ready"]', 'celular: cartão pronto');

        s.expectNoErrors(tv.errors, 'TV');
        s.expectNoErrors(phone.errors, 'celular');
        await Promise.all([tv.context.close(), phone.context.close(), kitchen.context.close(), counter.context.close()]);
        console.log('OK', { mine, shown });
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error.message); process.exit(1); });
EOF
```
Expected: `OK` followed by the order number used and how many preparing tiles the TV shows (12 at most; the rest is the `+N` tile). Compare manual screenshots with mockup 05 (TV normal, TV announcement, phone preparing/ready).

- [ ] **Step 10: Run the full suite**

Run: `./vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 11: Format and build**

Run: `vendor/bin/pint --dirty --format agent && npm run build`
Expected: no Pint issues left; the build succeeds.

- [ ] **Step 12: Commit and push**

```bash
git add -A
git commit -m "feat: painel da TV e acompanhamento no celular no estilo novo (até 12 preparando, +N)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push origin main
```

---
### Task 8: Visual verification sweep

**Files:**
- Create: `tests/Browser/screenshots.cjs`
- Modify: `docs/validacoes.md`
- Modify only when a check fails: the view that cuts content (see Step 4)

**Interfaces:**
- Consumes: every helper of `tests/Browser/support.cjs` (Task 1) and every data attribute listed in the File Map (Tasks 2–7).
- Produces: `tests/Browser/screenshots.cjs` — run with `node tests/Browser/screenshots.cjs`; writes PNGs to `storage/app/screenshots/` (ignored by `storage/app/.gitignore`), prints one result per screen and a Markdown table, and exits with code 1 when any check fails. Section `## 5. Redesenho das telas` in `docs/validacoes.md`.

- [ ] **Step 1: Create the sweep script** `tests/Browser/screenshots.cjs`:

```js
/*
 * Visual sweep of the redesign: screenshots + overflow/visibility checks at the approved sizes.
 * Usage (Git Bash, from the project root): node tests/Browser/screenshots.cjs
 * Needs NANA_E2E_USER / NANA_E2E_PASSWORD (local admin user); NANA_BASE_URL defaults to http://127.0.0.1:8000.
 */
const fs = require('fs');
const path = require('path');
const s = require('./support.cjs');

const OUT = path.resolve(__dirname, '../../storage/app/screenshots');
const results = [];

async function check(label, run) {
    try {
        await run();
        results.push([label, 'OK']);
        console.log(`OK      ${label}`);
    } catch (error) {
        results.push([label, `FALHOU: ${error.message}`]);
        console.log(`FALHOU  ${label}: ${error.message}`);
    }
}

async function shot(page, name) {
    await page.screenshot({ path: path.join(OUT, `${name}.png`) });
}

async function checkKitchen(page, label, scope = '') {
    const status = await s.kitchenStatus(page, scope);
    if (status.total === 0) {
        s.fail(`${label}: nenhum pedido na cozinha`);
    }
    if (status.buttonsCut > 0) {
        s.fail(`${label}: ${status.buttonsCut} botões PRONTO cortados`);
    }
    const expected = status.below > 0 ? `+${status.below} ${status.below === 1 ? 'pedido abaixo' : 'pedidos abaixo'} ↓` : '';
    if (status.moreText !== expected) {
        s.fail(`${label}: rodapé "${status.moreText}" ≠ "${expected}"`);
    }
    if (status.total >= 10 && status.maxItemsVisible <= 2 && status.visible < 8) {
        s.fail(`${label}: só ${status.visible} pedidos inteiros visíveis (esperado 8)`);
    }
    return status;
}

async function checkWideCounter(page, label) {
    await s.expectNoHorizontalScroll(page, label);
    await s.expectInViewport(page, '[data-send-button]', `${label}: botão Enviar`);
    await s.expectInViewport(page, '[data-order-total]', `${label}: total`);
    await s.expectLastProductReachable(page, label);
}

(async () => {
    fs.mkdirSync(OUT, { recursive: true });
    const browser = await s.launch();
    try {
        const state = await s.loginState(browser);

        // At least 10 orders in the kitchen, so "+N pedidos abaixo" can be measured.
        const probe = await s.open(browser, '/cozinha', { width: 1366, height: 768, state });
        const { total } = await s.kitchenStatus(probe.page);
        await probe.context.close();
        if (total < 10) {
            const counter = await s.open(browser, '/balcao', { width: 1366, height: 768, state });
            for (let i = total; i < 10; i += 1) {
                await s.placeOrder(counter.page, `Varredura ${i + 1}`);
            }
            await counter.context.close();
        }

        await check('Início 1366×768', async () => {
            const { page, context, errors } = await s.open(browser, '/?escolher=1', { width: 1366, height: 768, state });
            await s.expectNoHorizontalScroll(page, 'início');
            await s.expectInViewport(page, '[data-screen]', 'início: blocos');
            await shot(page, '01-inicio-1366x768');
            s.expectNoErrors(errors, 'início');
            await context.close();
        });

        await check('Login 1366×768', async () => {
            const { page, context, errors } = await s.open(browser, '/login', { width: 1366, height: 768 });
            await s.expectNoHorizontalScroll(page, 'login');
            await s.expectInViewport(page, 'form button[type="submit"]', 'login: botão Entrar');
            await shot(page, '02-login-1366x768');
            s.expectNoErrors(errors, 'login');
            await context.close();
        });

        await check('Balcão 1366×768', async () => {
            const { page, context, errors } = await s.open(browser, '/balcao', { width: 1366, height: 768, state });
            await checkWideCounter(page, 'balcão 1366x768');
            await s.expectInViewport(page, '[data-in-kitchen-column]', 'balcão 1366x768: Na cozinha');
            await shot(page, '03-balcao-1366x768');
            s.expectNoErrors(errors, 'balcão 1366x768');
            await context.close();
        });

        await check('Balcão 1280×800 (tablet deitado)', async () => {
            const { page, context, errors } = await s.open(browser, '/balcao', { width: 1280, height: 800, state, touch: true });
            await checkWideCounter(page, 'balcão 1280x800');
            await shot(page, '04-balcao-1280x800');
            s.expectNoErrors(errors, 'balcão 1280x800');
            await context.close();
        });

        await check('Balcão 800×1280 (tablet em pé)', async () => {
            const { page, context, errors } = await s.open(browser, '/balcao', { width: 800, height: 1280, state, touch: true });
            await s.expectNoHorizontalScroll(page, 'balcão 800x1280');
            await s.expectInViewport(page, '[data-order-bar]', 'balcão 800x1280: barra do pedido');
            await s.expectLastProductReachable(page, 'balcão 800x1280');
            await shot(page, '05-balcao-800x1280');
            await page.tap('[data-order-bar]');
            await s.expectInViewport(page, '[data-send-button]', 'balcão 800x1280: Enviar no pedido aberto');
            await s.expectInViewport(page, '[data-order-total]', 'balcão 800x1280: total no pedido aberto');
            await shot(page, '06-balcao-800x1280-pedido');
            s.expectNoErrors(errors, 'balcão 800x1280');
            await context.close();
        });

        await check('Cozinha 1366×768', async () => {
            const { page, context, errors } = await s.open(browser, '/cozinha', { width: 1366, height: 768, state });
            await s.expectNoHorizontalScroll(page, 'cozinha');
            const status = await checkKitchen(page, 'cozinha');
            console.log('        cozinha:', JSON.stringify(status));
            await shot(page, '07-cozinha-1366x768');
            s.expectNoErrors(errors, 'cozinha');
            await context.close();
        });

        await check('Estação 2×1366×768', async () => {
            const leftIs1366 = () => { try { localStorage.setItem('nana.station.left', '1366'); } catch (e) {} };
            const { page, context, errors } = await s.open(browser, '/estacao', { width: 2732, height: 768, state, init: leftIs1366 });
            const widths = await page.evaluate(() => ({
                left: Math.round(document.querySelector('[data-station-left]').getBoundingClientRect().width),
                right: Math.round(document.querySelector('[data-station-right]').getBoundingClientRect().width),
            }));
            if (widths.left !== 1366 || widths.right !== 1366) {
                s.fail(`estação: larguras ${JSON.stringify(widths)} (esperado 1366 + 1366)`);
            }
            await s.expectNoHorizontalScroll(page, 'estação');
            await s.expectInViewport(page, '[data-send-button]', 'estação: botão Enviar');
            await checkKitchen(page, 'estação (cozinha)', '[data-station-right]');
            await shot(page, '08-estacao-2732x768');
            s.expectNoErrors(errors, 'estação');
            await context.close();
        });

        await check('Painel da TV 1920×1080', async () => {
            const { page, context, errors } = await s.open(browser, '/painel', { width: 1920, height: 1080 });
            await page.click('text=Toque para começar');
            await page.waitForTimeout(300);
            await s.expectNoHorizontalScroll(page, 'TV');
            await s.expectInViewport(page, '[data-tv-preparing]', 'TV: preparando');
            if (await page.locator('[data-tv-overflow]').count()) {
                await s.expectInViewport(page, '[data-tv-overflow]', 'TV: +N');
            }
            if (await page.locator('[data-tv-ready]').count()) {
                await s.expectInViewport(page, '[data-tv-ready]', 'TV: prontos');
            }
            await s.expectInViewport(page, 'img[alt="QR code para acompanhar o pedido"]', 'TV: QR');
            await shot(page, '09-tv-1920x1080');
            s.expectNoErrors(errors, 'TV');
            await context.close();
        });

        await check('Celular 390×844', async () => {
            const { page, context, errors } = await s.open(browser, '/acompanhar', { width: 390, height: 844, touch: true });
            await s.expectNoHorizontalScroll(page, 'celular');
            await s.expectInViewport(page, 'input[x-model="mine"]', 'celular: campo Seu número');
            const preparingNumbers = page.locator('[data-phone-preparing]');
            if (await preparingNumbers.count()) {
                const first = await preparingNumbers.first().getAttribute('data-phone-preparing');
                await page.fill('input[x-model="mine"]', String(parseInt(first, 10)));
                await page.locator('[data-my-order="preparing"]').waitFor({ state: 'visible', timeout: 3000 });
                await s.expectInViewport(page, '[data-my-order="preparing"]', 'celular: cartão do meu pedido');
            }
            await shot(page, '10-celular-390x844');
            s.expectNoErrors(errors, 'celular');
            await context.close();
        });
    } finally {
        await browser.close();
    }

    console.log('\n| Tela e tamanho | Resultado |\n|---|---|');
    for (const [label, result] of results) {
        console.log(`| ${label} | ${result} |`);
    }
    process.exit(results.some(([, result]) => result !== 'OK') ? 1 : 0);
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
```

- [ ] **Step 2: Build and run the sweep.**

```bash
npm run build
node tests/Browser/screenshots.cjs
```
Expected: ten `OK` lines (one per screen), the Markdown table at the end, exit code 0, and ten PNGs in `storage/app/screenshots/`.

- [ ] **Step 3: Compare with the approved mockups.** Open each PNG next to its mockup and note any visual difference that is not a cut-off (colors, borders, fonts, order of elements):
  - `01-inicio-1366x768.png` ↔ `02-tela-de-inicio.html` (main frame);
  - `02-login-1366x768.png` ↔ `05-tv-celular-login.html` (Login);
  - `03-balcao-1366x768.png`, `04-balcao-1280x800.png` ↔ `03-balcao.html` option B;
  - `05-balcao-800x1280.png` ↔ `03-balcao.html` option A "no tablet em pé";
  - `07-cozinha-1366x768.png` and the right half of `08-estacao-2732x768.png` ↔ `04-cozinha.html` state 2;
  - `09-tv-1920x1080.png` ↔ `05-tv-celular-login.html` "TV — normal";
  - `10-celular-390x844.png` ↔ `05-tv-celular-login.html` "Celular — preparando".

- [ ] **Step 4: Fix every failure, one commit per fix.** For each `FALHOU` line (or a cut-off seen in Step 3):
  1. Find the element in the PNG and the view that renders it (the label names the screen and the selector).
  2. Make the smallest class change that removes the cut-off in that view only, keeping the bindings listed in Global Constraints (typical fixes: add `min-h-0` to a flex child that should scroll, add `min-w-0`/`truncate` to a long name, lower a fixed `text-[..vw]` on the TV, reduce a `gap`/`p-*`).
  3. If the cause can be expressed in markup (a class or attribute), add an assertion to the Pest test of that component, run it red, then green.
  4. Run `./vendor/bin/pest`, `npm run build` and `node tests/Browser/screenshots.cjs` again.
  5. Commit:
     ```bash
     git add -A
     git commit -m "fix: corte na tela <tela> em <largura>x<altura> no redesenho" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
     git push origin main
     ```
     replacing `<tela>`, `<largura>` and `<altura>` with the screen and size of the failing line (for example `fix: corte na tela cozinha em 1366x768 no redesenho`).
  6. Write one line for the "Correções" list of Step 5: `- <tela> <largura>×<altura>: <o que estava cortado> → <o que mudou> (<hash curto do commit>)`.

  Repeat until the sweep prints only `OK`.

- [ ] **Step 5: Record the results.** Append the section to `docs/validacoes.md` (after section "## 4. DNS"). Run from the project root, pasting the table printed by the last sweep run (all rows `OK`) where indicated:

```bash
{
  echo ""
  echo "## 5. Redesenho das telas (Task 8 do plano do redesenho, data: $(date +%F))"
  echo ""
  echo "Varredura com \`node tests/Browser/screenshots.cjs\` (Chromium automatizado, servidor e Reverb locais). Em cada tamanho: sem rolagem horizontal (\`scrollWidth <= innerWidth\`) e elementos-chave inteiros na tela (botão Enviar e total do balcão, último prato alcançável, botões PRONTO dos cartões visíveis e rodapé \"+N pedidos abaixo\" da cozinha, blocos da tela de início, números e QR da TV, campo \"Seu número\" do celular). Capturas em \`storage/app/screenshots/\` (não versionadas)."
  echo ""
} >> docs/validacoes.md
```

Then open `docs/validacoes.md`, and under that text paste the Markdown table printed by the sweep (header `| Tela e tamanho | Resultado |` plus the ten rows), followed by:

```markdown

Correções feitas durante a varredura:
- nenhuma
```

replacing `- nenhuma` with the lines written in Step 4.6 when there were fixes. Finish the section with the store checks that only the owner can do (spec §12):

```markdown

Pendente na loja (dono): leitura da cozinha a ~2 m, toque no tablet (se houver) e "Detectar monitores" com os dois monitores reais.
```

- [ ] **Step 6: Run the full suite and the build one last time**

Run: `./vendor/bin/pest && npm run build`
Expected: all PASS; the build succeeds.

- [ ] **Step 7: Commit and push**

```bash
git add tests/Browser/screenshots.cjs docs/validacoes.md
git commit -m "test: varredura visual do redesenho em todos os tamanhos e registro em validações" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push origin main
```

---

## Self-review

Spec coverage (docs/superpowers/specs/2026-11-16-redesenho-telas-design.md):

| Spec section | Covered by |
|---|---|
| §1 Por que (1366×768 cut-offs, kitchen fits 3–4 orders, "Marcar esgotado" breaking the line) | Tasks 3, 5, 6 (layout), Task 8 (sweep proves no cut-off) |
| §2 D1 monitor 18.5"/tablets | Task 3 (container queries at 1280/1024), Task 8 (1366×768, 1280×800, 800×1280) |
| §2 D2 visual-only NOVO, no yellow screen | Task 5 (`kitchen-highlight`, `nana-highlight`, no beep, no flash) |
| §2 D3 "Lanchonete marcante" | Task 1 (tokens, fonts, components), applied in Tasks 2–7 |
| §2 D4 home screen + "Telas" | Task 1 (`Launchpad`, `x-ui.screens-menu`), Task 2 (home, `nana.home`) |
| §2 D5 three-column counter | Tasks 3 and 4 |
| §2 D6 TV/phone/login, TV keeps the announcement | Task 2 (login), Task 7 (TV, phone, gate kept on TV) |
| §3 tokens, fonts bundled, surfaces, components, touch 44/48 px, yellow focus, reduced motion | Task 1 (+ `max-lg:min-h-12` on shared controls; `nana-highlight` reduced-motion rule, checked in Task 5's browser check) |
| §4 home: tiles per profile, guest + "Entrar", intended, `nana.home`, `/?escolher=1`, forget, "Telas" discreet on TV | Task 1 (registry, menu), Task 2 (home, launcher, login intended test), Task 7 (compact menu on TV and phone) |
| §5 counter: widths, tabs, chips/search, "⋯" menu, tiles (in-order, sold out, daily dish), order column (number, red name, "Comer aqui / Para viagem", per-line chips, total + send always visible, Enter), "Na cozinha", preserved behaviors | Task 3 (products/order/pane), Task 4 ("Na cozinha", "Pedidos de hoje", cancel, waiting time) |
| §6 kitchen: auto grid ≥300 px oldest first, card content, VIAGEM, notes in red, PRONTO, warn 15/late 25 from config, NOVO/ALTERADO 60 s or tap, no sound, "+N pedidos abaixo", numpad hint, focus rule, no "Toque para começar", wake lock on open and on visibility | Task 1 (config), Task 5 |
| §7 Estação: one window, `getScreenDetails()` via "Detectar monitores", `localStorage`, setting default 1366, single-monitor mode + note | Task 1 (default 1366), Task 6 |
| §8 TV: "Preparando" with counter, up to 12 + "+N", red "Pronto!", QR, clock, announcement ~8 s with queue and chime, gate kept | Task 7 |
| §9 phone: red header, "Seu número" remembered, my order "Preparando…"/"PRONTO! Retire no balcão" + vibration, short lists | Task 7 |
| §10 login | Task 2 |
| §11 out of scope (Filament visuals, business rules, TV sound) | untouched: only `SettingsPage` property defaults change (Task 1) |
| §12 Pest (home per profile, routes, `/?escolher=1`, intended, default 1366, limits from config, VIAGEM, markup updates) | Tasks 1, 2, 3, 4, 5, 6, 7 |
| §12 Playwright (screenshots at all sizes, NOVO on arrival and on tap, open-always + `/?escolher=1`, "+N" with 10 orders, counter focus kept, no cut-off) | Task 2 (open-always), Task 5 (NOVO, "+N", no sound), Task 6 (focus, widths), Task 8 (all sizes) |
| §13 delivery (deploy only after review; operations guide later) | Global Constraints (implementers never deploy) |
