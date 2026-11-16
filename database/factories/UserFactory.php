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
