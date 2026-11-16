# Nana Comanda — Fase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Nana Lanches order system (phase 1): the counter places orders, the kitchen sees them instantly and confirms "Pronto", the dining-room TV and the customer's phone show progress and call the customer by number + first name.

**Architecture:** Laravel 13 monolith. Domain logic lives in small action classes (`App\Actions\Orders\*`) and read queries (`App\Queries\*`). Livewire 4 class components render the operational screens; Filament 5 renders the admin. Laravel Reverb pushes "something changed" events (`ShouldBroadcastNow`, no queue worker). Screens re-query the server on every event, plus a 20 s poll as a safety net. Production runs on the user's shared Ubuntu VPS behind nginx, with Reverb as a systemd service.

**Tech Stack:** PHP 8.3, Laravel ^13.0, Livewire ^4.0 (bundled Alpine), Filament ^5.0, Laravel Reverb ^1.13 + Laravel Echo, Tailwind CSS 4 + @tailwindcss/forms, Pest ^4.0 (+ pest-plugin-laravel ^4, pest-plugin-livewire ^4), Laravel Boost ^2 (dev), chillerlan/php-qrcode ^5.0, MySQL 8 (SQLite in-memory for tests).

**Spec:** `docs/superpowers/specs/2026-11-13-comanda-fase1-design.md`. Read it before starting any task.

## Global Constraints

- Project root: `c:\laragon\www\nana-comanda`. Never read from or write to the git of `c:\laragon\www\Comanda_refatorada` or `c:\laragon\www\Comanda` (the old TCC).
- Git remote: `https://github.com/marcosserg12/nova_comanda.git`, branch `main`.
- Every commit message ends with the trailer line `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Local shell: Git Bash on Windows (Laragon: PHP 8.3.29, MySQL 8.4, Node, Composer). Local URL: `http://nana-comanda.test`.
- Versions: PHP 8.3; `laravel/framework ^13.0`; `livewire/livewire ^4.0`; `filament/filament ^5.0`; `laravel/reverb ^1.13`; `pestphp/pest ^4.0` (Pest 5 requires PHP 8.4; production runs PHP 8.3).
- App timezone `America/Sao_Paulo`, locale `pt_BR`. The business day is always `App\Support\BusinessDay::today()`.
- All user-facing copy is Brazilian Portuguese. Code identifiers are English.
- Money is integer cents (`*_cents`). Display it with `App\Support\Money::format()`, which gives `R$ 24,00`.
- No Redis. Production session, cache and queue drivers are `database`. Broadcast events implement `ShouldBroadcastNow`.
- Public screens (`/painel`, `/acompanhar`, public channel `painel`) expose only the order id, number, first name and status.
- Every Livewire action that changes data calls `Gate::authorize()` first. State changes go through `App\Actions\Orders\*`.
- Shared production VPS (`root@145.223.26.225`):
  - use a dedicated DB user and your own nginx site file;
  - run `nginx -t` before every reload;
  - never edit other sites, ufw, the global MySQL config or the n8n Redis;
  - never reload the shared php8.3-fpm pool.
- When a Livewire 4 or Filament 5 API in this plan does not match the installed version, look it up with Laravel Boost's `search-docs` MCP tool. Keep this plan's behavior, class names and method names.

---

## File Map

```
app/
  Actions/Orders/      OrderInput, BuildOrderItems, NextOrderNumber, PlaceOrder, UpdateOrderItems, TransitionOrder
  Console/Commands/    BackupDatabase
  Enums/               Role, Destination, OrderStatus, OrderSource
  Events/              OrdersChanged (private 'pedidos'), BoardChanged (public 'painel')
  Filament/Pages/      SalesSummaryPage, SettingsPage
  Filament/Resources/  Categories/, Products/, QuickNotes/, Users/, Orders/
  Http/Controllers/    HomeController, StationController, Auth/LoginController
  Http/Middleware/     EnsureRole
  Livewire/Balcao/     OrderForm, InKitchenList, TodayOrders
  Livewire/Cozinha/    Board
  Livewire/Painel/     PublicBoard
  Models/              User, Category, Product, QuickNote, Order, OrderItem, DailyCounter, Setting
  Queries/             BoardQuery, KitchenQueue, TodayOrdersQuery, SalesSummary
  Support/             BusinessDay, Money, QrCode, OrderBroadcaster
config/comanda.php
database/migrations/   users (modified), categories, products, quick_notes, orders, order_items, daily_counters, settings
database/seeders/      DatabaseSeeder, MenuSeeder, UserSeeder
deploy/                deploy.sh, nginx/comanda.mswebsolutions.site.conf, systemd/comanda-reverb.service, cron/comanda
docs/operacao.md, docs/validacoes.md
resources/css/app.css
resources/js/          app.js, device.js, toasts.js, connection.js, network.js, order-draft.js, kitchen.js, announcer.js
resources/views/
  auth/login.blade.php
  components/          layouts/app, toasts, device-gate, connection-dot, balcao-pane
  filament/pages/      sales-summary, settings
  livewire/balcao/     order-form, in-kitchen-list, today-orders
  livewire/cozinha/    board
  livewire/painel/     tv, mobile
  pages/               estacao, balcao, cozinha, painel, acompanhar
routes/                web.php, channels.php, console.php
tests/Pest.php, tests/Feature/**, tests/Unit/**
```

---

### Task 1: Scaffold the project and tooling

**Files:**
- Create: the whole Laravel skeleton inside `c:\laragon\www\nana-comanda`, plus `tests/Feature/HealthTest.php`
- Modify: `config/app.php`, `.env`, `.env.example`, `phpunit.xml`, `tests/Pest.php`, `resources/css/app.css`

**Interfaces:**
- Produces: a working Laravel 13 app with Livewire 4, Pest 4, Boost and Tailwind forms. `tests/Pest.php` applies `RefreshDatabase` to `tests/Feature`.

- [ ] **Step 1: Create the skeleton next to the existing docs.** `create-project` refuses non-empty folders, so create it in a temporary folder and copy it in.

```bash
cd /c/laragon/www
composer create-project laravel/laravel nana-comanda-tmp "^13.0" --no-interaction
cp -a nana-comanda-tmp/. nana-comanda/
rm -rf nana-comanda-tmp
cd nana-comanda
ls docs/superpowers/specs docs/superpowers/plans
```
Expected: both folders are listed, which confirms the spec and this plan survived the copy.

- [ ] **Step 2: Install the packages**

```bash
composer require livewire/livewire:"^4.0" chillerlan/php-qrcode:"^5.0"
composer remove phpunit/phpunit --dev
composer require --dev pestphp/pest:"^4.0" pestphp/pest-plugin-laravel:"^4.0" pestphp/pest-plugin-livewire:"^4.0" laravel/boost:"^2.0" --with-all-dependencies
./vendor/bin/pest --init
rm -f tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php
npm install
npm install -D @tailwindcss/forms
```

- [ ] **Step 3: Configure timezone and locale.** In `config/app.php`, set these four keys and leave the rest of the file unchanged:

```php
'timezone' => 'America/Sao_Paulo',

'locale' => env('APP_LOCALE', 'pt_BR'),

'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

'faker_locale' => env('APP_FAKER_LOCALE', 'pt_BR'),
```

- [ ] **Step 4: Point the local `.env` at MySQL.** Set or replace these keys in `.env`; uncomment the `DB_*` lines if the skeleton commented them out. Apply the same keys to `.env.example`, with an empty `DB_PASSWORD`.

```dotenv
APP_NAME="Nana Comanda"
APP_URL=http://nana-comanda.test
APP_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nana_comanda
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Then create the database and migrate (Laragon must be running):

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS nana_comanda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
  || /c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS nana_comanda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f database/database.sqlite
php artisan migrate
```

- [ ] **Step 5: Make tests use SQLite in memory.** Inside `<php>` in `phpunit.xml`, make sure these `env` entries exist and are not commented out. Add any that are missing.

```xml
<env name="APP_ENV" value="testing"/>
<env name="BCRYPT_ROUNDS" value="4"/>
<env name="BROADCAST_CONNECTION" value="null"/>
<env name="CACHE_STORE" value="array"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="MAIL_MAILER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
```

Replace `tests/Pest.php` with:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
```

- [ ] **Step 6: Theme the CSS.** Replace `resources/css/app.css` with:

```css
@import 'tailwindcss';
@plugin '@tailwindcss/forms';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    --color-nana-red: #c8102e;
    --color-nana-yellow: #ffc72c;
    --color-nana-ink: #1c1917;
}

[x-cloak] {
    display: none !important;
}
```

- [ ] **Step 7: Write the smoke test.** Create `tests/Feature/HealthTest.php`:

```php
<?php

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});
```

- [ ] **Step 8: Run the tests and the asset build**

Run: `./vendor/bin/pest && npm run build`
Expected: `1 passed`, and Vite prints `built in …`.

- [ ] **Step 9: Install Laravel Boost (interactive).** Run `php artisan boost:install`. Select **Claude Code** as the agent and enable the guidelines, the skills and the MCP server. Keep the files it generates.

- [ ] **Step 10: Initialize git and push**

```bash
git init -b main
git remote add origin https://github.com/marcosserg12/nova_comanda.git
git add -A
git commit -m "chore: scaffold Laravel 13 com Livewire 4, Pest 4 e Boost" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push -u origin main
```

---

### Task 2: Spike — counter and kitchen sharing one PC (throwaway)

Validates spec section 11, item 1, before any UI is built. The spike file is deleted at the end of the task; only the written results are kept.

**Files:**
- Create (temporary): `public/spike-estacao.html`
- Create: `docs/validacoes.md`

**Interfaces:**
- Produces: `docs/validacoes.md` with OK/FAIL for each check. Tasks 13 and 14 rely on its conclusions.

- [ ] **Step 1: Create the spike page** `public/spike-estacao.html`:

```html
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Spike estação</title>
<style>
  body { margin: 0; font-family: sans-serif; display: grid; grid-template-columns: var(--left, 50vw) var(--right, 50vw); height: 100vh; }
  section { padding: 24px; overflow: auto; }
  #kitchen { background: #1c1917; color: #fff; }
  input, textarea { font-size: 24px; width: 100%; margin-bottom: 12px; }
  button.k { font-size: 32px; padding: 24px; margin: 8px; width: 45%; }
  #log { font: 14px monospace; white-space: pre-wrap; background: #eee; color: #000; padding: 8px; height: 240px; overflow: auto; }
</style>
</head>
<body>
<section id="counter">
  <h1>Balcão</h1>
  <form id="f"><input id="name" placeholder="Digite um nome aqui" autofocus><textarea id="obs" placeholder="Observação"></textarea><button>Enviar (Enter)</button></form>
  <div id="log"></div>
</section>
<section id="kitchen">
  <h1>Cozinha</h1>
  <button class="k">PRONTO 001</button><button class="k">PRONTO 002</button>
  <button class="k">PRONTO 003</button><button class="k">PRONTO 004</button>
  <p>Teclado numérico: dígitos + Enter do numérico, "+" muda destaque, "−" desfaz, "," apaga.</p>
  <p id="typed" style="font-size:40px"></p>
</section>
<script>
  const params = new URLSearchParams(location.search);
  if (params.get('left')) document.body.style.setProperty('--left', params.get('left') + 'px');
  if (params.get('right')) document.body.style.setProperty('--right', params.get('right') + 'px');
  const log = (m) => { const el = document.getElementById('log'); el.textContent = new Date().toLocaleTimeString() + ' ' + m + '\n' + el.textContent; };
  document.getElementById('f').addEventListener('submit', (e) => { e.preventDefault(); log('BALCÃO enviou: ' + document.getElementById('name').value); });
  document.querySelectorAll('button.k').forEach((b) => {
    b.tabIndex = -1;
    b.addEventListener('pointerdown', (e) => e.preventDefault());
    b.addEventListener('mousedown', (e) => e.preventDefault());
    b.addEventListener('click', () => log('COZINHA tocou ' + b.textContent + ' | foco atual: ' + (document.activeElement.id || document.activeElement.tagName)));
  });
  let typed = '';
  window.addEventListener('keydown', (e) => {
    if (!e.code || !e.code.startsWith('Numpad')) return;
    e.preventDefault(); e.stopPropagation();
    const d = e.code.match(/^Numpad(\d)$/);
    if (d) typed = (typed + d[1]).slice(-3);
    else if (e.code === 'NumpadEnter') { log('COZINHA teclado: pronto ' + (typed || '(destacado)')); typed = ''; }
    else if (e.code === 'NumpadAdd') log('COZINHA teclado: próximo cartão');
    else if (e.code === 'NumpadSubtract') log('COZINHA teclado: desfazer');
    else if (e.code === 'NumpadDecimal') typed = '';
    document.getElementById('typed').textContent = typed ? 'Nº ' + typed : '';
  }, { capture: true });
</script>
</body>
</html>
```

- [ ] **Step 2: Open the spike.** Reload Laragon (Menu → Reload) so that the `nana-comanda.test` virtual host exists.
  - **With two monitors:** open Chrome spanning both. Use a dedicated profile, otherwise Chrome ignores the size flags. Adjust the widths to the real monitors.
    ```
    "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="C:\nana-estacao" --app="http://nana-comanda.test/spike-estacao.html?left=1920&right=1920" --window-position=0,0 --window-size=3840,1080
    ```
  - **With one monitor:** open `http://nana-comanda.test/spike-estacao.html` normally.

- [ ] **Step 3: Run the checklist with the user** (they operate the hardware) and write the results into `docs/validacoes.md`:

```markdown
# Validações de hardware e infraestrutura

## 1. Estação em dois monitores (Task 2, data: AAAA-MM-DD)

| # | Verificação | Resultado |
|---|---|---|
| 1 | Digitando no campo "nome", tocar/clicar nos botões da cozinha não tira o foco (log mostra `foco atual: name`) e a digitação continua no campo | OK / FALHOU |
| 2 | Dígitos do teclado numérico NÃO aparecem no campo "nome"; aparecem como "Nº" na cozinha | OK / FALHOU |
| 3 | Enter do teclado numérico não envia o formulário do balcão; o Enter principal envia | OK / FALHOU |
| 4 | Toque no monitor touch cai na metade da cozinha (se não: Painel de Controle → Configurações do Tablet PC → Configurar → Entrada por toque) | OK / FALHOU / sem touch disponível |
| 5 | Janela única cobre os dois monitores sem "grudar" em um só | OK / FALHOU / sem 2º monitor |

Conclusão: …
```

If check 1 or 2 fails, **stop** and report to the user. The fallback is plan B from the spec: `/cozinha` on its own device. Checks 4 and 5 may stay "sem … disponível" when the hardware is not at hand; they are repeated in Task 20.

- [ ] **Step 4: Delete the spike and commit the results**

```bash
rm public/spike-estacao.html
git add -A
git commit -m "docs: resultado do spike da estação em dois monitores" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Domain enums, schema, models and factories

**Files:**
- Create: `app/Enums/{Role,Destination,OrderStatus,OrderSource}.php`, `app/Support/{BusinessDay,Money}.php`
- Create: `app/Models/{Category,Product,QuickNote,Order,OrderItem,DailyCounter,Setting}.php`
- Create: migrations `database/migrations/2026_11_16_000001_create_categories_table.php` … `000007_create_settings_table.php`
- Create: `database/factories/{Category,Product,Order,OrderItem}Factory.php`
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`, `tests/Pest.php`
- Test: `tests/Unit/MoneyTest.php`, `tests/Unit/BusinessDayTest.php`, `tests/Feature/Models/{ProductTest,OrderTest,SettingTest,UserRoleTest}.php`

**Interfaces:**
- Produces:
  - `BusinessDay::today(): CarbonImmutable`, `BusinessDay::todayString(): string`
  - `Money::format(int $cents): string`, `Money::toDecimalString(int $cents): string`, `Money::parseToCents(string|int|float|null $value): int`
  - Enums with `label(): string` and `static options(): array<string,string>`. Values:
    - `Role`: `admin|balcao|cozinha`
    - `Destination`: `cozinha|cabine`
    - `OrderStatus`: `preparing|ready|delivered|canceled`
    - `OrderSource`: `balcao|totem`
  - `User::hasRole(Role ...$roles): bool`
  - `Product`: `isSoldOut()`, `toggleSoldOut()`, `dailyNameToday(): ?string`, `needsDailyName()`, `displayName()`, `setDailyName(string)`, virtual attributes `price` (`"24,00"`) and `sold_out_today` (bool)
  - `Order`: `items()`, `creator()`, `scopeToday()`, `displayNumber(): string` (`"007"`), `firstName(): string`
  - `Setting::get(string $key, mixed $default = null)`, `Setting::put(string $key, mixed $value)`, constants `READY_MINUTES`, `RESTAURANT_NAME`, `STATION_LEFT_WIDTH`, `STATION_RIGHT_WIDTH`
  - Pest helpers `userWithRole(Role)`, `kitchenProduct(array)`, `cabineProduct(array)`

- [ ] **Step 1: Write the failing unit tests**

`tests/Unit/MoneyTest.php`:

```php
<?php

use App\Support\Money;

it('formats cents as Brazilian reais', function () {
    expect(Money::format(2400))->toBe('R$ 24,00')
        ->and(Money::format(123456))->toBe('R$ 1.234,56')
        ->and(Money::toDecimalString(2550))->toBe('25,50');
});

it('parses prices typed by people into cents', function (string|int|float $typed, int $cents) {
    expect(Money::parseToCents($typed))->toBe($cents);
})->with([
    ['24', 2400], ['24,5', 2450], ['24,50', 2450], ['24.50', 2450], ['1.234,56', 123456], ['R$ 17,00', 1700], [24, 2400], [24.5, 2450],
]);

it('rejects invalid prices', function () {
    Money::parseToCents('abc');
})->throws(InvalidArgumentException::class);
```

`tests/Unit/BusinessDayTest.php`:

```php
<?php

use App\Support\BusinessDay;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('uses the São Paulo calendar day', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-14 02:30:00', 'UTC'));

    expect(BusinessDay::todayString())->toBe('2026-11-13')
        ->and(BusinessDay::today()->format('H:i'))->toBe('00:00');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Unit`
Expected: FAIL, `Class "App\Support\Money" not found`.

- [ ] **Step 3: Implement the support classes**

`app/Support/Money.php`:

```php
<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function format(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    public static function toDecimalString(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    public static function parseToCents(string|int|float|null $value): int
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException('Preço vazio.');
        }

        if (is_int($value)) {
            return $value * 100;
        }

        $normalized = str_replace(['R$', ' '], '', (string) $value);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        }

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("Preço inválido: {$value}");
        }

        return (int) round(((float) $normalized) * 100);
    }
}
```

`app/Support/BusinessDay.php`:

```php
<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class BusinessDay
{
    public const TIMEZONE = 'America/Sao_Paulo';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    public static function todayString(): string
    {
        return self::today()->toDateString();
    }
}
```

Run: `./vendor/bin/pest tests/Unit`
Expected: PASS.

- [ ] **Step 4: Create the enums**

`app/Enums/Role.php`:

```php
<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Balcao = 'balcao';
    case Cozinha = 'cozinha';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Balcao => 'Balcão',
            self::Cozinha => 'Cozinha',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
```

`app/Enums/Destination.php`:

```php
<?php

namespace App\Enums;

enum Destination: string
{
    case Cozinha = 'cozinha';
    case Cabine = 'cabine';

    public function label(): string
    {
        return match ($this) {
            self::Cozinha => 'Cozinha',
            self::Cabine => 'Cabine',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
```

`app/Enums/OrderStatus.php`:

```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Preparando',
            self::Ready => 'Pronto',
            self::Delivered => 'Entregue',
            self::Canceled => 'Cancelado',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
```

`app/Enums/OrderSource.php`:

```php
<?php

namespace App\Enums;

enum OrderSource: string
{
    case Balcao = 'balcao';
    case Totem = 'totem';

    public function label(): string
    {
        return match ($this) {
            self::Balcao => 'Balcão',
            self::Totem => 'Totem',
        };
    }
}
```

- [ ] **Step 5: Write the failing model tests**

`tests/Feature/Models/ProductTest.php`:

```php
<?php

use App\Models\Product;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo')));

it('is sold out only on the day it was marked', function () {
    $product = Product::factory()->create();

    $product->toggleSoldOut();
    expect($product->fresh()->isSoldOut())->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2026-11-14 08:00', 'America/Sao_Paulo'));
    expect($product->fresh()->isSoldOut())->toBeFalse();
});

it('toggles sold out back off', function () {
    $product = Product::factory()->create();
    $product->toggleSoldOut();
    $product->fresh()->toggleSoldOut();

    expect($product->fresh()->isSoldOut())->toBeFalse();
});

it('needs a daily name until one is set for today', function () {
    $product = Product::factory()->create(['name' => 'Prato do dia', 'has_daily_name' => true]);

    expect($product->needsDailyName())->toBeTrue()
        ->and($product->displayName())->toBe('Prato do dia');

    $product->setDailyName('  Strogonoff ');

    expect($product->fresh()->needsDailyName())->toBeFalse()
        ->and($product->fresh()->displayName())->toBe('Prato do dia: Strogonoff');

    $this->travelTo(CarbonImmutable::parse('2026-11-14 08:00', 'America/Sao_Paulo'));
    expect($product->fresh()->needsDailyName())->toBeTrue();
});

it('reads and writes the price in reais through the price attribute', function () {
    $product = Product::factory()->create(['price_cents' => 2400]);

    expect($product->price)->toBe('24,00');

    $product->update(['price' => '25,50']);
    expect($product->fresh()->price_cents)->toBe(2550);
});

it('exposes sold out today as a writable boolean attribute', function () {
    $product = Product::factory()->create();

    $product->update(['sold_out_today' => true]);
    expect($product->fresh()->sold_out_today)->toBeTrue();

    $product->update(['sold_out_today' => false]);
    expect($product->fresh()->sold_out_today)->toBeFalse();
});
```

`tests/Feature/Models/OrderTest.php`:

```php
<?php

use App\Models\Order;
use Carbon\CarbonImmutable;

it('pads the order number to three digits', function () {
    expect(Order::factory()->make(['number' => 7])->displayNumber())->toBe('007')
        ->and(Order::factory()->make(['number' => 123])->displayNumber())->toBe('123');
});

it('extracts a capitalized first name', function (string $name, string $first) {
    expect(Order::factory()->make(['customer_name' => $name])->firstName())->toBe($first);
})->with([
    ['maria  souza', 'Maria'],
    ['JOÃO', 'João'],
    ['  ana paula ', 'Ana'],
]);

it('scopes orders to the current business day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    $today = Order::factory()->create(['business_date' => '2026-11-13', 'number' => 1]);
    Order::factory()->create(['business_date' => '2026-11-12', 'number' => 1]);

    expect(Order::query()->today()->pluck('id')->all())->toBe([$today->id]);
});
```

