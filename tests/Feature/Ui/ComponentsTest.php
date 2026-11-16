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
