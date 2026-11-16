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
