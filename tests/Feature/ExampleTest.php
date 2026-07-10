<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_google_redirect_reports_missing_config(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $this->get('/auth/google')
            ->assertRedirect('/');
    }

    public function test_authenticated_bootstrap_binds_player_to_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/game/bootstrap', [
            'browserToken' => 'auth-player-token',
        ])->assertOk();

        $this->assertSame($user->id, $response->json('player.user_id'));
    }
}