`tests/Feature/Models/SettingTest.php`:

```php
<?php

use App\Models\Setting;

it('returns the built-in default when a setting was never saved', function () {
    expect(Setting::get(Setting::READY_MINUTES))->toBe(5)
        ->and(Setting::get(Setting::RESTAURANT_NAME))->toBe('Nana Lanches')
        ->and(Setting::get('unknown', 'fallback'))->toBe('fallback');
});

it('stores values and refreshes the cache on put', function () {
    expect(Setting::get(Setting::READY_MINUTES))->toBe(5);

    Setting::put(Setting::READY_MINUTES, 8);

    expect(Setting::get(Setting::READY_MINUTES))->toBe(8);
});
```

`tests/Feature/Models/UserRoleTest.php`:

```php
<?php

use App\Enums\Role;

it('checks roles', function () {
    $user = userWithRole(Role::Balcao);

    expect($user->hasRole(Role::Balcao))->toBeTrue()
        ->and($user->hasRole(Role::Admin, Role::Balcao))->toBeTrue()
        ->and($user->hasRole(Role::Cozinha))->toBeFalse();
});
```

Append the helpers to `tests/Pest.php`, so the full file reads:

```php
<?php

use App\Enums\Destination;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function userWithRole(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

function kitchenProduct(array $attributes = []): Product
{
    return Product::factory()
        ->for(Category::factory()->state(['destination' => Destination::Cozinha]))
        ->create($attributes);
}

function cabineProduct(array $attributes = []): Product
{
    return Product::factory()
        ->for(Category::factory()->state(['destination' => Destination::Cabine]))
        ->create($attributes);
}
```

- [ ] **Step 6: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Models`
Expected: FAIL, `Class "App\Models\Product" not found`.

- [ ] **Step 7: Write the migrations**

In `database/migrations/0001_01_01_000000_create_users_table.php`, replace **only** the `Schema::create('users', …)` block with:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('username', 40)->unique();
    $table->string('email')->nullable()->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('role', 10)->default('balcao');
    $table->rememberToken();
    $table->timestamps();
});
```

`database/migrations/2026_11_16_000001_create_categories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('destination', 10);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

`database/migrations/2026_11_16_000002_create_products_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('price_cents');
            $table->boolean('has_daily_name')->default(false);
            $table->string('daily_name', 60)->nullable();
            $table->date('daily_name_date')->nullable();
            $table->date('sold_out_date')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

`database/migrations/2026_11_16_000003_create_quick_notes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_notes');
    }
};
```

`database/migrations/2026_11_16_000004_create_orders_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('business_date');
            $table->unsignedSmallInteger('number');
            $table->string('customer_name', 60);
            $table->boolean('takeaway')->default(false);
            $table->string('status', 12)->index();
            $table->string('source', 10)->default('balcao');
            $table->unsignedInteger('total_cents')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->string('cancel_reason', 120)->nullable();
            $table->timestamps();

            $table->unique(['business_date', 'number']);
            $table->index(['business_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

`database/migrations/2026_11_16_000005_create_order_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot', 120);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('notes')->nullable();
            $table->string('destination', 10);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
```

`database/migrations/2026_11_16_000006_create_daily_counters_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_counters', function (Blueprint $table) {
            $table->date('business_date')->primary();
            $table->unsignedSmallInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_counters');
    }
};
```

`database/migrations/2026_11_16_000007_create_settings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

- [ ] **Step 8: Write the models**

`app/Models/User.php` (replace the file):

```php
<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
```

`app/Models/Category.php`:

```php
<?php

namespace App\Models;

use App\Enums\Destination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'destination', 'position', 'active'];

    protected function casts(): array
    {
        return ['destination' => Destination::class, 'active' => 'boolean', 'position' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('position');
    }

    public function quickNotes(): HasMany
    {
        return $this->hasMany(QuickNote::class)->orderBy('position');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
```

`app/Models/Product.php`:

```php
<?php

namespace App\Models;

use App\Support\BusinessDay;
use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'price_cents', 'price', 'has_daily_name', 'daily_name', 'daily_name_date',
        'sold_out_date', 'sold_out_today', 'image_path', 'active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'has_daily_name' => 'boolean',
            'daily_name_date' => 'date',
            'sold_out_date' => 'date',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isSoldOut(): bool
    {
        return $this->sold_out_date !== null && $this->sold_out_date->isSameDay(BusinessDay::today());
    }

    public function toggleSoldOut(): void
    {
        $this->forceFill(['sold_out_date' => $this->isSoldOut() ? null : BusinessDay::todayString()])->save();
    }

    public function dailyNameToday(): ?string
    {
        if (! $this->has_daily_name || blank($this->daily_name) || $this->daily_name_date === null) {
            return null;
        }

        return $this->daily_name_date->isSameDay(BusinessDay::today()) ? $this->daily_name : null;
    }

    public function needsDailyName(): bool
    {
        return $this->has_daily_name && $this->dailyNameToday() === null;
    }

    public function displayName(): string
    {
        $daily = $this->dailyNameToday();

        return $daily === null ? $this->name : "{$this->name}: {$daily}";
    }

    public function setDailyName(string $name): void
    {
        $this->forceFill(['daily_name' => trim($name), 'daily_name_date' => BusinessDay::todayString()])->save();
    }

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => isset($attributes['price_cents'])
                ? Money::toDecimalString((int) $attributes['price_cents'])
                : null,
            set: fn (mixed $value) => ['price_cents' => Money::parseToCents($value)],
        );
    }

    protected function soldOutToday(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isSoldOut(),
            set: fn (mixed $value) => [
                'sold_out_date' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? BusinessDay::todayString() : null,
            ],
        );
    }
}
```

`app/Models/QuickNote.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickNote extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'label', 'position'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

`app/Models/Order.php`:

```php
<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Support\BusinessDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'business_date', 'number', 'customer_name', 'takeaway', 'status', 'source', 'total_cents',
        'created_by', 'ready_at', 'delivered_at', 'canceled_at', 'edited_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'number' => 'integer',
            'takeaway' => 'boolean',
            'status' => OrderStatus::class,
            'source' => OrderSource::class,
            'total_cents' => 'integer',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'canceled_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeToday(Builder $query): void
    {
        $query->whereDate('business_date', BusinessDay::todayString());
    }

    public function displayNumber(): string
    {
        return str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    public function firstName(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->customer_name)) ?: [''];

        return Str::ucfirst(Str::lower($parts[0]));
    }
}
```

`app/Models/OrderItem.php`:

```php
<?php

namespace App\Models;

use App\Enums\Destination;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'name_snapshot', 'unit_price_cents', 'quantity', 'notes', 'destination', 'position',
    ];

    protected function casts(): array
    {
        return [
            'destination' => Destination::class,
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'position' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

`app/Models/DailyCounter.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCounter extends Model
{
    protected $primaryKey = 'business_date';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['business_date', 'last_number'];
}
```

`app/Models/Setting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const READY_MINUTES = 'ready_minutes';

    public const RESTAURANT_NAME = 'restaurant_name';

    public const STATION_LEFT_WIDTH = 'station_left_width';

    public const STATION_RIGHT_WIDTH = 'station_right_width';

    public const DEFAULTS = [
        self::READY_MINUTES => 5,
        self::RESTAURANT_NAME => 'Nana Lanches',
        self::STATION_LEFT_WIDTH => 1920,
        self::STATION_RIGHT_WIDTH => 1920,
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("setting.{$key}", fn () => static::query()->find($key)?->value);

        return $value ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.{$key}");
    }
}
```

- [ ] **Step 9: Write the factories**

`database/factories/UserFactory.php` (replace the file):

```php
<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'username' => fake()->unique()->userName(),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Balcao,
            'remember_token' => Str::random(10),
        ];
    }
}
```

`database/factories/CategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\Destination;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'destination' => Destination::Cozinha,
            'position' => 0,
            'active' => true,
        ];
    }
}
```

`database/factories/ProductFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'price_cents' => fake()->numberBetween(400, 4000),
            'has_daily_name' => false,
            'active' => true,
            'position' => 0,
        ];
    }
}
```

`database/factories/OrderFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\BusinessDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'business_date' => BusinessDay::todayString(),
            'number' => fake()->unique()->numberBetween(1, 999),
            'customer_name' => fake()->firstName(),
            'takeaway' => false,
            'status' => OrderStatus::Preparing,
            'source' => OrderSource::Balcao,
            'total_cents' => 0,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Ready, 'ready_at' => now()]);
    }
}
```

`database/factories/OrderItemFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\Destination;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'name_snapshot' => ucfirst(fake()->words(2, true)),
            'unit_price_cents' => 2400,
            'quantity' => 1,
            'notes' => null,
            'destination' => Destination::Cozinha,
            'position' => 0,
        ];
    }
}
```

- [ ] **Step 10: Run all tests and migrate locally**

Run: `./vendor/bin/pest && php artisan migrate:fresh`
Expected: all tests PASS and the migrations run on MySQL without errors.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: enums, schema, models e factories do domínio" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Initial menu and users (seeders)

**Files:**
- Create: `database/seeders/MenuSeeder.php`, `database/seeders/UserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Seeders/SeedersTest.php`

**Interfaces:**
- Consumes: the models from Task 3.
- Produces:
  - categories `Pratos` (cozinha), `Sucos` (cozinha), `Bebidas` (cabine);
  - the 15 dishes and 3 juices from the spec's appendix A;
  - quick notes;
  - users `admin` and `balcao`, with passwords taken from the env vars `SEED_ADMIN_PASSWORD` / `SEED_BALCAO_PASSWORD` or generated and printed.
- Seeders never overwrite existing rows (`firstOrCreate`), so re-running them in production keeps prices the father changed.

- [ ] **Step 1: Write the failing test** `tests/Feature/Seeders/SeedersTest.php`:

```php
<?php

use App\Enums\Destination;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\QuickNote;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds the Nana Lanches menu and the initial users', function () {
    $this->seed(DatabaseSeeder::class);

    $pratos = Category::query()->where('name', 'Pratos')->sole();
    $sucos = Category::query()->where('name', 'Sucos')->sole();

    expect($pratos->destination)->toBe(Destination::Cozinha)
        ->and(Category::query()->where('name', 'Bebidas')->sole()->destination)->toBe(Destination::Cabine)
        ->and($pratos->products()->count())->toBe(15)
        ->and($sucos->products()->pluck('price_cents', 'name')->all())
        ->toBe(['Suco de limão' => 400, 'Suco de goiaba' => 500, 'Suco de manga' => 600])
        ->and(Product::query()->where('name', 'Prato do dia')->sole()->has_daily_name)->toBeTrue()
        ->and(Product::query()->where('name', 'Picanha bovina')->sole()->price_cents)->toBe(3800)
        ->and(QuickNote::query()->where('category_id', $pratos->id)->orderBy('position')->pluck('label')->all())
        ->toBe(['Sem arroz', 'Sem feijão', 'Sem fritas', 'Sem farofa', 'Sem salada', 'C/ Ovo', 'C/ Cebola'])
        ->and(User::query()->where('username', 'admin')->sole()->role)->toBe(Role::Admin)
        ->and(User::query()->where('username', 'balcao')->sole()->role)->toBe(Role::Balcao);
});

it('can run twice without duplicating or overwriting prices', function () {
    $this->seed(DatabaseSeeder::class);
    Product::query()->where('name', 'Picanha bovina')->update(['price_cents' => 4200]);

    $this->seed(DatabaseSeeder::class);

    expect(Product::query()->count())->toBe(18)
        ->and(User::query()->count())->toBe(2)
        ->and(Product::query()->where('name', 'Picanha bovina')->value('price_cents'))->toBe(4200);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Seeders`
Expected: FAIL, because the categories don't exist (`sole()` throws `ModelNotFoundException`).

- [ ] **Step 3: Implement the seeders**

`database/seeders/MenuSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\Destination;
use App\Models\Category;
use App\Models\Product;
use App\Models\QuickNote;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /** @var list<array{0: string, 1: int, 2: bool}> name, price in cents, has daily name */
    private const DISHES = [
        ['Prato do dia', 2600, true],
        ['Prato Econômico', 1700, true],
        ['Frango ao molho', 2300, false],
        ['Fígado bovino', 2200, false],
        ['Omelete especial', 2200, false],
        ['Filé de frango', 2400, false],
        ['Filé suíno', 2200, false],
        ['Frango à milanesa', 2500, false],
        ['Parmegiana de frango', 2700, false],
        ['Filé de peixe', 2900, false],
        ['Carne de sol', 3200, false],
        ['Coxão mole', 3400, false],
        ['Parmegiana de carne', 3600, false],
        ['Filé mignon', 4000, false],
        ['Picanha bovina', 3800, false],
    ];

    private const JUICES = [['Suco de limão', 400], ['Suco de goiaba', 500], ['Suco de manga', 600]];

    private const DISH_NOTES = ['Sem arroz', 'Sem feijão', 'Sem fritas', 'Sem farofa', 'Sem salada', 'C/ Ovo', 'C/ Cebola'];

    public function run(): void
    {
        $pratos = Category::query()->firstOrCreate(['name' => 'Pratos'], ['destination' => Destination::Cozinha, 'position' => 1]);
        $sucos = Category::query()->firstOrCreate(['name' => 'Sucos'], ['destination' => Destination::Cozinha, 'position' => 2]);
        Category::query()->firstOrCreate(['name' => 'Bebidas'], ['destination' => Destination::Cabine, 'position' => 3]);

        foreach (self::DISHES as $index => [$name, $cents, $daily]) {
            Product::query()->firstOrCreate(
                ['category_id' => $pratos->id, 'name' => $name],
                ['price_cents' => $cents, 'has_daily_name' => $daily, 'position' => $index + 1],
            );
        }

        foreach (self::JUICES as $index => [$name, $cents]) {
            Product::query()->firstOrCreate(
                ['category_id' => $sucos->id, 'name' => $name],
                ['price_cents' => $cents, 'position' => $index + 1],
            );
        }

        foreach (self::DISH_NOTES as $index => $label) {
            QuickNote::query()->firstOrCreate(['category_id' => $pratos->id, 'label' => $label], ['position' => $index + 1]);
        }

        QuickNote::query()->firstOrCreate(['category_id' => $sucos->id, 'label' => 'Sem açúcar'], ['position' => 1]);
    }
}
```

`database/seeders/UserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['admin', 'Administrador', Role::Admin, 'SEED_ADMIN_PASSWORD'],
            ['balcao', 'Balcão', Role::Balcao, 'SEED_BALCAO_PASSWORD'],
        ];

        foreach ($users as [$username, $name, $role, $envKey]) {
            if (User::query()->where('username', $username)->exists()) {
                continue;
            }

            $password = (string) (getenv($envKey) ?: Str::password(12, symbols: false));

            User::query()->create(['username' => $username, 'name' => $name, 'role' => $role, 'password' => $password]);

            $this->command?->warn("Usuário {$username} criado. Senha: {$password}");
        }
    }
}
```

`database/seeders/DatabaseSeeder.php` (replace the file):

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([MenuSeeder::class, UserSeeder::class]);
    }
}
```

- [ ] **Step 4: Run the tests and seed locally**

Run: `./vendor/bin/pest tests/Feature/Seeders && php artisan migrate:fresh --seed`
Expected: PASS, and the seeder prints the two generated passwords. Write them down for local testing.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: cardápio inicial da Nana Lanches e usuários" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Real-time events and broadcaster

**Files:**
- Create (via installer): `config/reverb.php`, `config/broadcasting.php`, `routes/channels.php`, `resources/js/echo.js`
- Create: `app/Events/OrdersChanged.php`, `app/Events/BoardChanged.php`, `app/Support/OrderBroadcaster.php`
- Modify: `routes/channels.php`
- Test: `tests/Feature/Realtime/OrderBroadcasterTest.php`

**Interfaces:**
- Consumes: `Order::displayNumber()`, `Order::firstName()`, `User::hasRole()`.
- Produces:
  - `OrderBroadcaster::changed(Order $order, string $kind): void`. `$kind` is one of `created|updated|ready|unready|delivered|canceled`. Broadcasting runs after the DB commit, and failures are reported to the log, never thrown.
  - `OrdersChanged(int $orderId, string $kind)` on private channel `pedidos`. The Livewire listener name is `echo-private:pedidos,OrdersChanged` and the payload keys are `orderId`, `kind`.
  - `BoardChanged(int $orderId, string $kind, string $number, string $firstName, string $status)` on public channel `painel`. The listener name is `echo:painel,BoardChanged` and the payload keys are `orderId`, `kind`, `number`, `firstName`, `status`.

- [ ] **Step 1: Install broadcasting with Reverb**

Run: `php artisan install:broadcasting --reverb`
Answer **yes** to installing Reverb and to installing and building the Node dependencies. Then check that `.env` contains `BROADCAST_CONNECTION=reverb` and the `REVERB_*` / `VITE_REVERB_*` keys, and that `resources/js/echo.js` exists and is imported from `resources/js/app.js` or `resources/js/bootstrap.js`.

- [ ] **Step 2: Write the failing test** `tests/Feature/Realtime/OrderBroadcasterTest.php`:

```php
<?php

use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Models\Order;
use App\Support\OrderBroadcaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;

it('broadcasts a private change and a public board change with only safe fields', function () {
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $order = Order::factory()->create(['number' => 42, 'customer_name' => 'Maria Souza']);

    app(OrderBroadcaster::class)->changed($order, 'ready');

    Event::assertDispatched(OrdersChanged::class, fn (OrdersChanged $e) => $e->orderId === $order->id && $e->kind === 'ready');
    Event::assertDispatched(BoardChanged::class, function (BoardChanged $e) use ($order) {
        return $e->orderId === $order->id
            && $e->number === '042'
            && $e->firstName === 'Maria'
            && $e->status === 'preparing'
            && $e->kind === 'ready'
            && ! property_exists($e, 'customerName');
    });
});

it('waits for the surrounding transaction to commit', function () {
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $order = Order::factory()->create();

    DB::transaction(function () use ($order) {
        app(OrderBroadcaster::class)->changed($order, 'created');
        Event::assertNotDispatched(OrdersChanged::class);
    });

    Event::assertDispatched(OrdersChanged::class);
});

it('never lets a broadcasting failure break the caller', function () {
    Exceptions::fake();
    Event::listen(OrdersChanged::class, fn () => throw new RuntimeException('reverb fora do ar'));
    $order = Order::factory()->create();

    app(OrderBroadcaster::class)->changed($order, 'created');

    Exceptions::assertReported(RuntimeException::class);
});

it('uses the expected channels', function () {
    expect((new OrdersChanged(1, 'created'))->broadcastOn()[0]->name)->toBe('private-pedidos')
        ->and((new BoardChanged(1, 'created', '001', 'Maria', 'preparing'))->broadcastOn()[0]->name)->toBe('painel');
});
```

- [ ] **Step 3: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Realtime`
Expected: FAIL, `Class "App\Events\OrdersChanged" not found`.

- [ ] **Step 4: Implement the events, the broadcaster and the channel**

`app/Events/OrdersChanged.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class OrdersChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $orderId, public string $kind) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('pedidos')];
    }
}
```

`app/Events/BoardChanged.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class BoardChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $orderId,
        public string $kind,
        public string $number,
        public string $firstName,
        public string $status,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('painel')];
    }
}
```

`app/Support/OrderBroadcaster.php`:

```php
<?php

namespace App\Support;

use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OrderBroadcaster
{
    public function changed(Order $order, string $kind): void
    {
        $orderId = $order->id;
        $number = $order->displayNumber();
        $firstName = $order->firstName();
        $status = $order->status->value;

        DB::afterCommit(function () use ($orderId, $kind, $number, $firstName, $status) {
            try {
                broadcast(new OrdersChanged($orderId, $kind));
                broadcast(new BoardChanged($orderId, $kind, $number, $firstName, $status));
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
```

Replace `routes/channels.php` with:

```php
<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('pedidos', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Balcao, Role::Cozinha));
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Realtime`
Expected: PASS. If `broadcast()` dispatched before commit, check that the `DB::afterCommit` wrapper is there.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: eventos de tempo real (Reverb) e OrderBroadcaster" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Placing orders (daily numbering, snapshots, idempotency)

**Files:**
- Create: `app/Actions/Orders/{OrderInput,BuildOrderItems,NextOrderNumber,PlaceOrder}.php`
- Modify: `tests/Pest.php`
- Test: `tests/Feature/Orders/PlaceOrderTest.php`

**Interfaces:**
- Consumes: `Product::isSoldOut()`, `needsDailyName()` and `displayName()`; `BusinessDay::today()`; `OrderBroadcaster::changed()`.
- Produces:
  - `OrderInput::rules(): array`, `OrderInput::messages(): array`.
  - `BuildOrderItems::handle(array $items): array{items: list<array>, total_cents: int, has_kitchen: bool}`. Throws `ValidationException` with key `items.{i}.product_id`.
  - `NextOrderNumber::for(CarbonImmutable $day): int`. Must be called inside a transaction.
  - `PlaceOrder::handle(array $input, ?User $by = null, OrderSource $source = OrderSource::Balcao): Order`.
    - `$input` shape: `{uuid, customer_name, takeaway, items: list<{product_id, quantity, notes}>}`.
    - Returns the order with `items` loaded.
    - A repeated uuid returns the existing order.
    - Broadcasts `created`, or `delivered` for cabine-only orders.
  - Pest helpers `item(Product, int $qty = 1, ?string $notes = null)`, `orderInput(array $items, array $overrides = [])`, `placeOrder(array $items, array $overrides = [], ?User $by = null)`.

- [ ] **Step 1: Add the test helpers.** Append to `tests/Pest.php`, and add `use App\Actions\Orders\PlaceOrder;`, `use App\Models\Order;` and `use Illuminate\Support\Str;` to its imports:

```php
/** @return array{product_id: int, quantity: int, notes: ?string} */
function item(Product $product, int $quantity = 1, ?string $notes = null): array
{
    return ['product_id' => $product->id, 'quantity' => $quantity, 'notes' => $notes];
}

function orderInput(array $items, array $overrides = []): array
{
    return array_merge(['uuid' => (string) Str::uuid(), 'customer_name' => 'Maria', 'takeaway' => false, 'items' => $items], $overrides);
}

function placeOrder(array $items, array $overrides = [], ?User $by = null): Order
{
    return app(PlaceOrder::class)->handle(orderInput($items, $overrides), $by);
}
```

- [ ] **Step 2: Write the failing test** `tests/Feature/Orders/PlaceOrderTest.php`:

