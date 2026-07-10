<?php

namespace Tests\Feature;

use App\Models\CharacterClass;
use App\Models\Player;
use Database\Seeders\GameSeedSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_bootstrap_and_fight_monster(): void
    {
        $this->seed(GameSeedSeeder::class);

        $bootstrap = $this->postJson('/game/bootstrap', [
            'browserToken' => 'test-token',
            'name' => 'Tester',
        ]);

        $bootstrap->assertOk()
            ->assertJsonPath('player.name', 'Tester')
            ->assertJsonPath('player.level', 1);

        $playerId = $bootstrap->json('player.id');
        $this->postJson("/game/player/{$playerId}/roll-encounter", [
            'level_dice' => 4,
            'count_dice' => 6,
        ])->assertOk()
            ->assertJsonPath('encounter.isBoss', true)
            ->assertJsonPath('encounter.targetLevel', 1);

        $fight = $this->postJson("/game/player/{$playerId}/fight", [
            'skill_id' => 'punch',
            'dice' => 6,
            'monster_dice' => 1,
        ]);

        $fight->assertOk()
            ->assertJsonPath('battle.won', false)
            ->assertJsonPath('battle.encounter.isBoss', true)
            ->assertJsonPath('battle.turns.0.dice', 6)
            ->assertJsonPath('battle.turns.0.result', 'super_critical')
            ->assertJsonPath('battle.turns.0.multiplier', 2)
            ->assertJsonPath('battle.turns.0.skillBonus', 1.1);

        for ($i = 0; $i < 5 && ! $fight->json('battle.won'); $i++) {
            $fight = $this->postJson("/game/player/{$playerId}/fight", [
                'skill_id' => 'punch',
                'dice' => 6,
                'monster_dice' => 1,
            ])->assertOk();
        }

        $fight->assertJsonPath('battle.won', true)
            ->assertJsonPath('battle.bossReward.amount', 1);
    }

    public function test_player_can_change_class_at_level_ten(): void
    {
        $this->seed(GameSeedSeeder::class);

        $player = Player::create([
            'browser_token' => 'class-token',
            'name' => 'Tester',
            'level' => 10,
            'class_id' => 'normal',
            'exp' => 0,
            'hp' => 100,
            'max_hp' => 100,
            'mp' => 30,
            'max_mp' => 30,
            'atk' => 10,
            'def' => 5,
            'inventory' => [],
            'class_history' => ['normal'],
        ]);

        $response = $this->postJson("/game/player/{$player->id}/change-class", [
            'class_id' => 'mage',
        ]);

        $response->assertOk()
            ->assertJsonPath('player.class_id', 'mage')
            ->assertJsonPath('class.name', CharacterClass::find('mage')->name);
    }

    public function test_monster_rolls_dice_for_counter_attack(): void
    {
        $this->seed(GameSeedSeeder::class);

        $bootstrap = $this->postJson('/game/bootstrap', [
            'browserToken' => 'monster-dice-token',
            'name' => 'Tester',
        ]);

        $playerId = $bootstrap->json('player.id');
        $this->postJson("/game/player/{$playerId}/roll-encounter", [
            'level_dice' => 4,
            'count_dice' => 1,
        ])->assertOk();

        $fight = $this->postJson("/game/player/{$playerId}/fight", [
            'skill_id' => 'punch',
            'dice' => 1,
            'monster_dice' => 6,
        ]);

        $fight->assertOk()
            ->assertJsonPath('battle.turns.0.actor', 'player')
            ->assertJsonPath('battle.turns.0.damage', 0)
            ->assertJsonPath('battle.turns.0.result', 'dodged')
            ->assertJsonPath('battle.turns.1.actor', 'monster')
            ->assertJsonPath('battle.turns.1.dice', 6)
            ->assertJsonPath('battle.turns.1.result', 'super_critical')
            ->assertJsonPath('battle.turns.1.multiplier', 2);
    }

    public function test_player_can_choose_element_and_gain_element_advantage(): void
    {
        $this->seed(GameSeedSeeder::class);

        $bootstrap = $this->postJson('/game/bootstrap', [
            'browserToken' => 'element-token',
            'name' => 'Element Tester',
        ])->assertOk();

        $playerId = $bootstrap->json('player.id');

        $this->patchJson("/game/player/{$playerId}/element", [
            'element' => 'earth',
        ])->assertOk()
            ->assertJsonPath('player.element', 'earth');

        $this->postJson("/game/player/{$playerId}/roll-encounter", [
            'level_dice' => 4,
            'count_dice' => 1,
            'monster_element' => 'water',
        ])->assertOk()
            ->assertJsonPath('encounter.monsters.0.element', 'water')
            ->assertJsonPath('encounter.monsters.0.element_label', 'ธาตุน้ำ');

        $fight = $this->postJson("/game/player/{$playerId}/fight", [
            'skill_id' => 'punch',
            'dice' => 3,
            'monster_dice' => 1,
        ]);

        $fight->assertOk()
            ->assertJsonPath('battle.turns.0.element.attacker', 'earth')
            ->assertJsonPath('battle.turns.0.element.defender', 'water')
            ->assertJsonPath('battle.turns.0.elementMultiplier', 1.5);
    }

    public function test_current_class_skill_is_used_in_combat(): void
    {
        $this->seed(GameSeedSeeder::class);

        $player = Player::create([
            'browser_token' => 'mage-skill-token',
            'name' => 'Mage Tester',
            'level' => 10,
            'class_id' => 'mage',
            'exp' => 0,
            'hp' => 218,
            'max_hp' => 218,
            'mp' => 111,
            'max_mp' => 111,
            'atk' => 55,
            'def' => 25,
            'inventory' => [],
            'class_history' => ['normal', 'mage'],
        ]);

        $this->postJson("/game/player/{$player->id}/roll-encounter", [
            'level_dice' => 4,
            'count_dice' => 1,
        ])->assertOk();

        $fight = $this->postJson("/game/player/{$player->id}/fight", [
            'skill_id' => 'fireball',
            'dice' => 3,
            'monster_dice' => 1,
        ]);

        $fight->assertOk()
            ->assertJsonPath('battle.skill.id', 'fireball')
            ->assertJsonPath('battle.skill.class_id', 'mage');
    }

    public function test_skill_costs_mp_when_used(): void
    {
        $this->seed(GameSeedSeeder::class);

        $player = Player::create([
            'browser_token' => 'mp-cost-token',
            'name' => 'MP Tester',
            'level' => 10,
            'class_id' => 'mage',
            'exp' => 0,
            'hp' => 218,
            'max_hp' => 218,
            'mp' => 20,
            'max_mp' => 111,
            'atk' => 55,
            'def' => 25,
            'inventory' => [],
            'class_history' => ['normal', 'mage'],
        ]);

        $this->postJson("/game/player/{$player->id}/roll-encounter", [
            'level_dice' => 2,
            'count_dice' => 1,
        ])->assertOk();

        $fight = $this->postJson("/game/player/{$player->id}/fight", [
            'skill_id' => 'fireball',
            'dice' => 6,
            'monster_dice' => 1,
        ]);

        $fight->assertOk()
            ->assertJsonPath('battle.turns.0.skill.id', 'fireball')
            ->assertJsonPath('battle.turns.0.manaCost', 8)
            ->assertJsonPath('battle.turns.0.playerMp', 12);
    }

    public function test_skill_falls_back_to_basic_attack_without_enough_mp(): void
    {
        $this->seed(GameSeedSeeder::class);

        $player = Player::create([
            'browser_token' => 'mp-empty-token',
            'name' => 'Empty MP Tester',
            'level' => 10,
            'class_id' => 'mage',
            'exp' => 0,
            'hp' => 218,
            'max_hp' => 218,
            'mp' => 0,
            'max_mp' => 111,
            'atk' => 55,
            'def' => 25,
            'inventory' => [],
            'class_history' => ['normal', 'mage'],
        ]);

        $this->postJson("/game/player/{$player->id}/roll-encounter", [
            'level_dice' => 2,
            'count_dice' => 1,
        ])->assertOk();

        $fight = $this->postJson("/game/player/{$player->id}/fight", [
            'skill_id' => 'fireball',
            'dice' => 3,
            'monster_dice' => 1,
        ]);

        $fight->assertOk()
            ->assertJsonPath('battle.turns.0.skill', null)
            ->assertJsonPath('battle.turns.0.manaCost', 0)
            ->assertJsonPath('battle.turns.0.playerMp', 0);
    }

    public function test_player_can_target_a_specific_monster(): void
    {
        $this->seed(GameSeedSeeder::class);

        $bootstrap = $this->postJson('/game/bootstrap', [
            'browserToken' => 'target-token',
            'name' => 'Target Tester',
        ]);

        $playerId = $bootstrap->json('player.id');
        $encounter = $this->postJson("/game/player/{$playerId}/roll-encounter", [
            'level_dice' => 4,
            'count_dice' => 4,
        ])->assertOk();

        $targetId = $encounter->json('encounter.monsters.1.id');

        $fight = $this->postJson("/game/player/{$playerId}/fight", [
            'skill_id' => 'punch',
            'target_monster_id' => $targetId,
            'dice' => 3,
            'monster_dice' => 1,
        ]);

        $fight->assertOk()
            ->assertJsonPath('battle.turns.0.targetIndex', 1);
    }

    public function test_skill_cooldown_blocks_reuse_until_later_turn(): void
    {
        $this->seed(GameSeedSeeder::class);

        $player = Player::create([
            'browser_token' => 'cooldown-token',
            'name' => 'Cooldown Tester',
            'level' => 10,
            'class_id' => 'mage',
            'exp' => 0,
            'hp' => 218,
            'max_hp' => 218,
            'mp' => 111,
            'max_mp' => 111,
            'atk' => 55,
            'def' => 25,
            'inventory' => [],
            'class_history' => ['normal', 'mage'],
        ]);

        $this->postJson("/game/player/{$player->id}/roll-encounter", [
            'level_dice' => 6,
            'count_dice' => 6,
        ])->assertOk();

        $first = $this->postJson("/game/player/{$player->id}/fight", [
            'skill_id' => 'meteor_seed',
            'dice' => 3,
            'monster_dice' => 1,
        ])->assertOk();

        $first->assertJsonPath('battle.turns.0.skill.id', 'meteor_seed');
        $meteor = collect($first->json('skills'))->firstWhere('id', 'meteor_seed');
        $this->assertSame(2, $meteor['cooldown_remaining']);

        $second = $this->postJson("/game/player/{$player->id}/fight", [
            'skill_id' => 'meteor_seed',
            'dice' => 3,
            'monster_dice' => 1,
        ])->assertOk();

        $second->assertJsonPath('battle.turns.0.skill', null);
    }

    public function test_monster_hp_scales_up_with_level(): void
    {
        $this->seed(GameSeedSeeder::class);

        $lowPlayer = Player::create([
            'browser_token' => 'low-monster-token',
            'name' => 'Low Tester',
            'level' => 1,
            'class_id' => 'normal',
            'exp' => 0,
            'hp' => 100,
            'max_hp' => 100,
            'mp' => 30,
            'max_mp' => 30,
            'atk' => 10,
            'def' => 5,
            'inventory' => [],
            'class_history' => ['normal'],
        ]);

        $highPlayer = Player::create([
            'browser_token' => 'high-monster-token',
            'name' => 'High Tester',
            'level' => 20,
            'class_id' => 'dragon_knight',
            'exp' => 0,
            'hp' => 408,
            'max_hp' => 408,
            'mp' => 126,
            'max_mp' => 126,
            'atk' => 102,
            'def' => 68,
            'inventory' => [],
            'class_history' => ['normal', 'cavalry', 'dragon_knight'],
        ]);

        $low = $this->postJson("/game/player/{$lowPlayer->id}/roll-encounter", [
            'level_dice' => 4,
            'count_dice' => 1,
        ])->assertOk();

        $high = $this->postJson("/game/player/{$highPlayer->id}/roll-encounter", [
            'level_dice' => 4,
            'count_dice' => 1,
        ])->assertOk();

        $this->assertGreaterThan(
            $low->json('encounter.monsters.0.hp'),
            $high->json('encounter.monsters.0.hp')
        );
    }

    public function test_world_chat_and_pvp_leaderboard_work(): void
    {
        $this->seed(GameSeedSeeder::class);

        $hero = $this->postJson('/game/bootstrap', [
            'browserToken' => 'pvp-hero-token',
            'name' => 'Hero',
        ])->assertOk();

        $rival = $this->postJson('/game/bootstrap', [
            'browserToken' => 'pvp-rival-token',
            'name' => 'Rival',
        ])->assertOk();

        $heroId = $hero->json('player.id');
        $rivalId = $rival->json('player.id');

        $this->postJson("/game/player/{$heroId}/chat", [
            'message' => 'hello world',
        ])->assertOk()
            ->assertJsonPath('totalPlayers', 2)
            ->assertJsonPath('onlineCount', 2)
            ->assertJsonPath('chatMessages.0.message', 'hello world');

        $this->postJson("/game/player/{$heroId}/pvp/start", [])->assertOk()
            ->assertJsonPath('pvp', null)
            ->assertJsonPath('pvpQueue.waiting', true);

        $this->postJson("/game/player/{$rivalId}/pvp/start", [])->assertOk()
            ->assertJsonPath('pvp.opponent.name', 'Hero');

        $fight = null;
        for ($i = 0; $i < 12; $i++) {
            $fight = $this->postJson("/game/player/{$heroId}/pvp/fight", [
                'skill_id' => 'punch',
                'dice' => 6,
                'opponent_dice' => 1,
            ])->assertOk();

            if ($fight->json('pvpBattle.won')) {
                break;
            }
        }

        $fight->assertJsonPath('pvpBattle.won', true);
        $this->assertGreaterThan(1000, Player::find($heroId)->pvp_rating);
        $this->assertGreaterThan(0, Player::find($heroId)->pvp_wins);
    }

    public function test_pvp_arena_requires_another_online_player(): void
    {
        $this->seed(GameSeedSeeder::class);

        $hero = $this->postJson('/game/bootstrap', [
            'browserToken' => 'solo-pvp-token',
            'name' => 'Solo',
        ])->assertOk();

        $heroId = $hero->json('player.id');

        $this->postJson("/game/player/{$heroId}/pvp/start", [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'ต้องมีผู้เล่นอื่นออนไลน์ก่อนถึงจะเข้าลานประลองได้');
    }

    public function test_bot_pvp_uses_separate_leaderboard_from_world_pvp(): void
    {
        $this->seed(GameSeedSeeder::class);

        $hero = $this->postJson('/game/bootstrap', [
            'browserToken' => 'bot-pvp-token',
            'name' => 'Bot Hunter',
        ])->assertOk();

        $heroId = $hero->json('player.id');

        $this->postJson("/game/player/{$heroId}/pvp/bot/start", [])
            ->assertOk()
            ->assertJsonPath('pvp.is_bot', true);

        $fight = null;
        for ($i = 0; $i < 40; $i++) {
            $fight = $this->postJson("/game/player/{$heroId}/pvp/fight", [
                'skill_id' => 'punch',
                'dice' => 6,
                'opponent_dice' => 1,
            ])->assertOk();

            if ($fight->json('pvpBattle.won')) {
                break;
            }
        }

        $fight->assertJsonPath('pvpBattle.won', true)
            ->assertJsonPath('pvpBattle.isBot', true);

        $player = Player::find($heroId);
        $this->assertSame(1000, $player->pvp_rating);
        $this->assertGreaterThan(1000, $player->bot_rating);
        $this->assertGreaterThan(0, $player->bot_wins);
    }
}
