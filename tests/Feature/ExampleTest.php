<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Player;
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

    public function test_player_routes_reject_other_users_character(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $player = Player::create([
            'user_id' => $owner->id,
            'browser_token' => 'owned-player-token',
            'name' => 'Owned Player',
            'element' => 'earth',
            'inventory' => [],
            'class_history' => ['normal'],
        ]);

        $this->actingAs($intruder)
            ->patchJson("/game/player/{$player->id}/rename", [
                'name' => 'Stolen Name',
            ])
            ->assertStatus(403);
    }

    public function test_player_routes_require_google_login_outside_testing(): void
    {
        $player = Player::create([
            'user_id' => User::factory()->create()->id,
            'browser_token' => 'login-required-token',
            'name' => 'Login Required',
            'element' => 'earth',
            'inventory' => [],
            'class_history' => ['normal'],
        ]);

        $this->patchJson("/game/player/{$player->id}/rename", [
            'name' => 'No Login',
            'enforce_player_owner' => true,
        ])->assertStatus(401);
    }
}