```php
<?php

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
});

it('numbers orders per day starting at 1 and restarts the next day', function () {
    $dish = kitchenProduct();

    expect(placeOrder([item($dish)])->number)->toBe(1)
        ->and(placeOrder([item($dish)])->number)->toBe(2);

    $this->travelTo(CarbonImmutable::parse('2026-11-14 11:00', 'America/Sao_Paulo'));

    expect(placeOrder([item($dish)])->number)->toBe(1);
});

it('computes the total on the server and freezes name, price and destination', function () {
    $dish = kitchenProduct(['name' => 'Filé de frango', 'price_cents' => 2400]);
    $soda = cabineProduct(['name' => 'Coca lata', 'price_cents' => 600]);

    $order = placeOrder([item($dish, 2, 'Sem arroz'), item($soda)], ['customer_name' => '  João  ', 'takeaway' => true]);
    $dish->update(['price_cents' => 9900, 'name' => 'Outro nome']);

    $order = $order->fresh('items');
    expect($order->total_cents)->toBe(5400)
        ->and($order->customer_name)->toBe('João')
        ->and($order->takeaway)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::Preparing)
        ->and($order->items->pluck('name_snapshot')->all())->toBe(['Filé de frango', 'Coca lata'])
        ->and($order->items->first()->unit_price_cents)->toBe(2400)
        ->and($order->items->first()->notes)->toBe('Sem arroz')
        ->and($order->items->last()->destination->value)->toBe('cabine');
});

it('stores the dish of the day name in the snapshot', function () {
    $dish = kitchenProduct(['name' => 'Prato do dia', 'has_daily_name' => true]);
    $dish->setDailyName('Feijoada');

    expect(placeOrder([item($dish)])->items->first()->name_snapshot)->toBe('Prato do dia: Feijoada');
});

it('returns the existing order when the same uuid is sent twice', function () {
    $dish = kitchenProduct();
    $input = orderInput([item($dish)]);

    $first = app(\App\Actions\Orders\PlaceOrder::class)->handle($input);
    $second = app(\App\Actions\Orders\PlaceOrder::class)->handle($input);

    expect($second->id)->toBe($first->id)->and(Order::query()->count())->toBe(1);
});

it('delivers cabine-only orders immediately', function () {
    $order = placeOrder([item(cabineProduct())]);

    expect($order->status)->toBe(OrderStatus::Delivered)->and($order->delivered_at)->not->toBeNull();
    Event::assertDispatched(OrdersChanged::class, fn ($e) => $e->kind === 'delivered');
});

it('rejects sold out products without consuming a number', function () {
    $dish = kitchenProduct();
    $dish->toggleSoldOut();

    expect(fn () => placeOrder([item($dish)]))->toThrow(ValidationException::class);
    expect(Order::query()->count())->toBe(0);

    expect(placeOrder([item(kitchenProduct())])->number)->toBe(1);
});

it('rejects a dish of the day without today name', function () {
    $dish = kitchenProduct(['has_daily_name' => true]);

    placeOrder([item($dish)]);
})->throws(ValidationException::class);

it('requires a customer name and at least one item', function () {
    expect(fn () => placeOrder([], ['customer_name' => '']))->toThrow(ValidationException::class);
});

it('records who placed the order and the source, and broadcasts it', function () {
    $user = userWithRole(Role::Balcao);

    $order = placeOrder([item(kitchenProduct())], by: $user);

    expect($order->created_by)->toBe($user->id)->and($order->source)->toBe(OrderSource::Balcao);
    Event::assertDispatched(OrdersChanged::class, fn ($e) => $e->kind === 'created' && $e->orderId === $order->id);
});
```

- [ ] **Step 3: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Orders/PlaceOrderTest.php`
Expected: FAIL, `Class "App\Actions\Orders\PlaceOrder" not found`.

- [ ] **Step 4: Implement**

`app/Actions/Orders/OrderInput.php`:

```php
<?php

namespace App\Actions\Orders;

final class OrderInput
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:60'],
            'takeaway' => ['boolean'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'customer_name.required' => 'Informe o nome do cliente.',
            'customer_name.max' => 'O nome pode ter no máximo 60 letras.',
            'items.required' => 'Adicione pelo menos um item.',
            'items.min' => 'Adicione pelo menos um item.',
            'items.*.quantity.max' => 'Quantidade máxima por item: 20.',
        ];
    }
}
```

`app/Actions/Orders/BuildOrderItems.php`:

```php
<?php

namespace App\Actions\Orders;

use App\Enums\Destination;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class BuildOrderItems
{
    /**
     * @param  list<array{product_id: int|string, quantity: int|string, notes?: ?string}>  $items
     * @return array{items: list<array<string, mixed>>, total_cents: int, has_kitchen: bool}
     */
    public function handle(array $items): array
    {
        $products = Product::query()
            ->with('category')
            ->whereIn('id', array_map(fn (array $item) => (int) $item['product_id'], $items))
            ->get()
            ->keyBy('id');

        $rows = [];
        $total = 0;
        $hasKitchen = false;

        foreach (array_values($items) as $index => $item) {
            $product = $products->get((int) $item['product_id']);

            $error = match (true) {
                $product === null => 'Produto não encontrado.',
                ! $product->active || ! $product->category->active => "{$product->name} não está disponível.",
                $product->isSoldOut() => "{$product->name} está esgotado hoje.",
                $product->needsDailyName() => "Defina o prato de hoje para {$product->name}.",
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => $error]);
            }

            $quantity = (int) $item['quantity'];
            $notes = trim((string) ($item['notes'] ?? ''));
            $destination = $product->category->destination;

            $rows[] = [
                'product_id' => $product->id,
                'name_snapshot' => $product->displayName(),
                'unit_price_cents' => $product->price_cents,
                'quantity' => $quantity,
                'notes' => $notes === '' ? null : $notes,
                'destination' => $destination,
                'position' => $index,
            ];

            $total += $quantity * $product->price_cents;
            $hasKitchen = $hasKitchen || $destination === Destination::Cozinha;
        }

        return ['items' => $rows, 'total_cents' => $total, 'has_kitchen' => $hasKitchen];
    }
}
```

`app/Actions/Orders/NextOrderNumber.php`:

```php
<?php

namespace App\Actions\Orders;

use App\Models\DailyCounter;
use Carbon\CarbonImmutable;

final class NextOrderNumber
{
    /** Must run inside a DB transaction: the counter row stays locked until commit. */
    public function for(CarbonImmutable $day): int
    {
        $date = $day->toDateString();

        DailyCounter::query()->insertOrIgnore(['business_date' => $date, 'last_number' => 0]);

        $counter = DailyCounter::query()->whereKey($date)->lockForUpdate()->firstOrFail();
        $counter->last_number++;
        $counter->save();

        return (int) $counter->last_number;
    }
}
```

`app/Actions/Orders/PlaceOrder.php`:

```php
<?php

namespace App\Actions\Orders;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\OrderBroadcaster;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class PlaceOrder
{
    public function __construct(
        private NextOrderNumber $numbers,
        private BuildOrderItems $builder,
        private OrderBroadcaster $broadcaster,
    ) {}

    public function handle(array $input, ?User $by = null, OrderSource $source = OrderSource::Balcao): Order
    {
        $data = Validator::make($input, ['uuid' => ['required', 'uuid'], ...OrderInput::rules()], OrderInput::messages())->validate();

        $existing = Order::query()->where('uuid', $data['uuid'])->first();
        if ($existing !== null) {
            return $existing->load('items');
        }

        $built = $this->builder->handle($data['items']);

        try {
            $order = DB::transaction(function () use ($data, $built, $by, $source) {
                $day = BusinessDay::today();

                $order = Order::query()->create([
                    'uuid' => $data['uuid'],
                    'business_date' => $day->toDateString(),
                    'number' => $this->numbers->for($day),
                    'customer_name' => trim($data['customer_name']),
                    'takeaway' => (bool) ($data['takeaway'] ?? false),
                    'status' => $built['has_kitchen'] ? OrderStatus::Preparing : OrderStatus::Delivered,
                    'delivered_at' => $built['has_kitchen'] ? null : now(),
                    'source' => $source,
                    'total_cents' => $built['total_cents'],
                    'created_by' => $by?->id,
                ]);

                $order->items()->createMany($built['items']);

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            $existing = Order::query()->where('uuid', $data['uuid'])->first();
            if ($existing === null) {
                throw $e;
            }

            return $existing->load('items');
        }

        $order->load('items');
        $this->broadcaster->changed($order, $order->status === OrderStatus::Preparing ? 'created' : 'delivered');

        return $order;
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Orders/PlaceOrderTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: lançamento de pedidos com número diário, snapshot e idempotência" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Order transitions and edits

**Files:**
- Create: `app/Actions/Orders/TransitionOrder.php`, `app/Actions/Orders/UpdateOrderItems.php`
- Test: `tests/Feature/Orders/TransitionOrderTest.php`, `tests/Feature/Orders/UpdateOrderItemsTest.php`

**Interfaces:**
- Consumes: `BuildOrderItems`, `OrderInput`, `OrderBroadcaster`, `User::hasRole()`.
- Produces:
  - `TransitionOrder::markReady(Order): Order`
  - `TransitionOrder::undoReady(Order, User $by): Order`. Throws `AuthorizationException` for the `cozinha` role after `UNDO_WINDOW_SECONDS = 15`.
  - `TransitionOrder::markDelivered(Order): Order`
  - `TransitionOrder::cancel(Order, string $reason): Order`. Throws `ValidationException` with key `cancelReason` when the reason is empty or longer than 120 characters.
  - Invalid or repeated transitions are silent no-ops that return the fresh order without broadcasting.
  - `UpdateOrderItems::handle(Order, array $input): Order`. The input is the same shape as `PlaceOrder` without the uuid. Only works while `preparing`, otherwise throws `ValidationException` with key `order`. Sets `edited_at`; an order left without kitchen items becomes `delivered`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Orders/TransitionOrderTest.php`:

```php
<?php

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->actions = app(TransitionOrder::class);
    $this->order = placeOrder([item(kitchenProduct())]);
});

it('marks an order ready once and ignores the second tap', function () {
    $ready = $this->actions->markReady($this->order);
    $again = $this->actions->markReady($this->order);

    expect($ready->status)->toBe(OrderStatus::Ready)
        ->and($ready->ready_at)->not->toBeNull()
        ->and($again->ready_at->equalTo($ready->ready_at))->toBeTrue();
    Event::assertDispatchedTimes(OrdersChanged::class, 2); // created + ready
});

it('lets the kitchen undo within the window but not after it', function () {
    $cozinha = userWithRole(Role::Cozinha);
    $this->actions->markReady($this->order);

    $this->travel(10)->seconds();
    expect($this->actions->undoReady($this->order, $cozinha)->status)->toBe(OrderStatus::Preparing);

    $this->actions->markReady($this->order);
    $this->travel(20)->seconds();
    expect(fn () => $this->actions->undoReady($this->order, $cozinha))->toThrow(AuthorizationException::class);
});

it('lets the counter send a ready order back to preparing at any time', function () {
    $this->actions->markReady($this->order);
    $this->travel(30)->minutes();

    $back = $this->actions->undoReady($this->order, userWithRole(Role::Balcao));

    expect($back->status)->toBe(OrderStatus::Preparing)->and($back->ready_at)->toBeNull();
});

it('delivers only ready orders', function () {
    expect($this->actions->markDelivered($this->order)->status)->toBe(OrderStatus::Preparing);

    $this->actions->markReady($this->order);
    expect($this->actions->markDelivered($this->order)->status)->toBe(OrderStatus::Delivered);
});

it('cancels with a reason and keeps the order in history', function () {
    $canceled = $this->actions->cancel($this->order, 'Cliente desistiu');

    expect($canceled->status)->toBe(OrderStatus::Canceled)
        ->and($canceled->cancel_reason)->toBe('Cliente desistiu')
        ->and($canceled->canceled_at)->not->toBeNull();

    expect($this->actions->markReady($canceled)->status)->toBe(OrderStatus::Canceled);
});

it('requires a cancel reason', function () {
    $this->actions->cancel($this->order, '   ');
})->throws(ValidationException::class);
```

`tests/Feature/Orders/UpdateOrderItemsTest.php`:

```php
<?php

use App\Actions\Orders\TransitionOrder;
use App\Actions\Orders\UpdateOrderItems;
use App\Enums\OrderStatus;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
});

it('replaces items, recomputes the total and flags the order as edited', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $juice = kitchenProduct(['price_cents' => 500]);
    $order = placeOrder([item($dish)]);

    $updated = app(UpdateOrderItems::class)->handle($order, [
        'customer_name' => 'Maria Clara',
        'takeaway' => true,
        'items' => [item($dish, 1, 'Sem salada'), item($juice, 2)],
    ]);

    expect($updated->items)->toHaveCount(2)
        ->and($updated->total_cents)->toBe(3400)
        ->and($updated->customer_name)->toBe('Maria Clara')
        ->and($updated->edited_at)->not->toBeNull()
        ->and($updated->number)->toBe($order->number);
    Event::assertDispatched(OrdersChanged::class, fn ($e) => $e->kind === 'updated');
});

it('refuses to edit an order that is no longer preparing', function () {
    $dish = kitchenProduct();
    $order = placeOrder([item($dish)]);
    app(TransitionOrder::class)->markReady($order);

    app(UpdateOrderItems::class)->handle($order, ['customer_name' => 'X', 'takeaway' => false, 'items' => [item($dish)]]);
})->throws(ValidationException::class);

it('delivers the order when no kitchen item is left', function () {
    $order = placeOrder([item(kitchenProduct())]);

    $updated = app(UpdateOrderItems::class)->handle($order, [
        'customer_name' => 'Maria', 'takeaway' => false, 'items' => [item(cabineProduct())],
    ]);

    expect($updated->status)->toBe(OrderStatus::Delivered);
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Orders`
Expected: FAIL, `Class "App\Actions\Orders\TransitionOrder" not found`.

- [ ] **Step 3: Implement**

`app/Actions/Orders/TransitionOrder.php`:

```php
<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderBroadcaster;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class TransitionOrder
{
    public const UNDO_WINDOW_SECONDS = 15;

    public function __construct(private OrderBroadcaster $broadcaster) {}

    public function markReady(Order $order): Order
    {
        return $this->move($order, [OrderStatus::Preparing], OrderStatus::Ready, ['ready_at' => now()], 'ready');
    }

    public function undoReady(Order $order, User $by): Order
    {
        if ($by->hasRole(Role::Cozinha)) {
            $fresh = $order->fresh();
            if ($fresh->status === OrderStatus::Ready
                && $fresh->ready_at !== null
                && $fresh->ready_at->lt(now()->subSeconds(self::UNDO_WINDOW_SECONDS))) {
                throw new AuthorizationException('O tempo para desfazer acabou.');
            }
        }

        return $this->move($order, [OrderStatus::Ready], OrderStatus::Preparing, ['ready_at' => null], 'unready');
    }

    public function markDelivered(Order $order): Order
    {
        return $this->move($order, [OrderStatus::Ready], OrderStatus::Delivered, ['delivered_at' => now()], 'delivered');
    }

    public function cancel(Order $order, string $reason): Order
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 120) {
            throw ValidationException::withMessages(['cancelReason' => 'Informe o motivo do cancelamento (até 120 caracteres).']);
        }

        return $this->move(
            $order,
            [OrderStatus::Preparing, OrderStatus::Ready],
            OrderStatus::Canceled,
            ['canceled_at' => now(), 'cancel_reason' => $reason],
            'canceled',
        );
    }

    /**
     * Atomic conditional update: concurrent or repeated taps update at most once.
     *
     * @param  list<OrderStatus>  $from
     * @param  array<string, mixed>  $attributes
     */
    private function move(Order $order, array $from, OrderStatus $to, array $attributes, string $kind): Order
    {
        $updated = Order::query()
            ->whereKey($order->getKey())
            ->whereIn('status', array_map(fn (OrderStatus $status) => $status->value, $from))
            ->update([...$attributes, 'status' => $to->value, 'updated_at' => now()]);

        $fresh = $order->fresh(['items']);

        if ($updated === 1) {
            $this->broadcaster->changed($fresh, $kind);
        }

        return $fresh;
    }
}
```

`app/Actions/Orders/UpdateOrderItems.php`:

```php
<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\OrderBroadcaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateOrderItems
{
    public function __construct(private BuildOrderItems $builder, private OrderBroadcaster $broadcaster) {}

    public function handle(Order $order, array $input): Order
    {
        $data = Validator::make($input, OrderInput::rules(), OrderInput::messages())->validate();
        $built = $this->builder->handle($data['items']);

        $kind = DB::transaction(function () use ($order, $data, $built) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Preparing) {
                throw ValidationException::withMessages(['order' => 'Este pedido não pode mais ser alterado.']);
            }

            $locked->items()->delete();
            $locked->items()->createMany($built['items']);

            $locked->fill([
                'customer_name' => trim($data['customer_name']),
                'takeaway' => (bool) ($data['takeaway'] ?? false),
                'total_cents' => $built['total_cents'],
                'edited_at' => now(),
            ]);

            if (! $built['has_kitchen']) {
                $locked->status = OrderStatus::Delivered;
                $locked->delivered_at = now();
            }

            $locked->save();

            return $built['has_kitchen'] ? 'updated' : 'delivered';
        });

        $fresh = $order->fresh(['items']);
        $this->broadcaster->changed($fresh, $kind);

        return $fresh;
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Orders`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: transições de status e alteração de pedidos" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Login, roles, gates and route skeleton

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/HomeController.php`, `app/Http/Controllers/StationController.php`, `app/Http/Middleware/EnsureRole.php`
- Create: `resources/views/components/layouts/app.blade.php`, `resources/views/auth/login.blade.php`, `resources/views/pages/{estacao,balcao,cozinha,painel,acompanhar}.blade.php`
- Modify: `routes/web.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Auth/AccessTest.php`

**Interfaces:**
- Consumes: `User::hasRole()`, `Role`, `Setting`.
- Produces:
  - Named routes: `home`, `login`, `login.store`, `logout`, `estacao`, `balcao`, `cozinha`, `painel`, `acompanhar`.
  - Middleware alias `role:<roles>`.
  - Gates: `place-orders` and `manage-orders` (admin, balcao); `mark-ready` (admin, balcao, cozinha).
  - The `<x-layouts.app :title :body-class>` layout component.
  - `StationController` passes `$leftWidth` and `$rightWidth` (int) to `pages.estacao`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Auth/LoginTest.php`:

```php
<?php

use App\Enums\Role;
use App\Models\User;

it('logs in with username and password and remembers the device', function () {
    $user = User::factory()->create(['username' => 'balcao', 'password' => 'segredo123', 'role' => Role::Balcao]);

    $response = $this->post('/login', ['username' => 'balcao', 'password' => 'segredo123', 'remember' => '1']);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('rejects a wrong password with a Portuguese message', function () {
    User::factory()->create(['username' => 'balcao', 'password' => 'segredo123']);

    $this->post('/login', ['username' => 'balcao', 'password' => 'errada'])
        ->assertSessionHasErrors(['username' => 'Usuário ou senha incorretos.']);
    $this->assertGuest();
});

it('throttles repeated failed logins', function () {
    User::factory()->create(['username' => 'balcao', 'password' => 'segredo123']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['username' => 'balcao', 'password' => 'errada']);
    }

    $this->post('/login', ['username' => 'balcao', 'password' => 'errada'])->assertStatus(429);
});

it('logs out', function () {
    $this->actingAs(userWithRole(Role::Balcao))->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});
```

`tests/Feature/Auth/AccessTest.php`:

```php
<?php

use App\Enums\Role;
use Illuminate\Support\Facades\Gate;

it('sends guests to the login page', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/estacao', '/balcao', '/cozinha']);

it('redirects home by role', function (Role $role, string $route) {
    $this->actingAs(userWithRole($role))->get('/')->assertRedirect(route($route));
})->with([[Role::Admin, 'estacao'], [Role::Balcao, 'estacao'], [Role::Cozinha, 'cozinha']]);

it('keeps the kitchen profile out of the counter screens', function () {
    $cozinha = userWithRole(Role::Cozinha);

    $this->actingAs($cozinha)->get('/estacao')->assertForbidden();
    $this->actingAs($cozinha)->get('/balcao')->assertForbidden();
    $this->actingAs($cozinha)->get('/cozinha')->assertOk();
});

it('lets the counter open its screens', function () {
    $balcao = userWithRole(Role::Balcao);

    $this->actingAs($balcao)->get('/estacao')->assertOk();
    $this->actingAs($balcao)->get('/balcao')->assertOk();
    $this->actingAs($balcao)->get('/cozinha')->assertOk();
});

it('serves the public screens without login', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/painel', '/acompanhar']);

it('defines the order gates per role', function () {
    $cozinha = userWithRole(Role::Cozinha);
    $balcao = userWithRole(Role::Balcao);

    expect(Gate::forUser($cozinha)->allows('mark-ready'))->toBeTrue()
        ->and(Gate::forUser($cozinha)->allows('place-orders'))->toBeFalse()
        ->and(Gate::forUser($cozinha)->allows('manage-orders'))->toBeFalse()
        ->and(Gate::forUser($balcao)->allows('place-orders'))->toBeTrue()
        ->and(Gate::forUser($balcao)->allows('manage-orders'))->toBeTrue();
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Auth`
Expected: FAIL, with 404s for `/login` and `/estacao`.

- [ ] **Step 3: Implement the middleware, gates and controllers**

`app/Http/Middleware/EnsureRole.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user !== null && in_array($user->role->value, $roles, true), 403);

        return $next($request);
    }
}
```

In `bootstrap/app.php`, make `withMiddleware` read:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
})
```

`app/Providers/AppServiceProvider.php` (replace the file):

```php
<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('place-orders', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Balcao));
        Gate::define('manage-orders', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Balcao));
        Gate::define('mark-ready', fn (User $user): bool => $user->hasRole(Role::Admin, Role::Balcao, Role::Cozinha));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('username')).'|'.$request->ip()));
    }
}
```

`app/Http/Controllers/Auth/LoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginController
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(
            ['username' => ['required', 'string'], 'password' => ['required', 'string']],
            ['username.required' => 'Informe o usuário.', 'password.required' => 'Informe a senha.'],
        );

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['username' => 'Usuário ou senha incorretos.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

`app/Http/Controllers/HomeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class HomeController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        return redirect()->route($user->hasRole(Role::Cozinha) ? 'cozinha' : 'estacao');
    }
}
```

`app/Http/Controllers/StationController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Contracts\View\View;

