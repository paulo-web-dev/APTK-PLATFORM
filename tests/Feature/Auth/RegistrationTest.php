<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'maior_idade' => '1',
        ]);

        $this->assertAuthenticated();
        // Carrinho vazio, sem ?voltar: home (leva 09 — não mais /dashboard).
        $response->assertRedirect(route('home'));
        $this->assertNotNull(auth()->user()->maior_idade_em);
    }
}
