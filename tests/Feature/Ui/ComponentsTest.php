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