final class StationController
{
    public function __invoke(): View
    {
        return view('pages.estacao', [
            'leftWidth' => (int) Setting::get(Setting::STATION_LEFT_WIDTH),
            'rightWidth' => (int) Setting::get(Setting::STATION_RIGHT_WIDTH),
        ]);
    }
}
```

`routes/web.php` (replace the file):

```php
<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin,balcao'])->group(function () {
    Route::get('/estacao', StationController::class)->name('estacao');
    Route::view('/balcao', 'pages.balcao')->name('balcao');
});

Route::view('/cozinha', 'pages.cozinha')->middleware(['auth', 'role:admin,balcao,cozinha'])->name('cozinha');

Route::middleware('throttle:60,1')->group(function () {
    Route::view('/painel', 'pages.painel')->name('painel');
    Route::view('/acompanhar', 'pages.acompanhar')->name('acompanhar');
});
```

- [ ] **Step 4: Create the views**

`resources/views/components/layouts/app.blade.php`:

```blade
@props(['title' => 'Nana Comanda', 'bodyClass' => 'bg-stone-100'])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Nana Comanda</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans text-nana-ink antialiased {{ $bodyClass }}">
    {{ $slot }}
</body>
</html>
```

`resources/views/auth/login.blade.php`:

```blade
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
```

The page views start minimal; later tasks replace them. Create each one:

`resources/views/pages/estacao.blade.php`:

```blade
<x-layouts.app title="Estação"><h1 class="p-8 text-3xl font-black">Estação</h1></x-layouts.app>
```

`resources/views/pages/balcao.blade.php`:

```blade
<x-layouts.app title="Balcão"><h1 class="p-8 text-3xl font-black">Balcão</h1></x-layouts.app>
```

`resources/views/pages/cozinha.blade.php`:

```blade
<x-layouts.app title="Cozinha"><h1 class="p-8 text-3xl font-black">Cozinha</h1></x-layouts.app>
```

`resources/views/pages/painel.blade.php`:

```blade
<x-layouts.app title="Painel"><h1 class="p-8 text-3xl font-black">Painel</h1></x-layouts.app>
```

`resources/views/pages/acompanhar.blade.php`:

```blade
<x-layouts.app title="Acompanhe seu pedido"><h1 class="p-8 text-3xl font-black">Acompanhe seu pedido</h1></x-layouts.app>
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Auth`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: login por usuário, perfis, gates e rotas das telas" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Read queries (board, kitchen queue, today's orders)

**Files:**
- Create: `app/Queries/BoardQuery.php`, `app/Queries/KitchenQueue.php`, `app/Queries/TodayOrdersQuery.php`
- Test: `tests/Feature/Queries/QueriesTest.php`

**Interfaces:**
- Consumes: `Order::scopeToday()`, `Setting::get(Setting::READY_MINUTES)`, `OrderStatus`, `Destination`.
- Produces:
  - `BoardQuery::get(): array{preparing: list<array{id:int, number:string, first_name:string}>, ready: list<…same…>}`
    - preparing: oldest first;
    - ready: newest first, limited to the last N minutes.
  - `KitchenQueue::get(): Collection<Order>`. Today's preparing orders, oldest first, with `items` filtered to `destination = cozinha`.
  - `TodayOrdersQuery::search(string $term = ''): Collection<Order>`. All of today's orders, newest first, with `items`. An all-digits term matches `number`; any other term matches a name with `LIKE`. Capped at 200.

- [ ] **Step 1: Write the failing test** `tests/Feature/Queries/QueriesTest.php`:

```php
<?php

use App\Actions\Orders\TransitionOrder;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Models\Setting;
use App\Queries\BoardQuery;
use App\Queries\KitchenQueue;
use App\Queries\TodayOrdersQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
});

it('builds the public board with only number and first name', function () {
    $dish = kitchenProduct();
    $first = placeOrder([item($dish)], ['customer_name' => 'Maria Souza']);
    $second = placeOrder([item($dish)], ['customer_name' => 'João']);
    app(TransitionOrder::class)->markReady($second);

    $board = app(BoardQuery::class)->get();

    expect($board['preparing'])->toBe([['id' => $first->id, 'number' => '001', 'first_name' => 'Maria']])
        ->and($board['ready'])->toBe([['id' => $second->id, 'number' => '002', 'first_name' => 'João']]);
});

it('drops ready orders from the board after the configured minutes', function () {
    Setting::put(Setting::READY_MINUTES, 5);
    $order = placeOrder([item(kitchenProduct())]);
    app(TransitionOrder::class)->markReady($order);

    $this->travel(6)->minutes();

    expect(app(BoardQuery::class)->get()['ready'])->toBe([]);
});

it('lists only kitchen items of preparing orders, oldest first', function () {
    $dish = kitchenProduct(['name' => 'Filé de peixe']);
    $soda = cabineProduct(['name' => 'Guaraná']);
    $older = placeOrder([item($dish), item($soda)]);
    $newer = placeOrder([item($dish)]);
    $cabineOnly = placeOrder([item($soda)]);

    $queue = app(KitchenQueue::class)->get();

    expect($queue->pluck('id')->all())->toBe([$older->id, $newer->id])
        ->and($queue->first()->items->pluck('name_snapshot')->all())->toBe(['Filé de peixe'])
        ->and($queue->pluck('id'))->not->toContain($cabineOnly->id);
});

it('searches today orders by number or by name', function () {
    $dish = kitchenProduct();
    $maria = placeOrder([item($dish)], ['customer_name' => 'Maria']);
    $joao = placeOrder([item($dish)], ['customer_name' => 'João Pedro']);

    $query = app(TodayOrdersQuery::class);

    expect($query->search()->pluck('id')->all())->toBe([$joao->id, $maria->id])
        ->and($query->search('1')->pluck('id')->all())->toBe([$maria->id])
        ->and($query->search('pedro')->pluck('id')->all())->toBe([$joao->id]);
});

