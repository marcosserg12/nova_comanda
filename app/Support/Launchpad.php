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
