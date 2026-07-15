<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }
    public function test_kl_user_is_sent_to_it_dashboard_after_login(): void
    {
        $user = User::factory()->create(['username' => 'kl.user']);
        $kl = Branch::query()->where('code', 'KL-IT')->firstOrFail();
        $user->branches()->attach($kl, ['access_level' => 'edit', 'is_default' => true]);

        $response = $this->post('/login', [
            'username' => 'kl.user',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('it-assets.dashboard', absolute: false));
    }
}
