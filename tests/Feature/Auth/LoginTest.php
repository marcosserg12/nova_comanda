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