it('ignores orders from previous days', function () {
    placeOrder([item(kitchenProduct())]);
    $this->travelTo(CarbonImmutable::parse('2026-11-14 11:00', 'America/Sao_Paulo'));

    expect(app(KitchenQueue::class)->get())->toHaveCount(0)
        ->and(app(TodayOrdersQuery::class)->search())->toHaveCount(0);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Queries`
Expected: FAIL, `Class "App\Queries\BoardQuery" not found`.

- [ ] **Step 3: Implement**

`app/Queries/BoardQuery.php`:

```php
<?php

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;

final class BoardQuery
{
    /** @return array{preparing: list<array{id: int, number: string, first_name: string}>, ready: list<array{id: int, number: string, first_name: string}>} */
    public function get(): array
    {
        $minutes = (int) Setting::get(Setting::READY_MINUTES);
        $toEntry = fn (Order $order): array => [
            'id' => $order->id,
            'number' => $order->displayNumber(),
            'first_name' => $order->firstName(),
        ];

        $preparing = Order::query()->today()
            ->where('status', OrderStatus::Preparing->value)
            ->orderBy('id')
            ->get(['id', 'number', 'customer_name'])
            ->map($toEntry)->values()->all();

        $ready = Order::query()->today()
            ->where('status', OrderStatus::Ready->value)
            ->where('ready_at', '>=', now()->subMinutes($minutes))
            ->orderByDesc('ready_at')
            ->get(['id', 'number', 'customer_name'])
            ->map($toEntry)->values()->all();

        return ['preparing' => $preparing, 'ready' => $ready];
    }
}
```

`app/Queries/KitchenQueue.php`:

```php
<?php

namespace App\Queries;

use App\Enums\Destination;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

final class KitchenQueue
{
    /** @return Collection<int, Order> */
    public function get(): Collection
    {
        return Order::query()->today()
            ->where('status', OrderStatus::Preparing->value)
            ->with(['items' => fn ($query) => $query->where('destination', Destination::Cozinha->value)])
            ->whereHas('items', fn ($query) => $query->where('destination', Destination::Cozinha->value))
            ->orderBy('id')
            ->get();
    }
}
```

`app/Queries/TodayOrdersQuery.php`:

```php
<?php

namespace App\Queries;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

final class TodayOrdersQuery
{
    /** @return Collection<int, Order> */
    public function search(string $term = ''): Collection
    {
        $term = trim($term);

        return Order::query()->today()
            ->with('items')
            ->when($term !== '' && ctype_digit($term), fn ($query) => $query->where('number', (int) $term))
            ->when($term !== '' && ! ctype_digit($term), fn ($query) => $query->where('customer_name', 'like', "%{$term}%"))
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Queries`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: consultas do painel, fila da cozinha e pedidos do dia" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10: UI foundation (toasts, device unlock, sound, connection, network errors)

**Files:**
- Create: `resources/js/{device,toasts,connection,network}.js`
- Create: `resources/views/components/{toasts,device-gate,connection-dot}.blade.php`
- Modify: `resources/js/app.js`, `resources/views/components/layouts/app.blade.php`
- Test: `tests/Feature/Ui/ComponentsTest.php`

**Interfaces:**
- Produces:
  - **Alpine data:** `deviceGate(fullscreen: bool)`, `clock`, `toasts`.
  - **Alpine store:** `$store.conn.online`.
  - **Window events consumed:** `toast` (`detail: {type: 'success'|'error', message, timeout?, action?: {label, run}}`).
  - **Livewire event dispatched:** `realtime-reconnected`, after Echo reconnects.
  - **JS exports from `device.js`:** `beep()`, `chime()`.
  - **Blade components:** `<x-toasts />`, `<x-device-gate :fullscreen="bool" />`, `<x-connection-dot />`.

- [ ] **Step 1: Write the failing test** `tests/Feature/Ui/ComponentsTest.php`:

```php
<?php

it('renders the device gate button', function () {
    $this->blade('<x-device-gate />')
        ->assertSee('Toque para começar')
        ->assertSee('deviceGate(false)', false);

    $this->blade('<x-device-gate :fullscreen="true" />')->assertSee('deviceGate(true)', false);
});

it('renders the toast container and the connection dot', function () {
    $this->blade('<x-toasts />')->assertSee('x-data="toasts"', false);
    $this->blade('<x-connection-dot />')->assertSee('$store.conn.online', false);
});

it('includes toasts in the layout', function () {
    $this->get('/painel')->assertSee('x-data="toasts"', false);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Ui`
Expected: FAIL, `Unable to locate a class or view for component [device-gate]`.

- [ ] **Step 3: Create the Blade components**

`resources/views/components/toasts.blade.php`:

```blade
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
```

`resources/views/components/device-gate.blade.php`:

```blade
@props(['fullscreen' => false])
<div x-data="deviceGate({{ $fullscreen ? 'true' : 'false' }})" x-show="open" x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center bg-nana-ink/90">
    <button type="button" @click="start()"
            class="rounded-3xl bg-nana-yellow px-12 py-10 text-4xl font-black text-nana-ink shadow-2xl">
        Toque para começar
    </button>
</div>
```

`resources/views/components/connection-dot.blade.php`:

```blade
<span x-data class="inline-flex items-center gap-2 text-sm font-semibold">
    <span class="h-3 w-3 rounded-full" :class="$store.conn.online ? 'bg-emerald-500' : 'animate-pulse bg-red-600'"></span>
    <span x-text="$store.conn.online ? 'Online' : 'Sem conexão'"></span>
</span>
```

Add `<x-toasts />` to the layout. The `<body>` of `resources/views/components/layouts/app.blade.php` becomes:

```blade
<body class="min-h-screen font-sans text-nana-ink antialiased {{ $bodyClass }}">
    {{ $slot }}
    <x-toasts />
</body>
```

- [ ] **Step 4: Write the JS modules**

`resources/js/device.js`:

```js
let audioCtx = null;
let wakeLock = null;

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

export function beep() {
    tone(880, 0, 0.18);
    tone(880, 0.25, 0.18);
}

export function chime() {
    tone(660, 0, 0.35, 0.4);
    tone(880, 0.3, 0.35, 0.4);
    tone(1320, 0.6, 0.6, 0.4);
}

async function requestWakeLock() {
    try {
        if ('wakeLock' in navigator) {
            wakeLock = await navigator.wakeLock.request('screen');
        }
    } catch (e) {
        // sem suporte ou negado: segue sem travar a tela
    }
}

export function registerDevice(Alpine) {
    Alpine.data('deviceGate', (fullscreen = false) => ({
        open: true,
        init() {
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && !this.open) {
                    requestWakeLock();
                }
            });
            // Fully Kiosk / Chrome com autoplay liberado: o áudio já nasce destravado.
            if (audio().state === 'running') {
                requestWakeLock();
                this.open = false;
            }
        },
        async start() {
            await audio().resume();
            beep();
            await requestWakeLock();
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

`resources/js/toasts.js`:

```js
export function registerToasts(Alpine) {
    Alpine.data('toasts', () => ({
        items: [],
        push(detail) {
            const toast = {
                id: Date.now() + Math.random(),
                type: detail.type ?? 'success',
                message: detail.message,
                action: detail.action ?? null,
            };
            this.items.push(toast);
            setTimeout(() => this.dismiss(toast.id), detail.timeout ?? 4000);
        },
        dismiss(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    }));
}
```

`resources/js/connection.js`:

```js
export function registerConnection(Alpine) {
    Alpine.store('conn', { online: true });

    const pusher = window.Echo?.connector?.pusher;
    if (!pusher) {
        return;
    }

    let wasDown = false;
    pusher.connection.bind('state_change', ({ current }) => {
        const online = current === 'connected';
        Alpine.store('conn').online = online;
        if (online && wasDown) {
            window.Livewire?.dispatch('realtime-reconnected');
        }
        if (!online) {
            wasDown = true;
        }
    });
}
```

`resources/js/network.js` (Livewire request failure handling. If Livewire 4 renamed the `request` hook, use Boost `search-docs` "javascript hooks request fail" and keep the same behavior):

```js
export function registerNetworkErrors() {
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                preventDefault();
                if (status === 419) {
                    window.location.reload();
                    return;
                }
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'error', message: 'Sem conexão. Nada foi perdido: tente de novo.', timeout: 6000 },
                }));
            });
        });
    });
}
```

`resources/js/app.js` (replace the file; keep `./bootstrap`, which imports `./echo` after Task 5. If `install:broadcasting` put `import './echo';` in `app.js` instead, keep that line at the top):

```js
import './bootstrap';
import { registerDevice } from './device';
import { registerToasts } from './toasts';
import { registerConnection } from './connection';
import { registerNetworkErrors } from './network';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    registerDevice(Alpine);
    registerToasts(Alpine);
    registerConnection(Alpine);
});

registerNetworkErrors();
```

- [ ] **Step 5: Run the tests and the build**

Run: `./vendor/bin/pest tests/Feature/Ui && npm run build`
Expected: PASS, and Vite builds with no errors.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: base de UI (avisos, desbloqueio de som/tela, conexão, erros de rede)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 11: Counter order form (Livewire `Balcao\OrderForm`)

**Files:**
- Create: `app/Livewire/Balcao/OrderForm.php`, `resources/views/livewire/balcao/order-form.blade.php`, `resources/js/order-draft.js`
- Modify: `resources/js/app.js`, `resources/views/pages/balcao.blade.php`
- Test: `tests/Feature/Livewire/OrderFormTest.php`

**Interfaces:**
- Consumes: `PlaceOrder::handle()`, `UpdateOrderItems::handle()`, `Product` helpers, the gates `place-orders` and `manage-orders`.
- Produces:
  - Livewire component `balcao.order-form` with public methods:
    - `selectCategory(int)`, `addProduct(int)`, `addFirstSearchResult()`
    - `editDailyName(int)`, `saveDailyName()`, `closeDailyName()`
    - `toggleSoldOutMode()`, `toggleSoldOut(int)`
    - `increment(string $key)`, `decrement(string $key)`, `remove(string $key)`, `toggleChip(string $key, string $label)`
    - `submit()`, `loadOrder(int $orderId)` (listens to `edit-order`), `cancelEdit()`, `restoreDraft(array)`
  - Browser events dispatched: `toast`, `draft-clear`, `focus-name`.
  - Line shape: `{key, product_id, category_id, name, unit_price_cents, quantity, chips: list<string>, note}`.
  - Alpine data `orderDraft`, which saves the draft to `localStorage['nana.draft']` every second.

- [ ] **Step 1: Write the failing test** `tests/Feature/Livewire/OrderFormTest.php`:

```php
<?php

use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Balcao\OrderForm;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->user = userWithRole(Role::Balcao);
});

it('adds a product as a line with its current price', function () {
    $dish = kitchenProduct(['name' => 'Filé de frango', 'price_cents' => 2400]);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->assertSet('lines.0.name', 'Filé de frango')
        ->assertSet('lines.0.unit_price_cents', 2400)
        ->assertSee('R$ 24,00');
});

it('refuses a sold out product', function () {
    $dish = kitchenProduct();
    $dish->toggleSoldOut();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->assertHasErrors('lines')
        ->assertCount('lines', 0);
});

it('asks for the dish of the day before adding it', function () {
    $dish = kitchenProduct(['name' => 'Prato do dia', 'has_daily_name' => true, 'price_cents' => 2600]);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->assertSet('dailyNameProductId', $dish->id)
        ->assertCount('lines', 0)
        ->set('dailyNameInput', 'Strogonoff')
        ->call('saveDailyName')
        ->assertSet('dailyNameProductId', null)
        ->assertSet('lines.0.name', 'Prato do dia: Strogonoff');
});

it('composes chips and free text into notes and places the order', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);

    $component = Livewire::actingAs($this->user)->test(OrderForm::class)->call('addProduct', $dish->id);
    $key = $component->get('lines')[0]['key'];
    $firstUuid = $component->get('uuid');

    $component->call('toggleChip', $key, 'Sem arroz')
        ->call('toggleChip', $key, 'C/ Ovo')
        ->set('lines.0.note', 'bem passado')
        ->call('increment', $key)
        ->set('customerName', 'Maria')
        ->set('takeaway', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('lines', [])
        ->assertSet('customerName', '')
        ->assertDispatched('toast')
        ->assertDispatched('draft-clear');

    expect($component->get('uuid'))->not->toBe($firstUuid);

    $order = Order::query()->with('items')->sole();
    expect($order->number)->toBe(1)
        ->and($order->takeaway)->toBeTrue()
        ->and($order->total_cents)->toBe(4800)
        ->and($order->items->first()->quantity)->toBe(2)
        ->and($order->items->first()->notes)->toBe('Sem arroz, C/ Ovo, bem passado');
});

it('validates name and items before sending', function () {
    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('submit')
        ->assertHasErrors(['customerName', 'lines']);
});

it('loads an order for editing and saves the change', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $juice = kitchenProduct(['price_cents' => 500]);
    $order = placeOrder([item($dish, 1, 'Sem arroz, sem cebola')], ['customer_name' => 'João'], $this->user);

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->dispatch('edit-order', orderId: $order->id)
        ->assertSet('editingOrderId', $order->id)
        ->assertSet('customerName', 'João')
        ->call('addProduct', $juice->id)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('editingOrderId', null);

    expect($order->fresh()->items)->toHaveCount(2)
        ->and($order->fresh()->edited_at)->not->toBeNull()
        ->and(Order::query()->count())->toBe(1);
});

it('restores a draft re-pricing items from the database', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $uuid = (string) Str::uuid();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('restoreDraft', [
            'uuid' => $uuid,
            'customerName' => 'Ana',
            'takeaway' => true,
            'lines' => [['product_id' => $dish->id, 'quantity' => 2, 'unit_price_cents' => 1, 'chips' => ['Sem arroz'], 'note' => 'x']],
        ])
        ->assertSet('uuid', $uuid)
        ->assertSet('customerName', 'Ana')
        ->assertSet('lines.0.unit_price_cents', 2400)
        ->assertSet('lines.0.quantity', 2)
        ->assertSet('lines.0.chips', ['Sem arroz']);
});

it('lets the counter mark a product as sold out', function () {
    $dish = kitchenProduct();

    Livewire::actingAs($this->user)->test(OrderForm::class)
        ->call('toggleSoldOutMode')
        ->assertSet('soldOutMode', true)
        ->call('toggleSoldOut', $dish->id);

    expect($dish->fresh()->isSoldOut())->toBeTrue();
});

it('forbids the kitchen profile from placing orders', function () {
    $dish = kitchenProduct();

    Livewire::actingAs(userWithRole(Role::Cozinha))->test(OrderForm::class)
        ->call('addProduct', $dish->id)
        ->assertForbidden();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Livewire/OrderFormTest.php`
Expected: FAIL, `Class "App\Livewire\Balcao\OrderForm" not found`.

- [ ] **Step 3: Implement the component** `app/Livewire/Balcao/OrderForm.php`:

```php
<?php

namespace App\Livewire\Balcao;

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\UpdateOrderItems;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\QuickNote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderForm extends Component
{
    public string $uuid = '';

    public string $customerName = '';

    public bool $takeaway = false;

    /** @var list<array{key: string, product_id: int, category_id: int, name: string, unit_price_cents: int, quantity: int, chips: list<string>, note: string}> */
    public array $lines = [];

    public ?int $categoryId = null;

    public string $search = '';

    public bool $soldOutMode = false;

    public ?int $editingOrderId = null;

    public ?int $dailyNameProductId = null;

    public string $dailyNameInput = '';

    public function mount(): void
    {
        $this->uuid = (string) Str::uuid();
        $this->categoryId = Category::query()->where('active', true)->orderBy('position')->value('id');
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->where('active', true)->orderBy('position')->get();
    }

    #[Computed]
    public function products(): Collection
    {
        $term = trim($this->search);

        return Product::query()
            ->where('active', true)
            ->whereHas('category', fn ($query) => $query->where('active', true))
            ->when($term !== '', fn ($query) => $query->where('name', 'like', "%{$term}%"))
            ->when($term === '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function quickNotes(): Collection
    {
        return QuickNote::query()->orderBy('position')->get()->groupBy('category_id');
    }

    #[Computed]
    public function totalCents(): int
    {
        return (int) collect($this->lines)->sum(fn (array $line) => $line['unit_price_cents'] * $line['quantity']);
    }

    public function selectCategory(int $categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->search = '';
    }

    public function addProduct(int $productId): void
    {
        Gate::authorize('place-orders');
        $product = Product::query()->findOrFail($productId);

        if ($product->isSoldOut()) {
            $this->addError('lines', "{$product->name} está esgotado hoje.");

            return;
        }

        if ($product->needsDailyName()) {
            $this->openDailyName($product->id);

            return;
        }

        $this->lines[] = [
            'key' => (string) Str::ulid(),
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'name' => $product->displayName(),
            'unit_price_cents' => $product->price_cents,
            'quantity' => 1,
            'chips' => [],
            'note' => '',
        ];
        $this->search = '';
        $this->resetErrorBag('lines');
    }

    public function addFirstSearchResult(): void
    {
        $first = $this->products->first();

        if ($first !== null) {
            $this->addProduct($first->id);
        }
    }

    public function editDailyName(int $productId): void
    {
        Gate::authorize('place-orders');
        $this->openDailyName($productId);
    }

    public function saveDailyName(): void
    {
        Gate::authorize('place-orders');
        $this->validate(
            ['dailyNameInput' => ['required', 'string', 'max:60']],
            ['dailyNameInput.required' => 'Digite o prato de hoje.'],
        );

        $product = Product::query()->findOrFail($this->dailyNameProductId);
        $product->setDailyName($this->dailyNameInput);
        $this->closeDailyName();
        $this->addProduct($product->id);
    }

    public function closeDailyName(): void
    {
        $this->dailyNameProductId = null;
        $this->dailyNameInput = '';
    }

    public function toggleSoldOutMode(): void
    {
        Gate::authorize('manage-orders');
        $this->soldOutMode = ! $this->soldOutMode;
    }

    public function toggleSoldOut(int $productId): void
    {
        Gate::authorize('manage-orders');
        Product::query()->findOrFail($productId)->toggleSoldOut();
    }

    public function increment(string $key): void
    {
        $this->updateLine($key, fn (array $line) => [...$line, 'quantity' => min(20, $line['quantity'] + 1)]);
    }

    public function decrement(string $key): void
    {
        $this->updateLine($key, fn (array $line) => [...$line, 'quantity' => max(1, $line['quantity'] - 1)]);
    }

    public function remove(string $key): void
    {
        $this->lines = array_values(array_filter($this->lines, fn (array $line) => $line['key'] !== $key));
    }

    public function toggleChip(string $key, string $label): void
    {
        $this->updateLine($key, function (array $line) use ($label) {
            $line['chips'] = in_array($label, $line['chips'], true)
                ? array_values(array_diff($line['chips'], [$label]))
                : [...$line['chips'], $label];

            return $line;
        });
    }

    public function submit(): void
    {
        Gate::authorize('place-orders');
        $this->validate(
            ['customerName' => ['required', 'string', 'max:60'], 'lines' => ['required', 'array', 'min:1']],
            [
                'customerName.required' => 'Informe o nome do cliente.',
                'lines.required' => 'Adicione pelo menos um item.',
                'lines.min' => 'Adicione pelo menos um item.',
            ],
        );

        $payload = [
            'uuid' => $this->uuid,
            'customer_name' => trim($this->customerName),
            'takeaway' => $this->takeaway,
            'items' => array_map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'notes' => $this->composeNotes($line),
            ], $this->lines),
        ];

        if ($this->editingOrderId !== null) {
            Gate::authorize('manage-orders');
            $order = app(UpdateOrderItems::class)->handle(Order::query()->findOrFail($this->editingOrderId), $payload);
            $message = "Pedido {$order->displayNumber()} alterado!";
        } else {
            $order = app(PlaceOrder::class)->handle($payload, auth()->user());
            $message = "Pedido {$order->displayNumber()} enviado!";
        }

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('draft-clear');
        $this->dispatch('focus-name');
    }

    #[On('edit-order')]
    public function loadOrder(int $orderId): void
    {
        Gate::authorize('manage-orders');
        $order = Order::query()->with('items.product')->findOrFail($orderId);

        if ($order->status !== OrderStatus::Preparing) {
            $this->dispatch('toast', type: 'error', message: 'Só dá para alterar pedidos que ainda estão em preparo.');

            return;
        }

        $labels = QuickNote::query()->pluck('label')->all();

        $this->editingOrderId = $order->id;
        $this->uuid = $order->uuid;
        $this->customerName = $order->customer_name;
        $this->takeaway = $order->takeaway;
        $this->lines = $order->items->map(function ($item) use ($labels) {
            $parts = $item->notes ? array_map('trim', explode(',', $item->notes)) : [];

            return [
                'key' => (string) Str::ulid(),
                'product_id' => (int) $item->product_id,
                'category_id' => (int) ($item->product?->category_id ?? 0),
                'name' => $item->name_snapshot,
                'unit_price_cents' => $item->unit_price_cents,
                'quantity' => $item->quantity,
                'chips' => array_values(array_filter($parts, fn (string $part) => in_array($part, $labels, true))),
                'note' => implode(', ', array_filter($parts, fn (string $part) => ! in_array($part, $labels, true))),
            ];
        })->all();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    /** Restores the browser draft (localStorage). Prices and names always come from the database. */
    public function restoreDraft(array $draft): void
    {
        if ($this->editingOrderId !== null || $this->lines !== []) {
            return;
        }

        $lines = [];
        foreach (array_slice((array) ($draft['lines'] ?? []), 0, 30) as $line) {
            $product = Product::query()->find((int) ($line['product_id'] ?? 0));

            if ($product === null || ! $product->active) {
                continue;
            }

            $lines[] = [
                'key' => (string) Str::ulid(),
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'name' => $product->displayName(),
                'unit_price_cents' => $product->price_cents,
                'quantity' => max(1, min(20, (int) ($line['quantity'] ?? 1))),
                'chips' => array_values(array_filter((array) ($line['chips'] ?? []), 'is_string')),
                'note' => Str::limit((string) ($line['note'] ?? ''), 120, ''),
            ];
        }

        $this->lines = $lines;
        $this->customerName = Str::limit((string) ($draft['customerName'] ?? ''), 60, '');
        $this->takeaway = (bool) ($draft['takeaway'] ?? false);

        if (Str::isUuid((string) ($draft['uuid'] ?? ''))) {
            $this->uuid = (string) $draft['uuid'];
        }
    }

    public function render(): View
    {
        return view('livewire.balcao.order-form');
    }

    private function openDailyName(int $productId): void
    {
        $this->dailyNameProductId = $productId;
        $this->dailyNameInput = Product::query()->find($productId)?->dailyNameToday() ?? '';
    }

    private function updateLine(string $key, callable $change): void
    {
        $this->lines = array_map(fn (array $line) => $line['key'] === $key ? $change($line) : $line, $this->lines);
    }

    private function composeNotes(array $line): ?string
    {
        $parts = $line['chips'];

        if (trim($line['note']) !== '') {
            $parts[] = trim($line['note']);
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function resetForm(): void
    {
        $this->reset(['customerName', 'takeaway', 'lines', 'search', 'editingOrderId']);
        $this->uuid = (string) Str::uuid();
        $this->resetErrorBag();
    }
}
```

- [ ] **Step 4: Create the view** `resources/views/livewire/balcao/order-form.blade.php`:

```blade
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
```

- [ ] **Step 5: Add the draft persistence** in `resources/js/order-draft.js`:

```js
const KEY = 'nana.draft';

function read() {
    try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; }
}

function write(value) {
    try { localStorage.setItem(KEY, JSON.stringify(value)); } catch (e) { /* armazenamento indisponível */ }
}

function clear() {
    try { localStorage.removeItem(KEY); } catch (e) { /* armazenamento indisponível */ }
}

export function registerOrderDraft(Alpine) {
    Alpine.data('orderDraft', () => ({
        timer: null,
        init() {
            const draft = read();
            if (draft) {
                this.$wire.restoreDraft(draft);
            }
            window.addEventListener('draft-clear', clear);
            this.timer = setInterval(() => this.persist(), 1000);
        },
        destroy() {
            clearInterval(this.timer);
            window.removeEventListener('draft-clear', clear);
        },
        persist() {
            if (this.$wire.editingOrderId) {
                return;
            }
            const lines = this.$wire.lines || [];
            const customerName = this.$wire.customerName || '';
            if (!customerName && lines.length === 0) {
                clear();
                return;
            }
            write({ uuid: this.$wire.uuid, customerName, takeaway: this.$wire.takeaway, lines });
        },
    }));
}
```

In `resources/js/app.js`, add `import { registerOrderDraft } from './order-draft';` and call `registerOrderDraft(Alpine);` inside the `alpine:init` listener.

- [ ] **Step 6: Put the form on the counter page (temporary).** Task 14 swaps this for the full counter pane. Replace `resources/views/pages/balcao.blade.php` with:

```blade
<x-layouts.app title="Balcão" body-class="bg-stone-100 overflow-hidden">
    <div class="h-screen p-4"><livewire:balcao.order-form /></div>
</x-layouts.app>
```

- [ ] **Step 7: Run the tests and the build**

Run: `./vendor/bin/pest tests/Feature/Livewire/OrderFormTest.php && npm run build`
Expected: PASS, and the build succeeds.

- [ ] **Step 8: Check it manually.** Start `php artisan reverb:start` and `npm run dev` in two terminals, then open `http://nana-comanda.test/balcao` as `balcao`.
  - Add the Prato do dia (you are asked for today's name).
  - Add a juice, toggle chips, press F5 mid-order (the draft comes back) and send.
  - Expected: the toast "Pedido 001 enviado!" appears and the form clears.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: formulário de pedido do balcão com rascunho, prato do dia e esgotado" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 12: Counter lists — "Na cozinha" and "Pedidos de hoje"

**Files:**
- Create: `app/Livewire/Balcao/InKitchenList.php`, `app/Livewire/Balcao/TodayOrders.php`
- Create: `resources/views/livewire/balcao/in-kitchen-list.blade.php`, `resources/views/livewire/balcao/today-orders.blade.php`
- Test: `tests/Feature/Livewire/CounterListsTest.php`

**Interfaces:**
- Consumes: `KitchenQueue`, `TodayOrdersQuery`, `TransitionOrder`, the gates `mark-ready` and `manage-orders`.
- Produces:
  - `balcao.in-kitchen-list`: `markReady(int)`.
  - `balcao.today-orders`: `markReady(int)`, `markDelivered(int)`, `backToPreparing(int)`, `edit(int)` (dispatches `edit-order` with `orderId`), `startCancel(int)`, `confirmCancel()`, `closeCancel()`. Public constant `CANCEL_REASONS`.
  - Both refresh on `echo-private:pedidos,OrdersChanged`, on `realtime-reconnected` and with `wire:poll.20s`.

- [ ] **Step 1: Write the failing test** `tests/Feature/Livewire/CounterListsTest.php`:

```php
<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Balcao\InKitchenList;
use App\Livewire\Balcao\TodayOrders;
use App\Actions\Orders\TransitionOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->user = userWithRole(Role::Balcao);
    $this->dish = kitchenProduct();
});

it('lists orders in the kitchen and marks them ready', function () {
    $order = placeOrder([item($this->dish)], ['customer_name' => 'Carla']);

    Livewire::actingAs($this->user)->test(InKitchenList::class)
        ->assertSee('001')->assertSee('Carla')
        ->call('markReady', $order->id)
        ->assertDispatched('toast')
        ->assertDontSee('Carla');

    expect($order->fresh()->status)->toBe(OrderStatus::Ready);
});

it('shows today orders with totals and searches them', function () {
    placeOrder([item($this->dish)], ['customer_name' => 'Carla']);
    placeOrder([item($this->dish)], ['customer_name' => 'Bruno']);

    Livewire::actingAs($this->user)->test(TodayOrders::class)
        ->assertSee('Carla')->assertSee('Bruno')
        ->set('search', 'brun')
        ->assertSee('Bruno')->assertDontSee('Carla')
        ->set('search', '1')
        ->assertSee('Carla')->assertDontSee('Bruno');
});

it('delivers and sends back to preparing', function () {
    $order = placeOrder([item($this->dish)]);
    app(TransitionOrder::class)->markReady($order);

    Livewire::actingAs($this->user)->test(TodayOrders::class)->call('backToPreparing', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    app(TransitionOrder::class)->markReady($order);
    Livewire::actingAs($this->user)->test(TodayOrders::class)->call('markDelivered', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('asks for the edit through an event', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs($this->user)->test(TodayOrders::class)
        ->call('edit', $order->id)
        ->assertDispatched('edit-order', orderId: $order->id);
});

it('cancels with a chosen reason and requires text for Outro', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs($this->user)->test(TodayOrders::class)
        ->call('startCancel', $order->id)
        ->call('confirmCancel')->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Outro')
        ->call('confirmCancel')->assertHasErrors('cancelReasonOther')
        ->set('cancelReasonOther', 'cliente pediu pra trocar')
        ->call('confirmCancel')->assertHasNoErrors()
        ->assertSet('cancelingOrderId', null);

    expect($order->fresh()->status)->toBe(OrderStatus::Canceled)
        ->and($order->fresh()->cancel_reason)->toBe('Outro: cliente pediu pra trocar');
});

it('forbids the kitchen profile from cancelling', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs(userWithRole(Role::Cozinha))->test(TodayOrders::class)
        ->call('startCancel', $order->id)
        ->assertForbidden();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Livewire/CounterListsTest.php`
Expected: FAIL, `Class "App\Livewire\Balcao\InKitchenList" not found`.

- [ ] **Step 3: Implement the components**

`app/Livewire/Balcao/InKitchenList.php`:

```php
<?php

namespace App\Livewire\Balcao;

use App\Actions\Orders\TransitionOrder;
use App\Models\Order;
use App\Queries\KitchenQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class InKitchenList extends Component
{
    #[Computed]
    public function orders(): Collection
    {
        return app(KitchenQueue::class)->get();
    }

    public function markReady(int $orderId): void
    {
        Gate::authorize('mark-ready');
        $order = app(TransitionOrder::class)->markReady(Order::query()->findOrFail($orderId));
        $this->dispatch('toast', type: 'success', message: "Pedido {$order->displayNumber()} pronto, chamando na TV.");
    }

    #[On('echo-private:pedidos,OrdersChanged')]
    public function onOrdersChanged(): void {}

    #[On('realtime-reconnected')]
    public function onReconnected(): void {}

    public function render(): View
    {
        return view('livewire.balcao.in-kitchen-list');
    }
}
```

`app/Livewire/Balcao/TodayOrders.php`:

```php
<?php

namespace App\Livewire\Balcao;

use App\Actions\Orders\TransitionOrder;
use App\Models\Order;
use App\Queries\TodayOrdersQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class TodayOrders extends Component
{
    public const CANCEL_REASONS = ['Cliente desistiu', 'Lançado errado', 'Outro'];

    public string $search = '';

    public ?int $cancelingOrderId = null;

    public string $cancelReason = '';

    public string $cancelReasonOther = '';

    #[Computed]
    public function orders(): Collection
    {
        return app(TodayOrdersQuery::class)->search($this->search);
    }

    public function markReady(int $orderId): void
    {
        Gate::authorize('mark-ready');
        app(TransitionOrder::class)->markReady(Order::query()->findOrFail($orderId));
    }

    public function markDelivered(int $orderId): void
    {
        Gate::authorize('manage-orders');
        app(TransitionOrder::class)->markDelivered(Order::query()->findOrFail($orderId));
    }

    public function backToPreparing(int $orderId): void
    {
        Gate::authorize('manage-orders');
        app(TransitionOrder::class)->undoReady(Order::query()->findOrFail($orderId), auth()->user());
    }

    public function edit(int $orderId): void
    {
        Gate::authorize('manage-orders');
        $this->dispatch('edit-order', orderId: $orderId);
    }

    public function startCancel(int $orderId): void
    {
        Gate::authorize('manage-orders');
        $this->cancelingOrderId = $orderId;
        $this->cancelReason = '';
        $this->cancelReasonOther = '';
        $this->resetErrorBag();
    }

    public function closeCancel(): void
    {
        $this->cancelingOrderId = null;
    }

    public function confirmCancel(): void
    {
        Gate::authorize('manage-orders');
        $this->validate(
            [
                'cancelReason' => ['required', Rule::in(self::CANCEL_REASONS)],
                'cancelReasonOther' => ['nullable', 'required_if:cancelReason,Outro', 'string', 'max:100'],
            ],
            ['cancelReason.required' => 'Escolha o motivo.', 'cancelReasonOther.required_if' => 'Descreva o motivo.'],
        );

        $reason = $this->cancelReason === 'Outro' ? 'Outro: '.trim($this->cancelReasonOther) : $this->cancelReason;
        $order = app(TransitionOrder::class)->cancel(Order::query()->findOrFail($this->cancelingOrderId), $reason);

        $this->cancelingOrderId = null;
        $this->dispatch('toast', type: 'success', message: "Pedido {$order->displayNumber()} cancelado.");
    }

    #[On('echo-private:pedidos,OrdersChanged')]
    public function onOrdersChanged(): void {}

    #[On('realtime-reconnected')]
    public function onReconnected(): void {}

    public function render(): View
    {
        return view('livewire.balcao.today-orders');
    }
}
```

- [ ] **Step 4: Create the views**

`resources/views/livewire/balcao/in-kitchen-list.blade.php`:

```blade
<div wire:poll.20s class="flex h-full flex-col rounded-2xl bg-white p-3 shadow">
    <h3 class="mb-2 text-lg font-black uppercase tracking-wide text-stone-500">Na cozinha ({{ $this->orders->count() }})</h3>
    <ul class="min-h-0 flex-1 space-y-2 overflow-y-auto">
        @forelse ($this->orders as $order)
            <li wire:key="ik-{{ $order->id }}" class="flex items-center gap-2 rounded-xl bg-stone-50 p-2">
                <div class="min-w-0 flex-1">
                    <p class="text-xl font-black tabular-nums">{{ $order->displayNumber() }}</p>
                    <p class="truncate font-semibold text-stone-600">{{ $order->customer_name }}</p>
                </div>
                <button type="button" wire:click="markReady({{ $order->id }})"
                        class="rounded-xl bg-emerald-600 px-4 py-3 text-lg font-black text-white">Pronto</button>
            </li>
        @empty
            <li class="py-8 text-center text-stone-400">Nada na cozinha.</li>
        @endforelse
    </ul>
</div>
```

`resources/views/livewire/balcao/today-orders.blade.php`:

```blade
@use('App\Enums\OrderStatus')
@use('App\Support\Money')
<div wire:poll.20s class="flex h-full min-h-0 flex-col gap-3">
    <input type="search" wire:model.live.debounce.200ms="search" placeholder="Buscar por número ou nome…"
           class="w-full rounded-xl border border-stone-300 px-4 py-3 text-lg">

    <div class="min-h-0 flex-1 overflow-y-auto rounded-2xl bg-white shadow">
        <table class="w-full text-left">
            <thead class="sticky top-0 bg-stone-50 text-sm uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Nº</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Hora</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    <tr wire:key="today-{{ $order->id }}" class="border-t border-stone-100 text-lg">
                        <td class="px-4 py-3 font-black tabular-nums">{{ $order->displayNumber() }}</td>
                        <td class="px-4 py-3 font-semibold">
                            {{ $order->customer_name }}
                            @if ($order->takeaway) <span class="ml-1 rounded bg-nana-yellow px-2 text-sm font-bold">VIAGEM</span> @endif
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-3 py-1 text-sm font-bold',
                                'bg-amber-100 text-amber-800' => $order->status === OrderStatus::Preparing,
                                'bg-emerald-100 text-emerald-800' => $order->status === OrderStatus::Ready,
                                'bg-stone-100 text-stone-600' => $order->status === OrderStatus::Delivered,
                                'bg-red-100 text-red-700' => $order->status === OrderStatus::Canceled,
                            ])>{{ $order->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 tabular-nums text-stone-500">{{ $order->created_at->format('H:i') }}</td>
                        <td class="px-4 py-3 text-right font-black tabular-nums">{{ Money::format($order->total_cents) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                @if ($order->status === OrderStatus::Preparing)
                                    <button type="button" wire:click="markReady({{ $order->id }})" class="rounded-lg bg-emerald-600 px-3 py-2 font-bold text-white">Pronto</button>
                                    <button type="button" wire:click="edit({{ $order->id }})" class="rounded-lg bg-stone-200 px-3 py-2 font-bold">Editar</button>
                                @elseif ($order->status === OrderStatus::Ready)
                                    <button type="button" wire:click="markDelivered({{ $order->id }})" class="rounded-lg bg-nana-ink px-3 py-2 font-bold text-white">Entregue</button>
                                    <button type="button" wire:click="backToPreparing({{ $order->id }})" class="rounded-lg bg-stone-200 px-3 py-2 font-bold">Voltar p/ preparo</button>
                                @endif
                                @if (in_array($order->status, [OrderStatus::Preparing, OrderStatus::Ready], true))
                                    <button type="button" wire:click="startCancel({{ $order->id }})" class="rounded-lg px-3 py-2 font-bold text-red-700">Cancelar</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-stone-400">Nenhum pedido hoje.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($cancelingOrderId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50">
            <div class="w-[28rem] rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-2xl font-black">Cancelar pedido</h3>
                <div class="mt-4 space-y-2">
                    @foreach (\App\Livewire\Balcao\TodayOrders::CANCEL_REASONS as $reason)
                        <label class="flex items-center gap-3 text-lg">
                            <input type="radio" wire:model.live="cancelReason" value="{{ $reason }}" class="h-5 w-5"> {{ $reason }}
                        </label>
                    @endforeach
                    @if ($cancelReason === 'Outro')
                        <input type="text" wire:model="cancelReasonOther" maxlength="100" placeholder="Qual o motivo?"
                               class="w-full rounded-xl border border-stone-300 px-4 py-3">
                    @endif
                    @error('cancelReason') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    @error('cancelReasonOther') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="closeCancel" class="rounded-xl px-4 py-3 font-semibold">Voltar</button>
                    <button type="button" wire:click="confirmCancel" class="rounded-xl bg-red-700 px-6 py-3 font-bold text-white">Cancelar pedido</button>
                </div>
            </div>
        </div>
    @endif
</div>
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Livewire/CounterListsTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: listas do balcão (na cozinha e pedidos de hoje) com cancelamento" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 13: Kitchen board with touch and numeric keypad (`Cozinha\Board`)

**Files:**
- Create: `app/Livewire/Cozinha/Board.php`, `resources/views/livewire/cozinha/board.blade.php`, `resources/js/kitchen.js`
- Modify: `resources/js/app.js`, `resources/views/pages/cozinha.blade.php`
- Test: `tests/Feature/Livewire/KitchenBoardTest.php`

**Interfaces:**
- Consumes: `KitchenQueue`, `TransitionOrder`, the gate `mark-ready`, `beep()` from `device.js`.
- Produces:
  - Livewire component `cozinha.board`:
    - `markReady(int)`
    - `markReadyByNumber(int $number): ?int`. Returns the order id, or dispatches `kitchen-not-found` with `number`.
    - `undoReady(int)`
    - `onOrdersChanged(array $payload)`, which dispatches `kitchen-beep` for kinds `created` and `updated`.
  - Alpine data `kitchenBoard`:
    - captures `Numpad*` keys on `window` in the capture phase;
    - keys: digits, `NumpadEnter`, `NumpadAdd`, `NumpadDecimal`, `NumpadSubtract`;
    - shows a 10 s undo bar inside the board.
  - Kitchen buttons use `@pointerdown.prevent @mousedown.prevent tabindex="-1"`, so they never steal focus (validated in Task 2).

- [ ] **Step 1: Write the failing test** `tests/Feature/Livewire/KitchenBoardTest.php`:

```php
<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Cozinha\Board;
use App\Actions\Orders\UpdateOrderItems;
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

it('beeps for new and edited orders only', function () {
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->call('onOrdersChanged', ['orderId' => 1, 'kind' => 'created'])->assertDispatched('kitchen-beep');

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->call('onOrdersChanged', ['orderId' => 1, 'kind' => 'ready'])->assertNotDispatched('kitchen-beep');
});

it('refuses guests', function () {
    $order = placeOrder([item(kitchenProduct())]);

    Livewire::test(Board::class)->call('markReady', $order->id)->assertForbidden();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Livewire/KitchenBoardTest.php`
Expected: FAIL, `Class "App\Livewire\Cozinha\Board" not found`.

- [ ] **Step 3: Implement the component** `app/Livewire/Cozinha/Board.php`:

```php
<?php

namespace App\Livewire\Cozinha;

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Queries\KitchenQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Board extends Component
{
    #[Computed]
    public function orders(): Collection
    {
        return app(KitchenQueue::class)->get();
    }

    public function markReady(int $orderId): void
    {
        Gate::authorize('mark-ready');
        app(TransitionOrder::class)->markReady(Order::query()->findOrFail($orderId));
    }

    public function markReadyByNumber(int $number): ?int
    {
        Gate::authorize('mark-ready');

        $order = Order::query()->today()
            ->where('number', $number)
            ->where('status', OrderStatus::Preparing->value)
            ->first();

        if ($order === null) {
            $this->dispatch('kitchen-not-found', number: str_pad((string) $number, 3, '0', STR_PAD_LEFT));

            return null;
        }

        app(TransitionOrder::class)->markReady($order);

        return $order->id;
    }

    public function undoReady(int $orderId): void
    {
        Gate::authorize('mark-ready');
        app(TransitionOrder::class)->undoReady(Order::query()->findOrFail($orderId), auth()->user());
    }

    #[On('echo-private:pedidos,OrdersChanged')]
    public function onOrdersChanged(array $payload = []): void
    {
        if (in_array($payload['kind'] ?? null, ['created', 'updated'], true)) {
            $this->dispatch('kitchen-beep');
        }
    }

    #[On('realtime-reconnected')]
    public function onReconnected(): void {}

    public function render(): View
    {
        return view('livewire.cozinha.board');
    }
}
```

- [ ] **Step 4: Create the view** `resources/views/livewire/cozinha/board.blade.php`:

```blade
<div x-data="kitchenBoard" @kitchen-beep.window="beep()" @kitchen-not-found.window="notFound($event.detail.number)"
     wire:poll.20s class="relative flex h-full flex-col bg-stone-900 text-white">
    <header class="flex items-center justify-between px-6 py-3">
        <h2 class="text-3xl font-black tracking-wide">
            COZINHA <span class="ml-3 rounded-lg bg-white/10 px-3 py-1 text-2xl">{{ $this->orders->count() }}</span>
        </h2>
        <div class="flex items-center gap-6">
            <span x-show="typed" x-cloak class="rounded-lg bg-nana-yellow px-3 py-1 text-2xl font-black text-nana-ink" x-text="'Nº ' + typed"></span>
            <x-connection-dot />
            <span x-data="clock" x-text="time" class="text-2xl font-bold tabular-nums"></span>
        </div>
    </header>

    <div class="grid min-h-0 flex-1 auto-rows-min grid-cols-2 gap-4 overflow-y-auto p-4 2xl:grid-cols-3">
        @forelse ($this->orders as $index => $order)
            @php($minutes = (int) floor(abs($order->created_at->diffInMinutes(now()))))
            <article wire:key="k-{{ $order->id }}" data-order-id="{{ $order->id }}" data-number="{{ $order->displayNumber() }}"
                     :class="selected === {{ $index }} ? 'ring-8 ring-nana-yellow' : ''"
                     class="flex flex-col overflow-hidden rounded-2xl bg-white text-nana-ink">
                <div @class([
                    'flex items-center justify-between px-4 py-2 text-white',
                    'bg-emerald-600' => $minutes < 10,
                    'bg-amber-500' => $minutes >= 10 && $minutes < 20,
                    'bg-red-600' => $minutes >= 20,
                ])>
                    <span class="text-4xl font-black tabular-nums">{{ $order->displayNumber() }}</span>
                    <span class="text-2xl font-bold">{{ $minutes }} min</span>
                </div>
                <div class="flex-1 px-4 py-3">
                    <p class="truncate text-3xl font-black">{{ $order->customer_name }}</p>
                    <div class="mt-1 flex gap-2">
                        @if ($order->takeaway) <span class="rounded-md bg-nana-yellow px-2 py-1 text-lg font-black">VIAGEM</span> @endif
                        @if ($order->edited_at) <span class="animate-pulse rounded-md bg-red-600 px-2 py-1 text-lg font-black text-white">ALTERADO</span> @endif
                    </div>
                    <ul class="mt-3 space-y-2">
                        @foreach ($order->items as $item)
                            <li class="text-2xl leading-snug">
                                <span class="font-black">{{ $item->quantity }}×</span> {{ $item->name_snapshot }}
                                @if ($item->notes) <span class="block text-xl font-bold uppercase text-red-700">{{ $item->notes }}</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" tabindex="-1" @pointerdown.prevent @mousedown.prevent
                        @click="ready({{ $order->id }}, '{{ $order->displayNumber() }}')"
                        class="bg-nana-red py-6 text-3xl font-black text-white active:bg-red-800">PRONTO</button>
            </article>
        @empty
            <p class="col-span-full py-24 text-center text-3xl font-bold text-white/40">Nenhum pedido na fila.</p>
        @endforelse
    </div>

    <div x-show="undoBar" x-cloak class="absolute inset-x-4 bottom-4 flex items-center justify-between rounded-2xl bg-emerald-600 px-6 py-4 text-2xl font-black shadow-2xl">
        <span x-text="undoBar"></span>
        <button type="button" tabindex="-1" @pointerdown.prevent @mousedown.prevent @click="undo()"
                class="rounded-xl bg-white/20 px-5 py-2">Desfazer (−)</button>
    </div>
</div>
```

- [ ] **Step 5: Write the keypad and undo logic** in `resources/js/kitchen.js`:

```js
import { beep } from './device';

const UNDO_MS = 10000;

export function registerKitchen(Alpine) {
    Alpine.data('kitchenBoard', () => ({
        selected: 0,
        typed: '',
        lastReady: null,
        undoBar: '',
        undoTimer: null,
        keyHandler: null,
        init() {
            this.keyHandler = (event) => this.onKey(event);
            window.addEventListener('keydown', this.keyHandler, { capture: true });
        },
        destroy() {
            window.removeEventListener('keydown', this.keyHandler, { capture: true });
        },
        cards() {
            return [...this.$root.querySelectorAll('[data-order-id]')];
        },
        beep() {
            beep();
        },
        notFound(number) {
            this.undoBar = '';
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: `Pedido ${number} não está na fila.` } }));
        },
        onKey(event) {
            if (!event.code || !event.code.startsWith('Numpad')) {
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
    }));
}
```

In `resources/js/app.js`, add `import { registerKitchen } from './kitchen';` and call `registerKitchen(Alpine);` inside the `alpine:init` listener.

- [ ] **Step 6: Build the kitchen page.** Replace `resources/views/pages/cozinha.blade.php` with:

```blade
<x-layouts.app title="Cozinha" body-class="bg-stone-900 overflow-hidden">
    <x-device-gate />
    <div class="h-screen"><livewire:cozinha.board /></div>
</x-layouts.app>
```

- [ ] **Step 7: Run the tests and the build**

Run: `./vendor/bin/pest tests/Feature/Livewire/KitchenBoardTest.php && npm run build`
Expected: PASS, and the build succeeds.

- [ ] **Step 8: Check it manually.** With `reverb:start` and `npm run dev` running, open `/balcao` and `/cozinha` in two windows. Send an order.
  - Expected: it appears in the kitchen instantly, with a beep after "Toque para começar".
  - Mark it with Enter and with digits + Enter on a numeric keypad. Undo with `−`.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: tela da cozinha com toque, teclado numérico, bip e desfazer" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 14: Two-monitor station (`/estacao`) and counter pane

**Files:**
- Create: `resources/views/components/balcao-pane.blade.php`
- Modify: `resources/views/pages/estacao.blade.php`, `resources/views/pages/balcao.blade.php`
- Test: `tests/Feature/Pages/StationTest.php`

**Interfaces:**
- Consumes: the components `balcao.order-form`, `balcao.today-orders`, `balcao.in-kitchen-list`, `cozinha.board`; `StationController` (`$leftWidth`, `$rightWidth`).
- Produces: `<x-balcao-pane />`, which holds the tabs "Novo pedido" / "Pedidos de hoje" and the always-visible "Na cozinha" column. It switches to "Novo pedido" on the `edit-order` window event.

- [ ] **Step 1: Write the failing test** `tests/Feature/Pages/StationTest.php`:

```php
<?php

use App\Enums\Role;
use App\Livewire\Balcao\InKitchenList;
use App\Livewire\Balcao\OrderForm;
use App\Livewire\Balcao\TodayOrders;
use App\Livewire\Cozinha\Board;
use App\Models\Setting;

it('renders counter and kitchen side by side with the configured widths', function () {
    Setting::put(Setting::STATION_LEFT_WIDTH, 1920);
    Setting::put(Setting::STATION_RIGHT_WIDTH, 1366);

    $this->actingAs(userWithRole(Role::Balcao))->get('/estacao')
        ->assertOk()
        ->assertSee('grid-template-columns: 1920px 1366px', false)
        ->assertSeeLivewire(OrderForm::class)
        ->assertSeeLivewire(TodayOrders::class)
        ->assertSeeLivewire(InKitchenList::class)
        ->assertSeeLivewire(Board::class)
        ->assertSee('Toque para começar');
});

it('renders the counter alone on /balcao', function () {
    $this->actingAs(userWithRole(Role::Balcao))->get('/balcao')
        ->assertOk()
        ->assertSeeLivewire(OrderForm::class)
        ->assertDontSeeLivewire(Board::class);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Pages/StationTest.php`
Expected: FAIL, because the page doesn't contain `grid-template-columns`.

- [ ] **Step 3: Create the counter pane** `resources/views/components/balcao-pane.blade.php`:

```blade
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
```

- [ ] **Step 4: Build the pages**

`resources/views/pages/estacao.blade.php`:

```blade
<x-layouts.app title="Estação" body-class="bg-stone-100 overflow-hidden">
    <x-device-gate />
    <div class="grid h-screen" style="grid-template-columns: {{ $leftWidth }}px {{ $rightWidth }}px;">
        <x-balcao-pane />
        <div class="h-screen border-l-4 border-nana-ink">
            <livewire:cozinha.board />
        </div>
    </div>
</x-layouts.app>
```

`resources/views/pages/balcao.blade.php`:

```blade
<x-layouts.app title="Balcão" body-class="bg-stone-100 overflow-hidden">
    <x-balcao-pane />
</x-layouts.app>
```

- [ ] **Step 5: Run the full suite**

Run: `./vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 6: Check it manually** with the Chrome command from Task 2, pointed at `http://nana-comanda.test/estacao`:
  - type a name while tapping PRONTO on the kitchen side: the typing is not interrupted;
  - numeric keypad digits never land in the name field;
  - "Editar" in "Pedidos de hoje" jumps to the form with the order loaded.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: estação em dois monitores (balcão + cozinha numa janela)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 15: Public board — TV and phone (`Painel\PublicBoard`)

**Files:**
- Create: `app/Support/QrCode.php`, `app/Livewire/Painel/PublicBoard.php`, `resources/views/livewire/painel/{tv,mobile}.blade.php`, `resources/js/announcer.js`
- Modify: `resources/js/app.js`, `resources/views/pages/painel.blade.php`, `resources/views/pages/acompanhar.blade.php`
- Test: `tests/Feature/Livewire/PublicBoardTest.php`, `tests/Feature/Support/QrCodeTest.php`

**Interfaces:**
- Consumes: `BoardQuery::get()`, the `BoardChanged` payload (`orderId`, `kind`, `number`, `firstName`, `status`).
- Produces:
  - `QrCode::dataUri(string $url): string`, an SVG data URI.
  - Livewire component `painel.public-board` with a locked prop `variant` (`tv` | `mobile`):
    - on kind `ready` it dispatches `announce` (`orderId`, `number`, `name`);
    - on kind `unready` it dispatches `announce-cancel` (`orderId`).
  - Alpine data:
    - `announcer(durationMs = 8000)`: a queue of full-screen announcements with a chime;
    - `trackMine`: the customer's own number in `localStorage['nana.mine']`, with vibration.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Support/QrCodeTest.php`:

```php
<?php

use App\Support\QrCode;

it('renders an SVG data URI', function () {
    expect(QrCode::dataUri('https://comanda.mswebsolutions.site/acompanhar'))->toStartWith('data:image/svg+xml;base64,');
});
```

`tests/Feature/Livewire/PublicBoardTest.php`:

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
        ->assertSee('Preparando')->assertSee('Prontos')
        ->assertSee('001')->assertSee('Maria')
        ->assertSee('002')->assertSee('João')
        ->assertDontSee('Souza')->assertDontSee('Silva')->assertDontSee('Filé mignon')
        ->assertSee('data:image/svg+xml;base64,', false);
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

it('serves both public pages to guests', function () {
    $this->get('/painel')->assertOk()->assertSeeLivewire(PublicBoard::class);
    $this->get('/acompanhar')->assertOk()->assertSeeLivewire(PublicBoard::class);
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Livewire/PublicBoardTest.php tests/Feature/Support`
Expected: FAIL, `Class "App\Support\QrCode" not found`.

- [ ] **Step 3: Implement the QR helper and the component**

`app/Support/QrCode.php`:

```php
<?php

namespace App\Support;

use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

final class QrCode
{
    public static function dataUri(string $url): string
    {
        return (new Generator(new QROptions(['outputBase64' => true])))->render($url);
    }
}
```

`app/Livewire/Painel/PublicBoard.php`:

```php
<?php

namespace App\Livewire\Painel;

use App\Queries\BoardQuery;
use App\Support\QrCode;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class PublicBoard extends Component
{
    #[Locked]
    public string $variant = 'tv';

    #[Computed]
    public function board(): array
    {
        return app(BoardQuery::class)->get();
    }

    #[On('echo:painel,BoardChanged')]
    public function onBoardChanged(array $payload = []): void
    {
        $kind = $payload['kind'] ?? null;

        if ($kind === 'ready') {
            $this->dispatch('announce', orderId: (int) $payload['orderId'], number: (string) $payload['number'], name: (string) $payload['firstName']);
        }

        if ($kind === 'unready') {
            $this->dispatch('announce-cancel', orderId: (int) $payload['orderId']);
        }
    }

    #[On('realtime-reconnected')]
    public function onReconnected(): void {}

    public function render(): View
    {
        if ($this->variant === 'mobile') {
            return view('livewire.painel.mobile');
        }

        return view('livewire.painel.tv', ['qr' => QrCode::dataUri(route('acompanhar'))]);
    }
}
```

- [ ] **Step 4: Create the views**

`resources/views/livewire/painel/tv.blade.php`:

```blade
<div x-data="announcer(8000)" @announce.window="enqueue($event.detail)" @announce-cancel.window="cancel($event.detail.orderId)"
     wire:poll.20s class="relative grid h-screen grid-cols-3 overflow-hidden bg-white">
    <section class="col-span-2 p-8">
        <h1 class="text-center text-6xl font-black">Preparando</h1>
        <div class="mt-8 grid grid-cols-3 gap-6">
            @foreach ($this->board['preparing'] as $entry)
                <div wire:key="p-{{ $entry['id'] }}" class="rounded-2xl bg-stone-100 px-4 py-5 text-center">
                    <p class="text-6xl font-black tabular-nums">{{ $entry['number'] }}</p>
                    <p class="mt-1 truncate text-3xl font-bold text-stone-600">{{ $entry['first_name'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="flex flex-col bg-nana-red p-8 text-white">
        <h1 class="text-center text-6xl font-black">Prontos</h1>
        <div class="mt-8 flex-1 space-y-4 overflow-hidden">
            @foreach ($this->board['ready'] as $entry)
                <div wire:key="r-{{ $entry['id'] }}" class="rounded-2xl bg-white/15 px-4 py-4 text-center">
                    <p class="text-6xl font-black tabular-nums">{{ $entry['number'] }}</p>
                    <p class="truncate text-3xl font-bold">{{ $entry['first_name'] }}</p>
                </div>
            @endforeach
        </div>
        <div class="flex items-end justify-between gap-4">
            <div class="rounded-2xl bg-white p-3 text-center text-nana-ink">
                <img src="{{ $qr }}" alt="QR code para acompanhar o pedido" class="h-36 w-36">
                <p class="mt-1 text-sm font-bold">Acompanhe pelo celular</p>
            </div>
            <span x-data="clock" x-text="time" class="text-5xl font-black tabular-nums"></span>
        </div>
    </section>

    <div x-show="current" x-cloak class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-nana-yellow text-nana-ink">
        <p class="text-6xl font-black uppercase tracking-wide">Pedido pronto</p>
        <p class="mt-6 text-[12rem] font-black leading-none tabular-nums" x-text="current?.number"></p>
        <p class="mt-4 text-8xl font-black" x-text="current?.name"></p>
        <div class="absolute bottom-0 left-0 h-6 bg-nana-red" :style="`width: ${progress}%`"></div>
    </div>
</div>
```

`resources/views/livewire/painel/mobile.blade.php`:

```blade
<div x-data="trackMine" @announce.window="onAnnounce($event.detail)" wire:poll.20s class="mx-auto min-h-screen max-w-md bg-white p-4">
    <h1 class="text-center text-3xl font-black text-nana-red">{{ \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</h1>

    <label class="mt-4 block">
        <span class="text-sm font-semibold uppercase text-stone-500">Seu número</span>
        <input type="tel" inputmode="numeric" maxlength="3" x-model="mine" placeholder="Ex.: 42"
               class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3 text-2xl font-black">
    </label>

    <p x-show="alert" x-cloak class="mt-4 rounded-2xl bg-nana-yellow p-4 text-center text-2xl font-black">Seu pedido está pronto!</p>

    <h2 class="mt-6 rounded-t-2xl bg-nana-red px-4 py-2 text-xl font-black text-white">Prontos</h2>
    <ul class="divide-y divide-stone-100 rounded-b-2xl border border-stone-200">
        @forelse ($this->board['ready'] as $entry)
            <li wire:key="mr-{{ $entry['id'] }}" class="flex items-center justify-between px-4 py-3"
                :class="isMine('{{ $entry['number'] }}') ? 'bg-nana-yellow' : ''">
                <span class="text-3xl font-black tabular-nums">{{ $entry['number'] }}</span>
                <span class="text-xl font-bold">{{ $entry['first_name'] }}</span>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-stone-400">Nenhum pedido pronto agora.</li>
        @endforelse
    </ul>

    <h2 class="mt-6 rounded-t-2xl bg-stone-800 px-4 py-2 text-xl font-black text-white">Preparando</h2>
    <ul class="divide-y divide-stone-100 rounded-b-2xl border border-stone-200">
        @forelse ($this->board['preparing'] as $entry)
            <li wire:key="mp-{{ $entry['id'] }}" class="flex items-center justify-between px-4 py-3"
                :class="isMine('{{ $entry['number'] }}') ? 'bg-amber-100' : ''">
                <span class="text-3xl font-black tabular-nums">{{ $entry['number'] }}</span>
                <span class="text-xl font-bold">{{ $entry['first_name'] }}</span>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-stone-400">Nenhum pedido em preparo.</li>
        @endforelse
    </ul>
</div>
```

- [ ] **Step 5: Write the announcer and the phone tracker** in `resources/js/announcer.js`:

```js
import { chime } from './device';

export function registerAnnouncer(Alpine) {
    Alpine.data('announcer', (durationMs = 8000) => ({
        queue: [],
        current: null,
        progress: 0,
        timer: null,
        enqueue({ orderId, number, name }) {
            const known = this.current?.orderId === orderId || this.queue.some((entry) => entry.orderId === orderId);
            if (known) {
                return;
            }
            this.queue.push({ orderId, number, name });
            if (!this.current) {
                this.next();
            }
        },
        cancel(orderId) {
            this.queue = this.queue.filter((entry) => entry.orderId !== orderId);
        },
        next() {
            this.current = this.queue.shift() ?? null;
            if (!this.current) {
                return;
            }
            try { chime(); } catch (e) { /* áudio indisponível */ }
            const started = Date.now();
            clearInterval(this.timer);
            this.timer = setInterval(() => {
                this.progress = Math.min(100, ((Date.now() - started) / durationMs) * 100);
                if (this.progress >= 100) {
                    clearInterval(this.timer);
                    this.progress = 0;
                    this.current = null;
                    this.$nextTick(() => this.next());
                }
            }, 100);
        },
    }));

    Alpine.data('trackMine', () => ({
        mine: '',
        alert: false,
        init() {
            try { this.mine = localStorage.getItem('nana.mine') || ''; } catch (e) { this.mine = ''; }
            this.$watch('mine', (value) => {
                try { localStorage.setItem('nana.mine', value); } catch (e) { /* armazenamento indisponível */ }
            });
        },
        isMine(number) {
            return this.mine !== '' && number === String(parseInt(this.mine, 10)).padStart(3, '0');
        },
        onAnnounce({ number }) {
            if (!this.isMine(number)) {
                return;
            }
            this.alert = true;
            if (navigator.vibrate) {
                navigator.vibrate([300, 150, 300, 150, 300]);
            }
        },
    }));
}
```

In `resources/js/app.js`, add `import { registerAnnouncer } from './announcer';` and call `registerAnnouncer(Alpine);` inside the `alpine:init` listener.

- [ ] **Step 6: Build the pages**

`resources/views/pages/painel.blade.php`:

```blade
<x-layouts.app title="Painel" body-class="bg-white overflow-hidden">
    <x-device-gate :fullscreen="true" />
    <livewire:painel.public-board variant="tv" />
</x-layouts.app>
```

`resources/views/pages/acompanhar.blade.php`:

```blade
<x-layouts.app title="Acompanhe seu pedido" body-class="bg-white">
    <livewire:painel.public-board variant="mobile" />
</x-layouts.app>
```

- [ ] **Step 7: Run the full suite and the build**

Run: `./vendor/bin/pest && npm run build`
Expected: all PASS. If the QR test fails because the output is not base64 SVG, set the `QROptions` options `'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class, 'outputBase64' => true` and re-run.

- [ ] **Step 8: Check it manually.** Open `/painel` (click "Toque para começar"), `/estacao` and `/acompanhar` on a phone over the local network or in a narrow browser window. Mark an order ready.
  - Expected: the TV shows the yellow full screen "PEDIDO PRONTO 001 Maria" with the chime for 8 s, and the number moves to "Prontos".

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: painel da TV com anúncio e som, acompanhamento no celular e QR code" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 16: First deploy to the VPS (real infrastructure validation)

Validates spec section 11, items 2 and 4. **Includes a checkpoint where the user must act on GitHub.**

**Files:**
- Create: `deploy/deploy.sh`, `deploy/nginx/comanda.mswebsolutions.site.conf`, `deploy/systemd/comanda-reverb.service`, `deploy/cron/comanda`, `config/comanda.php`
- Modify: `docs/validacoes.md`

**Interfaces:**
- Consumes: the whole app.
- Produces:
  - `https://comanda.mswebsolutions.site` live;
  - the `comanda-reverb` systemd service;
  - `deploy/deploy.sh`, used by every later deploy as `ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh'`;
  - `config('comanda.backup_path')` and `config('comanda.backup_keep_days')`, used in Task 19.

- [ ] **Step 1: Add the app config** `config/comanda.php`:

```php
<?php

return [
    'backup_path' => env('BACKUP_PATH', storage_path('app/backups')),
    'backup_keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
];
```

- [ ] **Step 2: Add the deploy files**

`deploy/nginx/comanda.mswebsolutions.site.conf`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name comanda.mswebsolutions.site;
    root /var/www/comanda/public;
    index index.php;
    charset utf-8;
    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Laravel Reverb (WebSocket + HTTP API)
    location ~ ^/(app|apps)(/|$) {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_read_timeout 120s;
        proxy_pass http://127.0.0.1:8080;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`deploy/systemd/comanda-reverb.service`:

```ini
[Unit]
Description=Nana Comanda - Laravel Reverb
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/comanda
ExecStart=/usr/bin/php8.3 artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction
Restart=always
RestartSec=3
LimitNOFILE=10000

[Install]
WantedBy=multi-user.target
```

`deploy/cron/comanda` (it must end with a newline):

```
* * * * * www-data cd /var/www/comanda && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1
```

`deploy/deploy.sh`:

```bash
#!/usr/bin/env bash
# Publica a versão mais recente do main.
# Uso (do seu PC): ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh'
set -euo pipefail

APP_DIR=/var/www/comanda
PHP=/usr/bin/php8.3
export COMPOSER_ALLOW_SUPERUSER=1

cd "$APP_DIR"
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build
$PHP artisan migrate --force
$PHP artisan optimize
if $PHP artisan list --raw | grep -q '^filament:optimize'; then
    $PHP artisan filament:optimize
fi
chown -R www-data:www-data storage bootstrap/cache
systemctl restart comanda-reverb
echo "Deploy concluído: $(git log -1 --format='%h %s')"
```

Commit and push them:

```bash
git add -A
git commit -m "chore: arquivos de deploy (nginx, Reverb, cron, script)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push
```

- [ ] **Step 3: Create the deploy key on the VPS** (read-only access to the repo). This never touches the keys of other projects.

```bash
ssh root@145.223.26.225 'test -f /root/.ssh/nova_comanda_deploy || ssh-keygen -t ed25519 -N "" -C "deploy@comanda.mswebsolutions.site" -f /root/.ssh/nova_comanda_deploy; grep -q "Host github-nova-comanda" /root/.ssh/config 2>/dev/null || printf "\nHost github-nova-comanda\n  HostName github.com\n  User git\n  IdentityFile /root/.ssh/nova_comanda_deploy\n  IdentitiesOnly yes\n" >> /root/.ssh/config; cat /root/.ssh/nova_comanda_deploy.pub'
```

- [ ] **Step 4: CHECKPOINT (user).** Show the printed public key to the user and ask them to add it at `https://github.com/marcosserg12/nova_comanda/settings/keys` → **Add deploy key**, title `VPS comanda`, with **Allow write access unchecked**. Wait for confirmation, then verify:

Run: `ssh root@145.223.26.225 'ssh -o StrictHostKeyChecking=accept-new -T git@github-nova-comanda 2>&1 | head -1'`
Expected: `Hi marcosserg12/nova_comanda! You've successfully authenticated, but GitHub does not provide shell access.`

- [ ] **Step 5: Clone, create the DB and the user, write `.env`.** First check the FPM socket: `ssh root@145.223.26.225 'ls /run/php/'`. Expected: `php8.3-fpm.sock`. If the name differs, fix the nginx file in Step 2 and push.

```bash
ssh root@145.223.26.225 bash -s <<'EOF'
set -euo pipefail
test -d /var/www/comanda || git clone git@github-nova-comanda:marcosserg12/nova_comanda.git /var/www/comanda
cd /var/www/comanda
DB_PASS=$(openssl rand -hex 16)
REVERB_KEY=$(openssl rand -hex 16)
REVERB_SECRET=$(openssl rand -hex 24)
REVERB_ID=$(shuf -i 100000-999999 -n 1)
mysql -e "CREATE DATABASE IF NOT EXISTS comanda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'comanda'@'localhost' IDENTIFIED BY '${DB_PASS}'; ALTER USER 'comanda'@'localhost' IDENTIFIED BY '${DB_PASS}'; GRANT ALL PRIVILEGES ON comanda.* TO 'comanda'@'localhost'; FLUSH PRIVILEGES;"
cat > .env <<ENV
APP_NAME="Nana Comanda"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://comanda.mswebsolutions.site
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=pt_BR
LOG_CHANNEL=daily
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=comanda
DB_USERNAME=comanda
DB_PASSWORD=${DB_PASS}
SESSION_DRIVER=database
SESSION_LIFETIME=720
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=${REVERB_ID}
REVERB_APP_KEY=${REVERB_KEY}
REVERB_APP_SECRET=${REVERB_SECRET}
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
VITE_APP_NAME="Nana Comanda"
VITE_REVERB_APP_KEY=${REVERB_KEY}
VITE_REVERB_HOST=comanda.mswebsolutions.site
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
BACKUP_PATH=/var/backups/comanda
BACKUP_KEEP_DAYS=14
ENV
chown root:www-data .env && chmod 640 .env
mkdir -p /var/backups/comanda && chown www-data:www-data /var/backups/comanda
EOF
```

- [ ] **Step 6: Install, migrate and seed.** Generate the passwords and **show them to the user at the end** (they are only printed here).

```bash
ssh root@145.223.26.225 bash -s <<'EOF'
set -euo pipefail
cd /var/www/comanda
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund && npm run build
php8.3 artisan key:generate --force
php8.3 artisan migrate --force
SEED_ADMIN_PASSWORD=$(openssl rand -base64 12 | tr -d '/+=' | cut -c1-12) \
SEED_BALCAO_PASSWORD=$(openssl rand -base64 12 | tr -d '/+=' | cut -c1-12) \
php8.3 artisan db:seed --force
php8.3 artisan storage:link
php8.3 artisan optimize
chown -R www-data:www-data storage bootstrap/cache
EOF
```

Expected: the seeder prints `Usuário admin criado. Senha: …` and `Usuário balcao criado. Senha: …`.

- [ ] **Step 7: nginx, HTTPS, Reverb service, cron** (isolated from the other sites):

```bash
ssh root@145.223.26.225 bash -s <<'EOF'
set -euo pipefail
cd /var/www/comanda
cp deploy/nginx/comanda.mswebsolutions.site.conf /etc/nginx/sites-available/comanda.mswebsolutions.site.conf
ln -sf /etc/nginx/sites-available/comanda.mswebsolutions.site.conf /etc/nginx/sites-enabled/comanda.mswebsolutions.site.conf
nginx -t
systemctl reload nginx
certbot --nginx -d comanda.mswebsolutions.site --non-interactive --redirect
nginx -t && systemctl reload nginx
cp deploy/systemd/comanda-reverb.service /etc/systemd/system/comanda-reverb.service
systemctl daemon-reload
systemctl enable --now comanda-reverb
install -m 644 deploy/cron/comanda /etc/cron.d/comanda
systemctl is-active comanda-reverb
EOF
```

If `nginx -t` fails, **stop**. Remove the symlink (`rm /etc/nginx/sites-enabled/comanda.mswebsolutions.site.conf`) so the other sites stay up, fix the file, and repeat.

- [ ] **Step 8: Verify the infrastructure**

Run: `curl -sI https://comanda.mswebsolutions.site/up | head -1`
Expected: `HTTP/2 200`.

Run, using `REVERB_APP_KEY` from the server's `.env`:

```bash
KEY=$(ssh root@145.223.26.225 "grep ^REVERB_APP_KEY= /var/www/comanda/.env | cut -d= -f2")
curl -s -o /dev/null -w "%{http_code}\n" --http1.1 -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" "https://comanda.mswebsolutions.site/app/${KEY}" --max-time 5
```
Expected: `101`.

- [ ] **Step 9: End-to-end check with the user.** Log in as `balcao` on `https://comanda.mswebsolutions.site/estacao` and open `/painel` in another window or device. Send an order, mark it ready, and confirm it appears instantly and the TV announces it.

Append to `docs/validacoes.md`:

```markdown
## 2. Reverb atrás do nginx na VPS (Task 16, data: AAAA-MM-DD)

| Verificação | Resultado |
|---|---|
| `https://comanda.mswebsolutions.site/up` responde 200 com HTTPS | OK / FALHOU |
| Handshake WebSocket em `/app/{key}` responde 101 | OK / FALHOU |
| `systemctl is-active comanda-reverb` = active | OK / FALHOU |
| Pedido lançado aparece na cozinha e no painel sem recarregar | OK / FALHOU |

## 4. DNS
`comanda.mswebsolutions.site` → `145.223.26.225` (confirmado em 2026-11-13).
```

Commit: `git add docs/validacoes.md && git commit -m "docs: validação do deploy na VPS" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" && git push`

---

### Task 17: Filament admin — panel, menu and users

**Files:**
- Create (via installer): `app/Providers/Filament/AdminPanelProvider.php`
- Create: `app/Filament/Resources/Categories/{CategoryResource.php, Pages/ListCategories.php, Pages/CreateCategory.php, Pages/EditCategory.php}`
- Create: `app/Filament/Resources/Products/{ProductResource.php, Pages/ListProducts.php, Pages/CreateProduct.php, Pages/EditProduct.php}`
- Create: `app/Filament/Resources/QuickNotes/{QuickNoteResource.php, Pages/ListQuickNotes.php, Pages/CreateQuickNote.php, Pages/EditQuickNote.php}`
- Create: `app/Filament/Resources/Users/{UserResource.php, Pages/ListUsers.php, Pages/CreateUser.php, Pages/EditUser.php}`
- Modify: `app/Models/User.php`, `resources/views/components/balcao-pane.blade.php`
- Test: `tests/Feature/Admin/AdminAccessTest.php`, `tests/Feature/Admin/MenuResourcesTest.php`

**Interfaces:**
- Consumes: the models, enums and `Money` helpers; the `price` and `sold_out_today` virtual attributes from Task 3.
- Produces:
  - the panel `admin` at `/admin`, which uses the app's own `/login`;
  - `User implements FilamentUser` (`canAccessPanel` only for `admin`);
  - CRUD for categories, products (price and "Esgotado hoje" editable in the table), quick notes and users.

- [ ] **Step 1: Install Filament**

```bash
composer require filament/filament:"^5.0" -W
php artisan filament:install --panels
```
When asked for the panel ID, answer `admin`.

- [ ] **Step 2: Adjust the generated `app/Providers/Filament/AdminPanelProvider.php`.** Keep its imports and middleware and make these edits only:
  - remove the line `->login()`, because the app's own `/login` is used;
  - after `->path('admin')`, add `->brandName('Nana Comanda')`;
  - set `->colors(['primary' => Color::Red])`;
  - in `->widgets([...])`, keep only `AccountWidget::class` (remove `FilamentInfoWidget::class`).

- [ ] **Step 3: Write the failing tests**

`tests/Feature/Admin/AdminAccessTest.php`:

```php
<?php

use App\Enums\Role;

it('opens the admin only for the admin profile', function () {
    $this->actingAs(userWithRole(Role::Admin))->get('/admin')->assertOk();
    $this->actingAs(userWithRole(Role::Balcao))->get('/admin')->assertForbidden();
});

it('sends guests to the app login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});
```

`tests/Feature/Admin/MenuResourcesTest.php`:

```php
<?php

use App\Enums\Destination;
use App\Enums\Role;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(fn () => $this->actingAs(userWithRole(Role::Admin)));

it('lists products with the price in reais', function () {
    $product = kitchenProduct(['name' => 'Carne de sol', 'price_cents' => 3200]);

    livewire(ListProducts::class)->assertOk()->assertCanSeeTableRecords([$product])->assertSee('32,00');
});

it('creates a product typing the price in reais', function () {
    $category = Category::factory()->create(['destination' => Destination::Cozinha]);

    livewire(CreateProduct::class)
        ->fillForm(['category_id' => $category->id, 'name' => 'Feijoada', 'price' => '29,90', 'active' => true, 'position' => 1])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::query()->where('name', 'Feijoada')->value('price_cents'))->toBe(2990);
});

it('fills the edit form with the current price and saves a new one', function () {
    $product = kitchenProduct(['price_cents' => 2400]);

    livewire(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertSchemaStateSet(['price' => '24,00'])
        ->fillForm(['price' => '25'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->price_cents)->toBe(2500);
});

it('creates categories and users', function () {
    livewire(CreateCategory::class)
        ->fillForm(['name' => 'Sobremesas', 'destination' => 'cozinha', 'position' => 4, 'active' => true])
        ->call('create')->assertHasNoFormErrors();

    livewire(CreateUser::class)
        ->fillForm(['name' => 'Cozinha', 'username' => 'cozinha', 'role' => 'cozinha', 'password' => 'segredo123'])
        ->call('create')->assertHasNoFormErrors();

    expect(Category::query()->where('name', 'Sobremesas')->exists())->toBeTrue()
        ->and(User::query()->where('username', 'cozinha')->sole()->role)->toBe(Role::Cozinha);
});
```

If `assertSchemaStateSet` is unknown in the installed Filament, use `assertFormSet` (the Filament 3 name) with the same array.

- [ ] **Step 4: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Admin`
Expected: FAIL. `/admin` returns 403 for the admin because `canAccessPanel` is missing, and the resource classes are not found.

- [ ] **Step 5: Implement**

`app/Models/User.php`: add the interface and the method (keep everything else):

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    // ... (unchanged)

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole(Role::Admin);
    }
}
```

`app/Filament/Resources/Categories/CategoryResource.php`:

```php
<?php

namespace App\Filament\Resources\Categories;

use App\Enums\Destination;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $modelLabel = 'categoria';

    protected static ?string $pluralModelLabel = 'categorias';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(40),
            Select::make('destination')->label('Destino')->options(Destination::options())->required(),
            TextInput::make('position')->label('Ordem')->numeric()->default(0),
            Toggle::make('active')->label('Ativa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('destination')->label('Destino')->badge()
                    ->formatStateUsing(fn (Destination $state) => $state->label()),
                TextColumn::make('position')->label('Ordem')->sortable(),
                ToggleColumn::make('active')->label('Ativa'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
```

`app/Filament/Resources/Categories/Pages/ListCategories.php`:

```php
<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`app/Filament/Resources/Categories/Pages/CreateCategory.php`:

```php
<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
```

`app/Filament/Resources/Categories/Pages/EditCategory.php`:

```php
<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

`app/Filament/Resources/Products/ProductResource.php`:

```php
<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    public const PRICE_REGEX = '/^\d{1,4}([.,]\d{1,2})?$/';

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'produto';

    protected static ?string $pluralModelLabel = 'produtos';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')->label('Categoria')->relationship('category', 'name')->required(),
            TextInput::make('name')->label('Nome')->required()->maxLength(80),
            TextInput::make('price')->label('Preço (R$)')->placeholder('24,00')->required()->regex(self::PRICE_REGEX),
            Toggle::make('has_daily_name')->label('Tem nome do dia (ex.: Prato do dia)'),
            Toggle::make('active')->label('Ativo')->default(true),
            TextInput::make('position')->label('Ordem')->numeric()->default(0),
            FileUpload::make('image_path')->label('Foto')->image()->disk('public')->directory('products'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('image_path')->label('Foto')->disk('public'),
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Categoria'),
                TextInputColumn::make('price')->label('Preço (R$)')->rules(['required', 'regex:'.self::PRICE_REGEX]),
                ToggleColumn::make('sold_out_today')->label('Esgotado hoje'),
                ToggleColumn::make('active')->label('Ativo'),
            ])
            ->filters([SelectFilter::make('category_id')->label('Categoria')->relationship('category', 'name')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
```

`app/Filament/Resources/Products/Pages/ListProducts.php`:

```php
<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`app/Filament/Resources/Products/Pages/CreateProduct.php`:

```php
<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
```

`app/Filament/Resources/Products/Pages/EditProduct.php`:

```php
<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['price'] = Money::toDecimalString((int) $data['price_cents']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

`app/Filament/Resources/QuickNotes/QuickNoteResource.php`:

```php
<?php

namespace App\Filament\Resources\QuickNotes;

use App\Filament\Resources\QuickNotes\Pages\CreateQuickNote;
use App\Filament\Resources\QuickNotes\Pages\EditQuickNote;
use App\Filament\Resources\QuickNotes\Pages\ListQuickNotes;
use App\Models\QuickNote;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuickNoteResource extends Resource
{
    protected static ?string $model = QuickNote::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $modelLabel = 'observação rápida';

    protected static ?string $pluralModelLabel = 'observações rápidas';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')->label('Categoria')->relationship('category', 'name')->required(),
            TextInput::make('label')->label('Texto do botão')->required()->maxLength(40),
            TextInput::make('position')->label('Ordem')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('label')->label('Texto')->searchable(),
                TextColumn::make('category.name')->label('Categoria'),
                TextColumn::make('position')->label('Ordem')->sortable(),
            ])
            ->filters([SelectFilter::make('category_id')->label('Categoria')->relationship('category', 'name')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuickNotes::route('/'),
            'create' => CreateQuickNote::route('/create'),
            'edit' => EditQuickNote::route('/{record}/edit'),
        ];
    }
}
```

`app/Filament/Resources/QuickNotes/Pages/ListQuickNotes.php`:

```php
<?php

namespace App\Filament\Resources\QuickNotes\Pages;

use App\Filament\Resources\QuickNotes\QuickNoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuickNotes extends ListRecords
{
    protected static string $resource = QuickNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`app/Filament/Resources/QuickNotes/Pages/CreateQuickNote.php`:

```php
<?php

namespace App\Filament\Resources\QuickNotes\Pages;

use App\Filament\Resources\QuickNotes\QuickNoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuickNote extends CreateRecord
{
    protected static string $resource = QuickNoteResource::class;
}
```

`app/Filament/Resources/QuickNotes/Pages/EditQuickNote.php`:

```php
<?php

namespace App\Filament\Resources\QuickNotes\Pages;

use App\Filament\Resources\QuickNotes\QuickNoteResource;
use Filament\Resources\Pages\EditRecord;

class EditQuickNote extends EditRecord
{
    protected static string $resource = QuickNoteResource::class;
}
```

`app/Filament/Resources/Users/UserResource.php`:

```php
<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'usuário';

    protected static ?string $pluralModelLabel = 'usuários';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(60),
            TextInput::make('username')->label('Usuário')->required()->maxLength(40)->unique(ignoreRecord: true),
            Select::make('role')->label('Perfil')->options(Role::options())->required(),
            TextInput::make('password')->label('Senha')->password()->revealable()
                ->minLength(6)
                ->dehydrated(fn (?string $state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('username')->label('Usuário')->searchable(),
                TextColumn::make('role')->label('Perfil')->badge()->formatStateUsing(fn (Role $state) => $state->label()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
```

`app/Filament/Resources/Users/Pages/ListUsers.php`:

```php
<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`app/Filament/Resources/Users/Pages/CreateUser.php`:

```php
<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
```

`app/Filament/Resources/Users/Pages/EditUser.php`:

```php
<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
```

Add an admin link to the counter header. In `resources/views/components/balcao-pane.blade.php`, inside `<div class="ml-auto flex items-center gap-4">` and before `<x-connection-dot />`, insert:

```blade
@if (auth()->user()?->hasRole(\App\Enums\Role::Admin))
    <a href="{{ url('/admin') }}" class="text-sm font-semibold text-stone-500 underline">Administração</a>
@endif
```

- [ ] **Step 6: Run the full suite**

Run: `./vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 7: Commit and deploy**

```bash
git add -A
git commit -m "feat: administração (Filament) com cardápio, observações e usuários" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push
ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh'
```
Expected: the last line is `Deploy concluído: … feat: administração …`.

---

### Task 18: Filament — order history, sales summary and settings

**Files:**
- Create: `app/Queries/SalesSummary.php`
- Create: `app/Filament/Resources/Orders/{OrderResource.php, Pages/ListOrders.php, Pages/ViewOrder.php}`
- Create: `app/Filament/Pages/SalesSummaryPage.php`, `app/Filament/Pages/SettingsPage.php`
- Create: `resources/views/filament/pages/sales-summary.blade.php`, `resources/views/filament/pages/settings.blade.php`
- Test: `tests/Feature/Queries/SalesSummaryTest.php`, `tests/Feature/Admin/ReportsAndSettingsTest.php`

**Interfaces:**
- Consumes: `Order`, `OrderItem`, `Setting`, `Money`, `BusinessDay`.
- Produces:
  - `SalesSummary::between(CarbonImmutable $from, CarbonImmutable $until): array{total_cents:int, orders:int, average_cents:int, by_product: list<array{name:string, quantity:int, total_cents:int}>}`. Canceled orders are excluded.
  - A read-only `OrderResource` with filters for period, status and product.
  - `SalesSummaryPage` (slug `resumo-de-vendas`), with public props `from` and `until`.
  - `SettingsPage` (slug `configuracoes`), with public props `readyMinutes`, `restaurantName`, `stationLeftWidth`, `stationRightWidth` and the method `save()`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Queries/SalesSummaryTest.php`:

```php
<?php

use App\Actions\Orders\TransitionOrder;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Queries\SalesSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

it('sums sales by period excluding canceled orders', function () {
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    $dish = kitchenProduct(['name' => 'Filé de frango', 'price_cents' => 2400]);
    $juice = kitchenProduct(['name' => 'Suco de manga', 'price_cents' => 600]);

    placeOrder([item($dish, 2), item($juice)]);
    placeOrder([item($dish)]);
    $canceled = placeOrder([item($juice, 5)]);
    app(TransitionOrder::class)->cancel($canceled, 'Lançado errado');

    $this->travelTo(CarbonImmutable::parse('2026-11-14 11:00', 'America/Sao_Paulo'));
    placeOrder([item($dish)]);

    $day = app(SalesSummary::class)->between(CarbonImmutable::parse('2026-11-13'), CarbonImmutable::parse('2026-11-13'));

    expect($day['orders'])->toBe(2)
        ->and($day['total_cents'])->toBe(7800)
        ->and($day['average_cents'])->toBe(3900)
        ->and($day['by_product'])->toBe([
            ['name' => 'Filé de frango', 'quantity' => 3, 'total_cents' => 7200],
            ['name' => 'Suco de manga', 'quantity' => 1, 'total_cents' => 600],
        ]);

    expect(app(SalesSummary::class)->between(CarbonImmutable::parse('2026-11-13'), CarbonImmutable::parse('2026-11-14'))['orders'])->toBe(3);
});
```

`tests/Feature/Admin/ReportsAndSettingsTest.php`:

```php
<?php

use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Filament\Pages\SalesSummaryPage;
use App\Filament\Pages\SettingsPage;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Setting;
use Illuminate\Support\Facades\Event;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->actingAs(userWithRole(Role::Admin));
});

it('lists and shows orders in the history', function () {
    $order = placeOrder([item(kitchenProduct(['name' => 'Coxão mole']), 1, 'Sem farofa')], ['customer_name' => 'Rui']);

    livewire(ListOrders::class)->assertOk()->assertCanSeeTableRecords([$order])->assertSee('Rui');
    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])->assertOk()->assertSee('Coxão mole')->assertSee('Sem farofa');
});

it('shows the sales summary for today', function () {
    placeOrder([item(kitchenProduct(['price_cents' => 2400]))]);

    livewire(SalesSummaryPage::class)->assertOk()->assertSee('R$ 24,00');
});

it('saves the settings', function () {
    livewire(SettingsPage::class)
        ->set('readyMinutes', 8)
        ->set('restaurantName', 'Nana Lanches')
        ->set('stationLeftWidth', 1920)
        ->set('stationRightWidth', 1366)
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get(Setting::READY_MINUTES))->toBe(8)
        ->and(Setting::get(Setting::STATION_RIGHT_WIDTH))->toBe(1366);
});

it('validates settings', function () {
    livewire(SettingsPage::class)->set('readyMinutes', 0)->call('save')->assertHasErrors('readyMinutes');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/pest tests/Feature/Queries/SalesSummaryTest.php tests/Feature/Admin/ReportsAndSettingsTest.php`
Expected: FAIL, `Class "App\Queries\SalesSummary" not found`.

- [ ] **Step 3: Implement the query** `app/Queries/SalesSummary.php`:

```php
<?php

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;

final class SalesSummary
{
    /** @return array{total_cents: int, orders: int, average_cents: int, by_product: list<array{name: string, quantity: int, total_cents: int}>} */
    public function between(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $base = Order::query()
            ->whereDate('business_date', '>=', $from->toDateString())
            ->whereDate('business_date', '<=', $until->toDateString())
            ->where('status', '!=', OrderStatus::Canceled->value);

        $orders = (clone $base)->count();
        $total = (int) (clone $base)->sum('total_cents');

        $byProduct = OrderItem::query()
            ->whereIn('order_id', (clone $base)->select('id'))
            ->selectRaw('name_snapshot as name, SUM(quantity) as qty, SUM(quantity * unit_price_cents) as cents')
            ->groupBy('name_snapshot')
            ->orderByDesc('qty')
            ->orderBy('name_snapshot')
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->name, 'quantity' => (int) $row->qty, 'total_cents' => (int) $row->cents])
            ->all();

        return [
            'total_cents' => $total,
            'orders' => $orders,
            'average_cents' => $orders > 0 ? intdiv($total, $orders) : 0,
            'by_product' => $byProduct,
        ];
    }
}
```

- [ ] **Step 4: Implement the order history resource**

`app/Filament/Resources/Orders/OrderResource.php`:

```php
<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'pedido';

    protected static ?string $pluralModelLabel = 'histórico de pedidos';

    protected static ?string $navigationLabel = 'Histórico';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('business_date')->label('Dia')->date('d/m/Y')->sortable(),
                TextColumn::make('number')->label('Nº')->formatStateUsing(fn (int $state) => str_pad((string) $state, 3, '0', STR_PAD_LEFT)),
                TextColumn::make('customer_name')->label('Cliente')->searchable(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                TextColumn::make('total_cents')->label('Total')->formatStateUsing(fn (int $state) => Money::format($state)),
                TextColumn::make('created_at')->label('Hora')->dateTime('H:i'),
            ])
            ->filters([
                Filter::make('periodo')
                    ->schema([DatePicker::make('from')->label('De'), DatePicker::make('until')->label('Até')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('business_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('business_date', '<=', $date))),
                SelectFilter::make('status')->label('Status')->options(OrderStatus::options()),
                SelectFilter::make('product')->label('Produto')
                    ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $productId) => $q->whereHas('items', fn (Builder $items) => $items->where('product_id', $productId)),
                    )),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('number')->label('Número')->formatStateUsing(fn (int $state) => str_pad((string) $state, 3, '0', STR_PAD_LEFT)),
            TextEntry::make('customer_name')->label('Cliente'),
            TextEntry::make('status')->label('Status')->formatStateUsing(fn (OrderStatus $state) => $state->label()),
            TextEntry::make('total_cents')->label('Total')->formatStateUsing(fn (int $state) => Money::format($state)),
            TextEntry::make('cancel_reason')->label('Motivo do cancelamento')->placeholder('—'),
            RepeatableEntry::make('items')->label('Itens')->schema([
                TextEntry::make('quantity')->label('Qtd'),
                TextEntry::make('name_snapshot')->label('Produto'),
                TextEntry::make('notes')->label('Observação')->placeholder('—'),
                TextEntry::make('unit_price_cents')->label('Preço')->formatStateUsing(fn (int $state) => Money::format($state)),
            ])->columns(4)->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
```

`app/Filament/Resources/Orders/Pages/ListOrders.php`:

```php
<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
```

`app/Filament/Resources/Orders/Pages/ViewOrder.php`:

```php
<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;
}
```

- [ ] **Step 5: Implement the custom pages.** They use plain Livewire properties and Filament Blade components, and inline styles because the panel CSS does not compile app Tailwind classes.

`app/Filament/Pages/SalesSummaryPage.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Queries\SalesSummary;
use App\Support\BusinessDay;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class SalesSummaryPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Resumo de vendas';

    protected static ?string $title = 'Resumo de vendas';

    protected static ?string $slug = 'resumo-de-vendas';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.sales-summary';

    public string $from = '';

    public string $until = '';

    public function mount(): void
    {
        $this->from = BusinessDay::todayString();
        $this->until = BusinessDay::todayString();
    }

    #[Computed]
    public function summary(): array
    {
        $from = CarbonImmutable::parse($this->from !== '' ? $this->from : BusinessDay::todayString());
        $until = CarbonImmutable::parse($this->until !== '' ? $this->until : BusinessDay::todayString());

        if ($until->lt($from)) {
            [$from, $until] = [$until, $from];
        }

        return app(SalesSummary::class)->between($from, $until);
    }
}
```

`resources/views/filament/pages/sales-summary.blade.php`:

```blade
@use('App\Support\Money')
<x-filament-panels::page>
    <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
        <label>De
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
        </label>
        <label>Até
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="until" /></x-filament::input.wrapper>
        </label>
    </div>

    @php($summary = $this->summary)

    <div style="display:grid; gap:1rem; grid-template-columns:repeat(3, minmax(0, 1fr));">
        <x-filament::section>
            <x-slot name="heading">Total vendido</x-slot>
            <p style="font-size:1.875rem; font-weight:800;">{{ Money::format($summary['total_cents']) }}</p>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Pedidos</x-slot>
            <p style="font-size:1.875rem; font-weight:800;">{{ $summary['orders'] }}</p>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Ticket médio</x-slot>
            <p style="font-size:1.875rem; font-weight:800;">{{ Money::format($summary['average_cents']) }}</p>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Por produto</x-slot>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="text-align:left;"><th style="padding:.5rem;">Produto</th><th style="padding:.5rem;">Quantidade</th><th style="padding:.5rem;">Total</th></tr>
            </thead>
            <tbody>
                @forelse ($summary['by_product'] as $row)
                    <tr style="border-top:1px solid rgba(0,0,0,.08);">
                        <td style="padding:.5rem;">{{ $row['name'] }}</td>
                        <td style="padding:.5rem;">{{ $row['quantity'] }}</td>
                        <td style="padding:.5rem;">{{ Money::format($row['total_cents']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="padding:1rem; text-align:center; opacity:.6;">Nenhuma venda no período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
```

`app/Filament/Pages/SettingsPage.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Configurações';

    protected static ?string $title = 'Configurações';

    protected static ?string $slug = 'configuracoes';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.settings';

    public int $readyMinutes = 5;

    public string $restaurantName = '';

    public int $stationLeftWidth = 1920;

    public int $stationRightWidth = 1920;

    public function mount(): void
    {
        $this->readyMinutes = (int) Setting::get(Setting::READY_MINUTES);
        $this->restaurantName = (string) Setting::get(Setting::RESTAURANT_NAME);
        $this->stationLeftWidth = (int) Setting::get(Setting::STATION_LEFT_WIDTH);
        $this->stationRightWidth = (int) Setting::get(Setting::STATION_RIGHT_WIDTH);
    }

    public function save(): void
    {
        $this->validate([
            'readyMinutes' => ['required', 'integer', 'min:1', 'max:60'],
            'restaurantName' => ['required', 'string', 'max:60'],
            'stationLeftWidth' => ['required', 'integer', 'min:800', 'max:7680'],
            'stationRightWidth' => ['required', 'integer', 'min:600', 'max:7680'],
        ]);

        Setting::put(Setting::READY_MINUTES, $this->readyMinutes);
        Setting::put(Setting::RESTAURANT_NAME, $this->restaurantName);
        Setting::put(Setting::STATION_LEFT_WIDTH, $this->stationLeftWidth);
        Setting::put(Setting::STATION_RIGHT_WIDTH, $this->stationRightWidth);

        Notification::make()->title('Configurações salvas')->success()->send();
    }
}
```

`resources/views/filament/pages/settings.blade.php`:

```blade
<x-filament-panels::page>
    <form wire:submit="save" style="display:grid; gap:1rem; max-width:32rem;">
        <x-filament::section>
            <x-slot name="heading">Painel da TV</x-slot>
            <label style="display:block;">Minutos que o pedido fica em "Prontos"
                <x-filament::input.wrapper><x-filament::input type="number" min="1" max="60" wire:model="readyMinutes" /></x-filament::input.wrapper>
            </label>
            @error('readyMinutes') <p style="color:#b91c1c;">{{ $message }}</p> @enderror
            <label style="display:block; margin-top:1rem;">Nome do restaurante
                <x-filament::input.wrapper><x-filament::input type="text" wire:model="restaurantName" /></x-filament::input.wrapper>
            </label>
            @error('restaurantName') <p style="color:#b91c1c;">{{ $message }}</p> @enderror
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Estação (dois monitores)</x-slot>
            <label style="display:block;">Largura do monitor do balcão (pixels)
                <x-filament::input.wrapper><x-filament::input type="number" wire:model="stationLeftWidth" /></x-filament::input.wrapper>
            </label>
            @error('stationLeftWidth') <p style="color:#b91c1c;">{{ $message }}</p> @enderror
            <label style="display:block; margin-top:1rem;">Largura do monitor da cozinha (pixels)
                <x-filament::input.wrapper><x-filament::input type="number" wire:model="stationRightWidth" /></x-filament::input.wrapper>
            </label>
            @error('stationRightWidth') <p style="color:#b91c1c;">{{ $message }}</p> @enderror
        </x-filament::section>

        <div><x-filament::button type="submit">Salvar</x-filament::button></div>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 6: Run the full suite.** If Filament says `$view` must be static, change it to `protected static string $view` in both pages. If `Filter::schema()` is unknown, use `->form([...])`.

Run: `./vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 7: Commit and deploy**

```bash
git add -A
git commit -m "feat: histórico de pedidos, resumo de vendas e configurações no admin" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push
ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh'
```

---

### Task 19: Daily database backup

**Files:**
- Create: `app/Console/Commands/BackupDatabase.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Console/BackupDatabaseTest.php`

**Interfaces:**
- Consumes: `config('comanda.backup_path')`, `config('comanda.backup_keep_days')`, the DB connection config.
- Produces: the artisan command `comanda:backup-db {--connection=} {--keep=}`, scheduled daily at 03:00.
  - It writes `comanda-YYYY-MM-DD_His.sql.gz` with `mysqldump` and `gzip`.
  - It prunes files older than N days.
  - It only supports MySQL.

- [ ] **Step 1: Write the failing test** `tests/Feature/Console/BackupDatabaseTest.php`:

```php
<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->dir = storage_path('framework/testing/backups');
    File::deleteDirectory($this->dir);
    File::ensureDirectoryExists($this->dir);
    config([
        'comanda.backup_path' => $this->dir,
        'database.connections.mysql.host' => '127.0.0.1',
        'database.connections.mysql.port' => '3306',
        'database.connections.mysql.username' => 'comanda',
        'database.connections.mysql.password' => 'segredo',
        'database.connections.mysql.database' => 'comanda',
    ]);
});

it('dumps the database with mysqldump, compresses it and prunes old files', function () {
    Process::fake();
    $old = $this->dir.'/comanda-antigo.sql.gz';
    $recent = $this->dir.'/comanda-recente.sql.gz';
    File::put($old, 'x');
    touch($old, now()->subDays(20)->getTimestamp());
    File::put($recent, 'x');

    $this->artisan('comanda:backup-db', ['--connection' => 'mysql', '--keep' => 14])->assertSuccessful();

    Process::assertRan(function ($process) {
        $command = (array) $process->command;

        return $command[0] === 'mysqldump'
            && in_array('--user=comanda', $command, true)
            && in_array('comanda', $command, true)
            && ($process->environment['MYSQL_PWD'] ?? null) === 'segredo';
    });
    Process::assertRan(fn ($process) => ((array) $process->command)[0] === 'gzip');

    expect(File::exists($old))->toBeFalse()->and(File::exists($recent))->toBeTrue();
});

it('fails clearly for non-MySQL connections', function () {
    Process::fake();

    $this->artisan('comanda:backup-db', ['--connection' => 'sqlite'])->assertFailed();

    Process::assertNothingRan();
});

it('is scheduled daily', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('comanda:backup-db');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/Console`
Expected: FAIL, `The command "comanda:backup-db" does not exist`.

- [ ] **Step 3: Implement** `app/Console/Commands/BackupDatabase.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    protected $signature = 'comanda:backup-db {--connection= : Conexão do config/database.php} {--keep= : Dias de retenção}';

    protected $description = 'Faz o backup diário do banco do Nana Comanda (mysqldump + gzip) e apaga os antigos';

    public function handle(): int
    {
        $name = (string) ($this->option('connection') ?: config('database.default'));
        $connection = (array) config("database.connections.{$name}");

        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error("Backup só é suportado para MySQL (conexão: {$name}).");

            return self::FAILURE;
        }

        $dir = (string) config('comanda.backup_path');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/comanda-'.now()->format('Y-m-d_His').'.sql';

        $dump = Process::env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
            ->timeout(300)
            ->run([
                'mysqldump',
                '--single-transaction',
                '--no-tablespaces',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--user='.$connection['username'],
                '--result-file='.$file,
                $connection['database'],
            ]);

        if ($dump->failed()) {
            $this->error('mysqldump falhou: '.$dump->errorOutput());

            return self::FAILURE;
        }

        Process::run(['gzip', '-f', $file]);

        $keepDays = (int) ($this->option('keep') ?: config('comanda.backup_keep_days'));
        $limit = now()->subDays($keepDays)->getTimestamp();

        foreach (File::files($dir) as $existing) {
            if ($existing->getMTime() < $limit) {
                File::delete($existing->getPathname());
            }
        }

        $this->info('Backup criado: '.basename($file).'.gz');

        return self::SUCCESS;
    }
}
```

Append to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('comanda:backup-db')->dailyAt('03:00');
```

(If `routes/console.php` already imports `Schedule`, don't duplicate the `use`.)

The `database.connections.mysql` config has `driver => mysql` by default in Laravel; the test relies on it.

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Console`
Expected: PASS.

- [ ] **Step 5: Commit, deploy and run it once in production**

```bash
git add -A
git commit -m "feat: backup diário do banco com retenção de 14 dias" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push
ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh && sudo -u www-data php8.3 /var/www/comanda/artisan comanda:backup-db && ls -la /var/backups/comanda'
```
Expected: `Backup criado: comanda-….sql.gz`, and the file is listed.

---

### Task 20: Store operations guide and hardware validation

**Files:**
- Create: `docs/operacao.md`
- Modify: `docs/validacoes.md`

**Interfaces:**
- Consumes: everything deployed.
- Produces: a guide in Portuguese for the user and the family. Validation items 1 (hardware) and 3 (TV autoplay) from the spec, recorded.

- [ ] **Step 1: Write the guide** `docs/operacao.md`:

````markdown
# Nana Comanda: guia de operação da loja

Endereço do sistema: **https://comanda.mswebsolutions.site**

## Telas

| Tela | Endereço | Onde fica |
|---|---|---|
| Estação (balcão + cozinha) | `/estacao` | PC do balcão, dois monitores |
| Só balcão | `/balcao` | qualquer computador |
| Só cozinha | `/cozinha` | aparelho próprio na cozinha (plano B) |
| TV do salão | `/painel` | TV com Google TV |
| Celular do cliente | `/acompanhar` | QR code no balcão |
| Administração | `/admin` | seu pai ou você |

## Montagem do PC do balcão (uma vez)

1. Ligue os dois monitores. Em Windows → Configurações → Sistema → Tela, deixe-os lado a lado (balcão à esquerda, cozinha à direita), na mesma altura. Anote a resolução de cada um.
2. Se a cozinha usar monitor touch, vá em Painel de Controle → Configurações do Tablet PC → **Configurar** → Entrada por toque, e toque no monitor da cozinha quando ele pedir.
3. Crie um atalho na área de trabalho com este destino, trocando as larguras e a altura pelas resoluções reais:
   ```
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="C:\nana-estacao" --app="https://comanda.mswebsolutions.site/estacao" --window-position=0,0 --window-size=3840,1080 --autoplay-policy=no-user-gesture-required
   ```
4. No `/admin` → Configurações, informe as larguras dos dois monitores.
5. Para abrir sozinho ao ligar: tecle Windows+R, digite `shell:startup` e copie o atalho para essa pasta.
6. Em Windows → Energia, deixe a tela e o computador **nunca** desligarem.
7. Faça login como `balcao` e marque **Manter conectado neste aparelho**.

## TV do salão (Google TV + Fully Kiosk Browser)

1. Instale o **Fully Kiosk Browser** (APK de fully-kiosk.com) e compre a licença PLUS (~€8, pagamento único).
2. Configure:
   - Start URL: `https://comanda.mswebsolutions.site/painel`;
   - Web Content Settings → **Autoplay Audio** ligado;
   - Device Management → **Keep Screen On** ligado e **Launch on Boot** ligado;
   - Web Auto Reload → recarregar ao reconectar a rede.
3. Se aparecer "Toque para começar", o som automático não está liberado: revise o item Autoplay Audio.

## QR code do balcão

Imprima o QR que aparece no canto da TV, ou gere um para `https://comanda.mswebsolutions.site/acompanhar`, com o texto "Acompanhe seu pedido".

## Rotina do dia

- **Ao abrir:** ligue o PC e a TV. Na estação, toque em "Toque para começar".
- **Prato do dia:** ao tocar no "Prato do dia" pela primeira vez, o sistema pergunta o prato de hoje. Para trocar, use o ✎ no botão.
- **Acabou um prato:** clique em "Marcar esgotado" e toque no prato. No dia seguinte ele volta sozinho.
- **Errou um pedido:** em "Pedidos de hoje", use "Editar" enquanto estiver em preparo, ou "Cancelar" com o motivo.
- **Cobrança:** em "Pedidos de hoje", procure pelo número ou pelo nome. O total aparece na linha.
- **Mudar preço:** no `/admin` → Produtos, edite o preço direto na lista.

## Se algo der errado

- **Bolinha vermelha "Sem conexão":** a internet caiu. Rote o 4G de um celular para o PC e para a TV. Se demorar, volte para o papel por um tempo. Nenhum pedido já enviado se perde.
- **Pedido não apareceu na cozinha:** espere até 20 segundos (a tela confere sozinha) ou aperte F5.
- **Esqueceu a senha:** o admin troca em `/admin` → Usuários.
- **Atualizar o sistema (desenvolvedor):** `ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh'`
- **Backups:** diários às 03:00 em `/var/backups/comanda`, guardados por 14 dias.
````

- [ ] **Step 2: Validate with the user's real hardware** (at the store or with the equipment at hand). Append to `docs/validacoes.md`:

```markdown
## 1b. Estação com o hardware definitivo (Task 20, data: AAAA-MM-DD)

| Verificação | Resultado |
|---|---|
| Atalho abre a estação cobrindo os dois monitores | OK / FALHOU |
| Toque (ou teclado numérico) da cozinha não atrapalha a digitação do balcão | OK / FALHOU |
| Bip da cozinha toca a cada pedido novo | OK / FALHOU |

## 3. Som automático na TV (Fully Kiosk) (Task 20, data: AAAA-MM-DD)

| Verificação | Resultado |
|---|---|
| Painel abre sozinho ao ligar a TV | OK / FALHOU |
| Anúncio "PEDIDO PRONTO" toca som sem tocar na TV | OK / FALHOU |
| Após cair e voltar a internet, o painel volta a atualizar sozinho | OK / FALHOU |
```

Any FAIL becomes a follow-up item reported to the user. It does not block closing phase 1 if a workaround exists, such as tapping "Toque para começar" once a day.

- [ ] **Step 3: Final verification**

Run: `./vendor/bin/pint --test && ./vendor/bin/pest`
Expected: Pint reports no issues (run `./vendor/bin/pint` first if needed) and all tests PASS.

- [ ] **Step 4: Commit and deploy**

```bash
git add -A
git commit -m "docs: guia de operação da loja e validações de hardware" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git push
ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh'
```
