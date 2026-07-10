<?php

namespace App\Http\Controllers;

use App\Models\CharacterClass;
use App\Models\ChatMessage;
use App\Models\ClassEvolution;
use App\Models\Monster;
use App\Models\Player;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        return view('game', [
            'googleReady' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret'))
                && filled(config('services.google.redirect')),
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'browserToken' => ['required', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $request->user();
        if (! $user && ! app()->environment('testing')) {
            return response()->json([
                'message' => 'ต้องเข้าสู่ระบบด้วย Google ก่อนเข้าเล่น',
                'loginUrl' => route('auth.google'),
            ], 401);
        }

        if ($user) {
            $player = Player::where('user_id', $user->id)->first();

            if (! $player) {
                $player = Player::where('browser_token', $data['browserToken'])
                    ->whereNull('user_id')
                    ->first();
            }

            if ($player) {
                $player->forceFill([
                    'user_id' => $user->id,
                    'browser_token' => $data['browserToken'],
                    'name' => $player->name ?: $user->name,
                ])->save();
            } else {
                $player = Player::create([
                    'user_id' => $user->id,
                    'browser_token' => $data['browserToken'],
                    'name' => $data['name'] ?? $user->name,
                    'element' => 'earth',
                    'inventory' => [],
                    'class_history' => ['normal'],
                ]);
            }
        } else {
            $player = Player::firstOrCreate(
                ['browser_token' => $data['browserToken']],
                [
                    'name' => $data['name'] ?? 'Adventurer',
                    'element' => 'earth',
                    'inventory' => [],
                    'class_history' => ['normal'],
                ]
            );
        }

        $player->forceFill(['last_seen_at' => now()])->save();

        return response()->json($this->gamePayload($request, $player->fresh()));
    }

    public function world(): JsonResponse
    {
        return response()->json($this->worldPayload());
    }

    public function heartbeat(Player $player): JsonResponse
    {
        $player->forceFill(['last_seen_at' => now()])->save();

        return response()->json($this->worldPayload($player->fresh()));
    }

    public function chat(Request $request, Player $player): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:240'],
        ]);

        $message = trim($data['message']);
        if ($message === '') {
            return response()->json(['message' => 'Chat message is empty.'], 422);
        }

        $player->forceFill(['last_seen_at' => now()])->save();
        ChatMessage::create([
            'player_id' => $player->id,
            'message' => $message,
        ]);

        return response()->json($this->worldPayload($player->fresh()));
    }

    public function rename(Request $request, Player $player): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $player->update(['name' => $data['name']]);

        return response()->json($this->gamePayload($request, $player->fresh()));
    }

    public function chooseElement(Request $request, Player $player): JsonResponse
    {
        $data = $request->validate([
            'element' => ['required', 'string', 'in:earth,water,wind,fire'],
        ]);

        $player->forceFill([
            'element' => $data['element'],
            'last_seen_at' => now(),
        ])->save();

        return response()->json($this->gamePayload($request, $player->fresh()));
    }

    public function rollEncounter(Request $request, Player $player): JsonResponse
    {
        $data = $request->validate([
            'level_dice' => ['nullable', 'integer', 'between:1,6'],
            'count_dice' => ['nullable', 'integer', 'between:1,6'],
            'monster_element' => ['nullable', 'string', 'in:earth,water,wind,fire,neutral'],
        ]);

        $encounter = $this->generateEncounter(
            $player,
            $this->rollDice($data['level_dice'] ?? null),
            $this->rollDice($data['count_dice'] ?? null),
            app()->environment('testing') ? ($data['monster_element'] ?? null) : null
        );

        $request->session()->put($this->encounterKey($player), $encounter);

        return response()->json($this->gamePayload($request, $player->fresh()));
    }

    public function fight(Request $request, Player $player): JsonResponse
    {
        $data = $request->validate([
            'skill_id' => ['nullable', 'string', 'exists:skills,id'],
            'target_monster_id' => ['nullable', 'string'],
            'dice' => ['nullable', 'integer', 'between:1,6'],
            'monster_dice' => ['nullable', 'integer', 'between:1,6'],
        ]);

        $encounter = $request->session()->get($this->encounterKey($player));
        if (! $encounter) {
            $encounter = $this->generateEncounter($player, $this->rollDice(), $this->rollDice());
            $request->session()->put($this->encounterKey($player), $encounter);
        }

        $skill = $this->classSkill($player, $data['skill_id'] ?? null);
        $stats = $this->playerStats($player);
        $playerHp = $player->hp > 0 ? $player->hp : $stats['max_hp'];
        $playerMp = min($player->mp, $stats['max_mp']);
        $turns = [];

        $targetIndex = $this->targetMonsterIndex($encounter, $data['target_monster_id'] ?? null);
        if ($targetIndex === null) {
            return response()->json(['message' => 'No living monster target.'], 422);
        }

        $turnNumber = (int) ($encounter['turn'] ?? 1);
        $monster = $encounter['monsters'][$targetIndex];
        $activeSkill = $this->usableSkill($skill, $playerMp, $encounter, $turnNumber);
        $manaCost = (int) ($activeSkill?->mana_cost ?? 0);
        $playerMp = max(0, $playerMp - $manaCost);
        $skillDamage = ($activeSkill ? $activeSkill->damage + ($player->int_stat * 2) : 0);
        $baseDamage = max(1, $stats['atk'] + $skillDamage - $monster['def'] + random_int(-3, 6));
        $skillBonus = $this->skillBonusMultiplier($activeSkill);
        $baseDamage = max(1, (int) floor($baseDamage * $skillBonus));
        $dice = $this->rollDice($data['dice'] ?? null);
        $diceResult = $this->diceResult($dice);
        $playerDamage = $this->applyDiceDamage($baseDamage, $diceResult);
        $playerElement = $this->elementPayload($player->element, $monster['element'] ?? 'neutral');
        $playerDamage = $this->applyElementDamage($playerDamage, $playerElement);
        $encounter['monsters'][$targetIndex]['current_hp'] = max(0, $monster['current_hp'] - $playerDamage);
        $attackName = $activeSkill?->name ?? 'Basic Attack';
        $playerElementText = $this->elementText($playerElement);

        if ($activeSkill && $activeSkill->cooldown > 0) {
            $encounter['cooldowns'][$activeSkill->id] = $turnNumber + $activeSkill->cooldown + 1;
        }

        $turns[] = [
            'actor' => 'player',
            'targetIndex' => $targetIndex,
            'text' => "{$player->name} rolled {$dice} with {$attackName}: {$diceResult['label']} for {$playerDamage}{$playerElementText}",
            'skill' => $activeSkill,
            'manaCost' => $manaCost,
            'playerMp' => $playerMp,
            'damage' => $playerDamage,
            'baseDamage' => $baseDamage,
            'element' => $playerElement,
            'elementMultiplier' => $playerElement['multiplier'],
            'skillBonus' => $skillBonus,
            'dice' => $dice,
            'result' => $diceResult['key'],
            'label' => $diceResult['label'],
            'effect' => $diceResult['effect'],
            'multiplier' => $diceResult['multiplier'],
            'monsterHp' => $encounter['monsters'][$targetIndex]['current_hp'],
            'playerHp' => $playerHp,
        ];

        if ($encounter['monsters'][$targetIndex]['current_hp'] <= 0) {
            $turns[] = [
                'actor' => 'system',
                'type' => 'monster_defeated',
                'text' => "{$monster['name']} defeated",
                'monster' => $encounter['monsters'][$targetIndex],
                'targetIndex' => $targetIndex,
                'monsterHp' => 0,
                'playerHp' => $playerHp,
            ];
        } else {
            $monsterBaseDamage = max(1, $monster['atk'] - $stats['def'] + random_int(-2, 5));
            $monsterDice = $this->rollDice($data['monster_dice'] ?? null);
            $monsterDiceResult = $this->diceResult($monsterDice);
            $monsterDamage = $this->applyDiceDamage($monsterBaseDamage, $monsterDiceResult);
            $monsterElement = $this->elementPayload($monster['element'] ?? 'neutral', $player->element);
            $monsterDamage = $this->applyElementDamage($monsterDamage, $monsterElement);
            $playerHp = max(0, $playerHp - $monsterDamage);
            $monsterElementText = $this->elementText($monsterElement);

            $turns[] = [
                'actor' => 'monster',
                'targetIndex' => $targetIndex,
                'text' => "{$monster['name']} rolled {$monsterDice}: {$monsterDiceResult['label']} countered for {$monsterDamage}{$monsterElementText}",
                'damage' => $monsterDamage,
                'baseDamage' => $monsterBaseDamage,
                'element' => $monsterElement,
                'elementMultiplier' => $monsterElement['multiplier'],
                'dice' => $monsterDice,
                'result' => $monsterDiceResult['key'],
                'label' => $monsterDiceResult['label'],
                'effect' => $monsterDiceResult['effect'],
                'multiplier' => $monsterDiceResult['multiplier'],
                'monsterHp' => $encounter['monsters'][$targetIndex]['current_hp'],
                'playerHp' => $playerHp,
            ];
        }

        $encounter['turn'] = $turnNumber + 1;
        $won = collect($encounter['monsters'])->every(fn (array $monster) => $monster['current_hp'] <= 0);
        $playerDefeated = $playerHp <= 0;
        $expGained = $won ? array_sum(array_column($encounter['monsters'], 'exp_reward')) : 0;

        $bossReward = null;
        if ($won && $encounter['isBoss']) {
            $bossReward = $this->grantBossReward($player);
        }

        $levelSummary = $this->addExp($player, $expGained);
        $freshStats = $this->playerStats($player->fresh());
        $mpGainedFromProgression = max(0, $freshStats['max_mp'] - $stats['max_mp']);

        $player->forceFill([
            'hp' => $won ? $freshStats['max_hp'] : max(1, $playerHp),
            'mp' => min($freshStats['max_mp'], $playerMp + $mpGainedFromProgression),
            'max_hp' => $freshStats['max_hp'],
            'max_mp' => $freshStats['max_mp'],
            'atk' => $freshStats['atk'],
            'def' => $freshStats['def'],
        ])->save();

        if ($won || $playerDefeated) {
            $request->session()->forget($this->encounterKey($player));
        } else {
            $request->session()->put($this->encounterKey($player), $encounter);
        }

        return response()->json([
            ...$this->gamePayload($request, $player->fresh()),
            'battle' => [
                'won' => $won,
                'playerDefeated' => $playerDefeated,
                'expGained' => $expGained,
                'bossReward' => $bossReward,
                'monster' => $encounter['monsters'][$targetIndex],
                'encounter' => $encounter,
                'skill' => $skill,
                'turns' => $turns,
                'levelSummary' => $levelSummary,
            ],
        ]);
    }

    public function startPvp(Request $request, Player $player): JsonResponse
    {
        $request->validate([
            'opponent_id' => ['nullable', 'integer', 'exists:players,id'],
        ]);

        $onlineCutoff = now()->subMinutes(5);
        $queueCutoff = now()->subSeconds(45);
        Player::where('pvp_queue_at', '<', $queueCutoff)->update(['pvp_queue_at' => null]);

        $player->refresh();
        if ($player->pvp_match) {
            $request->session()->put($this->pvpKey($player), $player->pvp_match);
            $player->forceFill(['last_seen_at' => now()])->save();

            return response()->json($this->gamePayload($request, $player->fresh()));
        }

        $onlinePlayers = Player::where('id', '!=', $player->id)
            ->where('last_seen_at', '>=', $onlineCutoff)
            ->count();

        if ($onlinePlayers < 1) {
            $player->forceFill([
                'last_seen_at' => now(),
                'pvp_queue_at' => null,
                'pvp_match' => null,
            ])->save();

            return response()->json(['message' => 'ต้องมีผู้เล่นอื่นออนไลน์ก่อนถึงจะเข้าลานประลองได้'], 422);
        }

        $opponent = Player::query()
            ->where('id', '!=', $player->id)
            ->where('last_seen_at', '>=', $onlineCutoff)
            ->where('pvp_queue_at', '>=', $queueCutoff)
            ->orderBy('pvp_queue_at')
            ->first();

        if (! $opponent) {
            $player->forceFill([
                'last_seen_at' => now(),
                'pvp_queue_at' => now(),
                'pvp_match' => null,
            ])->save();
            $request->session()->forget($this->pvpKey($player));

            return response()->json($this->gamePayload($request, $player->fresh()));
        }

        $freshPlayer = $player->fresh();
        $freshOpponent = $opponent->fresh();
        $pvp = $this->generatePvpEncounter($freshPlayer, $freshOpponent);
        $opponentPvp = $this->generatePvpEncounter($freshOpponent, $freshPlayer);

        $freshPlayer->forceFill([
            'last_seen_at' => now(),
            'pvp_queue_at' => null,
            'pvp_match' => $pvp,
        ])->save();
        $freshOpponent->forceFill([
            'pvp_queue_at' => null,
            'pvp_match' => $opponentPvp,
        ])->save();

        $request->session()->put($this->pvpKey($player), $pvp);

        return response()->json($this->gamePayload($request, $freshPlayer->fresh()));
    }

    public function startBotPvp(Request $request, Player $player): JsonResponse
    {
        $pvp = $this->generateBotPvpEncounter($player->fresh());

        $player->forceFill([
            'last_seen_at' => now(),
            'pvp_queue_at' => null,
            'pvp_match' => null,
        ])->save();
        $request->session()->put($this->pvpKey($player), $pvp);

        return response()->json($this->gamePayload($request, $player->fresh()));
    }

    public function fightPvp(Request $request, Player $player): JsonResponse
    {
        $data = $request->validate([
            'skill_id' => ['nullable', 'string', 'exists:skills,id'],
            'dice' => ['nullable', 'integer', 'between:1,6'],
            'opponent_dice' => ['nullable', 'integer', 'between:1,6'],
        ]);

        $pvp = $request->session()->get($this->pvpKey($player)) ?? $player->pvp_match;
        if (! $pvp) {
            return response()->json(['message' => 'ต้องเข้าคิวและจับคู่ลานประลองก่อน'], 422);
        }

        $skill = $this->classSkill($player, $data['skill_id'] ?? null);
        $stats = $this->playerStats($player);
        $turnNumber = (int) ($pvp['turn'] ?? 1);
        $playerHp = max(1, (int) $pvp['player']['current_hp']);
        $playerMp = max(0, (int) $pvp['player']['current_mp']);
        $opponentHp = max(0, (int) $pvp['opponent']['current_hp']);
        $opponentMp = max(0, (int) $pvp['opponent']['current_mp']);
        $turns = [];

        $activeSkill = $this->usableSkill($skill, $playerMp, $pvp, $turnNumber);
        $manaCost = (int) ($activeSkill?->mana_cost ?? 0);
        $playerMp = max(0, $playerMp - $manaCost);
        $skillDamage = ($activeSkill ? $activeSkill->damage + ($player->int_stat * 2) : 0);
        $baseDamage = max(1, $stats['atk'] + $skillDamage - (int) $pvp['opponent']['def'] + random_int(-3, 6));
        $skillBonus = $this->skillBonusMultiplier($activeSkill);
        $baseDamage = max(1, (int) floor($baseDamage * $skillBonus));
        $dice = $this->rollDice($data['dice'] ?? null);
        $diceResult = $this->diceResult($dice);
        $playerDamage = $this->applyDiceDamage($baseDamage, $diceResult);
        $playerElement = $this->elementPayload($pvp['player']['element'] ?? $player->element, $pvp['opponent']['element'] ?? 'earth');
        $playerDamage = $this->applyElementDamage($playerDamage, $playerElement);
        $opponentHp = max(0, $opponentHp - $playerDamage);
        $attackName = $activeSkill?->name ?? 'Basic Attack';
        $playerElementText = $this->elementText($playerElement);

        if ($activeSkill && $activeSkill->cooldown > 0) {
            $pvp['cooldowns'][$activeSkill->id] = $turnNumber + $activeSkill->cooldown + 1;
        }

        $turns[] = [
            'actor' => 'player',
            'text' => "{$player->name} rolled {$dice} with {$attackName}: {$diceResult['label']} for {$playerDamage}{$playerElementText}",
            'skill' => $activeSkill,
            'manaCost' => $manaCost,
            'damage' => $playerDamage,
            'element' => $playerElement,
            'elementMultiplier' => $playerElement['multiplier'],
            'dice' => $dice,
            'result' => $diceResult['key'],
            'label' => $diceResult['label'],
            'effect' => $diceResult['effect'],
            'multiplier' => $diceResult['multiplier'],
            'opponentHp' => $opponentHp,
            'playerHp' => $playerHp,
        ];

        if ($opponentHp > 0) {
            $opponentBaseDamage = max(1, (int) $pvp['opponent']['atk'] - $stats['def'] + random_int(-2, 5));
            $opponentDice = $this->rollDice($data['opponent_dice'] ?? null);
            $opponentDiceResult = $this->diceResult($opponentDice);
            $opponentDamage = $this->applyDiceDamage($opponentBaseDamage, $opponentDiceResult);
            $opponentElement = $this->elementPayload($pvp['opponent']['element'] ?? 'earth', $pvp['player']['element'] ?? $player->element);
            $opponentDamage = $this->applyElementDamage($opponentDamage, $opponentElement);
            $playerHp = max(0, $playerHp - $opponentDamage);
            $opponentElementText = $this->elementText($opponentElement);

            $turns[] = [
                'actor' => 'opponent',
                'text' => "{$pvp['opponent']['name']} rolled {$opponentDice}: {$opponentDiceResult['label']} countered for {$opponentDamage}{$opponentElementText}",
                'damage' => $opponentDamage,
                'element' => $opponentElement,
                'elementMultiplier' => $opponentElement['multiplier'],
                'dice' => $opponentDice,
                'result' => $opponentDiceResult['key'],
                'label' => $opponentDiceResult['label'],
                'effect' => $opponentDiceResult['effect'],
                'multiplier' => $opponentDiceResult['multiplier'],
                'opponentHp' => $opponentHp,
                'playerHp' => $playerHp,
            ];
        }

        $won = $opponentHp <= 0;
        $lost = $playerHp <= 0;
        $pvp['turn'] = $turnNumber + 1;
        $pvp['player']['current_hp'] = $playerHp;
        $pvp['player']['current_mp'] = $playerMp;
        $pvp['opponent']['current_hp'] = $opponentHp;
        $pvp['opponent']['current_mp'] = $opponentMp;

        if ($won || $lost) {
            if ($pvp['is_bot'] ?? false) {
                $this->recordBotPvpResult($player, $won);
            } else {
                $this->recordPvpResult($player, (int) $pvp['opponent']['id'], $won);
            }
            $request->session()->forget($this->pvpKey($player));
            if (! ($pvp['is_bot'] ?? false)) {
                Player::whereIn('id', [$player->id, (int) $pvp['opponent']['id']])->update([
                    'pvp_queue_at' => null,
                    'pvp_match' => null,
                ]);
            }
        } else {
            $request->session()->put($this->pvpKey($player), $pvp);
            if (! ($pvp['is_bot'] ?? false)) {
                $player->forceFill(['pvp_match' => $pvp])->save();
            }
        }

        $player->forceFill([
            'hp' => $won || $lost ? $stats['max_hp'] : max(1, $playerHp),
            'mp' => $won || $lost ? $stats['max_mp'] : $playerMp,
            'max_hp' => $stats['max_hp'],
            'max_mp' => $stats['max_mp'],
            'atk' => $stats['atk'],
            'def' => $stats['def'],
            'last_seen_at' => now(),
            'pvp_queue_at' => null,
        ])->save();

        return response()->json([
            ...$this->gamePayload($request, $player->fresh()),
            'pvpBattle' => [
                'won' => $won,
                'lost' => $lost,
                'isBot' => (bool) ($pvp['is_bot'] ?? false),
                'turns' => $turns,
                'encounter' => $pvp,
                'ratingChange' => ($pvp['is_bot'] ?? false) ? ($won ? 8 : ($lost ? -4 : 0)) : ($won ? 12 : ($lost ? -8 : 0)),
            ],
        ]);
    }

    public function changeClass(Request $request, Player $player): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'string', 'exists:character_classes,id'],
        ]);

        $canChange = $this->availableEvolutions($player)
            ->contains(fn (CharacterClass $class) => $class->id === $data['class_id']);

        if (! $canChange) {
            return response()->json(['message' => 'This class is not available yet.'], 422);
        }

        $history = $player->class_history ?? [];
        $history[] = $data['class_id'];
        $player->forceFill([
            'class_id' => $data['class_id'],
            'class_history' => $history,
        ])->save();

        $stats = $this->playerStats($player->fresh());
        $player->forceFill([
            'hp' => $stats['max_hp'],
            'mp' => $stats['max_mp'],
            'max_hp' => $stats['max_hp'],
            'max_mp' => $stats['max_mp'],
            'atk' => $stats['atk'],
            'def' => $stats['def'],
        ])->save();

        return response()->json($this->gamePayload($request, $player->fresh()));
    }

    private function gamePayload(Request $request, Player $player): array
    {
        $skillClassIds = [$player->class_id];
        $encounter = $request->session()->get($this->encounterKey($player));
        $pvp = $request->session()->get($this->pvpKey($player)) ?? $player->pvp_match;
        if ($pvp) {
            $request->session()->put($this->pvpKey($player), $pvp);
        }

        return [
            'player' => $player,
            'class' => CharacterClass::find($player->class_id),
            'nextExp' => $this->expRequired($player->level),
            'availableEvolutions' => $this->availableEvolutions($player)->values(),
            'classEvolutionTree' => $this->classEvolutionTree($player),
            'skills' => Skill::whereIn('class_id', $skillClassIds)
                ->orderByRaw('case when class_id = ? then 0 else 1 end', [$player->class_id])
                ->get()
                ->map(fn (Skill $skill) => $this->skillPayload($skill, $encounter))
                ->unique('id')
                ->values(),
            'encounter' => $encounter,
            'pvp' => $pvp,
            'pvpQueue' => [
                'waiting' => $player->pvp_queue_at !== null && ! $pvp,
                'joinedAt' => $player->pvp_queue_at?->toIso8601String(),
                'windowSeconds' => 45,
            ],
            'world' => $this->worldPayload($player),
            'csrfToken' => csrf_token(),
        ];
    }

    private function worldPayload(?Player $currentPlayer = null): array
    {
        $onlineCutoff = now()->subMinutes(5);

        return [
            'totalPlayers' => Player::count(),
            'onlineCount' => Player::where('last_seen_at', '>=', $onlineCutoff)->count(),
            'onlinePlayers' => Player::query()
                ->select(['id', 'name', 'level', 'class_id', 'element', 'pvp_wins', 'pvp_losses', 'pvp_rating', 'pvp_queue_at', 'last_seen_at'])
                ->where('last_seen_at', '>=', $onlineCutoff)
                ->when($currentPlayer, fn ($query) => $query->where('id', '!=', $currentPlayer->id))
                ->orderByDesc('level')
                ->orderByDesc('pvp_rating')
                ->limit(30)
                ->get(),
            'leaderboard' => Player::query()
                ->select(['id', 'name', 'level', 'class_id', 'element', 'pvp_wins', 'pvp_losses', 'pvp_rating'])
                ->orderByDesc('pvp_rating')
                ->orderByDesc('pvp_wins')
                ->orderBy('pvp_losses')
                ->limit(10)
                ->get(),
            'botLeaderboard' => Player::query()
                ->select(['id', 'name', 'level', 'class_id', 'element', 'bot_wins', 'bot_losses', 'bot_rating'])
                ->orderByDesc('bot_rating')
                ->orderByDesc('bot_wins')
                ->orderBy('bot_losses')
                ->limit(10)
                ->get(),
            'chatMessages' => ChatMessage::query()
                ->with('player:id,name')
                ->latest()
                ->limit(30)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (ChatMessage $message) => [
                    'id' => $message->id,
                    'player_id' => $message->player_id,
                    'player_name' => $message->player?->name ?? 'Unknown',
                    'message' => $message->message,
                    'created_at' => $message->created_at?->toIso8601String(),
                ]),
        ];
    }

    private function generatePvpEncounter(Player $player, Player $opponent): array
    {
        $playerStats = $this->playerStats($player);
        $opponentStats = $this->playerStats($opponent);

        return [
            'id' => uniqid('pvp_', true),
            'is_bot' => false,
            'turn' => 1,
            'cooldowns' => [],
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'level' => $player->level,
                'class_id' => $player->class_id,
                'element' => $player->element,
                'element_label' => $this->elementLabel($player->element),
                'element_color' => $this->elementColor($player->element),
                'hp' => $playerStats['max_hp'],
                'current_hp' => $playerStats['max_hp'],
                'mp' => $playerStats['max_mp'],
                'current_mp' => min($player->mp, $playerStats['max_mp']),
                'atk' => $playerStats['atk'],
                'def' => $playerStats['def'],
            ],
            'opponent' => [
                'id' => $opponent->id,
                'name' => $opponent->name,
                'level' => $opponent->level,
                'class_id' => $opponent->class_id,
                'element' => $opponent->element,
                'element_label' => $this->elementLabel($opponent->element),
                'element_color' => $this->elementColor($opponent->element),
                'hp' => $opponentStats['max_hp'],
                'current_hp' => $opponentStats['max_hp'],
                'mp' => $opponentStats['max_mp'],
                'current_mp' => min($opponent->mp, $opponentStats['max_mp']),
                'atk' => $opponentStats['atk'],
                'def' => $opponentStats['def'],
            ],
        ];
    }

    private function generateBotPvpEncounter(Player $player): array
    {
        $playerStats = $this->playerStats($player);
        $botLevel = max(1, min(100, $player->level + random_int(-2, 2)));
        $botClass = $this->randomClassForLevel($botLevel);
        $botClassId = $botClass?->id ?? 'normal';
        $botElement = ['earth', 'water', 'wind', 'fire'][array_rand(['earth', 'water', 'wind', 'fire'])];
        $botStats = $this->botStats($botLevel, $botClassId);

        return [
            'id' => uniqid('bot_pvp_', true),
            'is_bot' => true,
            'turn' => 1,
            'cooldowns' => [],
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'level' => $player->level,
                'class_id' => $player->class_id,
                'element' => $player->element,
                'element_label' => $this->elementLabel($player->element),
                'element_color' => $this->elementColor($player->element),
                'hp' => $playerStats['max_hp'],
                'current_hp' => $playerStats['max_hp'],
                'mp' => $playerStats['max_mp'],
                'current_mp' => min($player->mp, $playerStats['max_mp']),
                'atk' => $playerStats['atk'],
                'def' => $playerStats['def'],
            ],
            'opponent' => [
                'id' => 'bot',
                'name' => 'Bot '.random_int(100, 999),
                'level' => $botLevel,
                'class_id' => $botClassId,
                'class_name' => $botClass?->name,
                'element' => $botElement,
                'element_label' => $this->elementLabel($botElement),
                'element_color' => $this->elementColor($botElement),
                'hp' => $botStats['max_hp'],
                'current_hp' => $botStats['max_hp'],
                'mp' => $botStats['max_mp'],
                'current_mp' => $botStats['max_mp'],
                'atk' => $botStats['atk'],
                'def' => $botStats['def'],
                'bot' => true,
            ],
        ];
    }

    private function recordPvpResult(Player $player, int $opponentId, bool $won): void
    {
        $player->refresh();
        $player->increment($won ? 'pvp_wins' : 'pvp_losses');
        $player->increment('pvp_rating', $won ? 12 : -8);

        $opponent = Player::find($opponentId);
        if ($opponent) {
            $opponent->increment($won ? 'pvp_losses' : 'pvp_wins');
            $opponent->increment('pvp_rating', $won ? -6 : 10);
        }
    }

    private function recordBotPvpResult(Player $player, bool $won): void
    {
        $player->refresh();
        $player->increment($won ? 'bot_wins' : 'bot_losses');
        $player->increment('bot_rating', $won ? 8 : -4);
    }

    private function generateEncounter(Player $player, int $levelDice, int $countDice, ?string $forcedElement = null): array
    {
        $levelDelta = match ($levelDice) {
            6 => 2,
            5 => 1,
            4, 3 => 0,
            2 => -1,
            default => -2,
        };
        $targetLevel = max(1, min(100, $player->level + $levelDelta));
        $isBoss = $countDice === 6;
        $count = match ($countDice) {
            6 => 1,
            5 => random_int(3, 4),
            4, 3 => random_int(2, 3),
            default => random_int(1, 2),
        };

        $baseMonsters = $this->monsterFormsForLevel($targetLevel);

        if ($baseMonsters->isEmpty()) {
            $baseMonsters = Monster::limit(1)->get();
        }

        $monsters = [];
        $bossClass = $isBoss ? $this->nextBossClassForLevel($targetLevel) : null;
        for ($i = 0; $i < $count; $i++) {
            $base = $baseMonsters[$i % $baseMonsters->count()];
            $levelScale = max(1, $targetLevel) / max(1, $base->level);
            $element = $forcedElement ?? $this->randomMonsterElement();
            $classHpBonus = $isBoss ? (int) ($bossClass?->hp_bonus ?? 0) : 0;
            $classAtkBonus = $isBoss ? (int) ($bossClass?->atk_bonus ?? 0) : 0;
            $classDefBonus = $isBoss ? (int) ($bossClass?->def_bonus ?? 0) : 0;
            $monsterHp = $this->scaledMonsterHp($base, $targetLevel, $levelScale, $isBoss) + $classHpBonus;
            $monster = [
                'id' => "{$base->id}-{$i}",
                'base_id' => $base->id,
                'family_key' => $base->family_key,
                'evolution_stage' => $base->evolution_stage,
                'name' => $isBoss ? "Boss {$base->name}" : $base->name,
                'level' => $targetLevel,
                'hp' => $monsterHp,
                'current_hp' => $monsterHp,
                'atk' => max(1, (int) floor($base->atk * $levelScale * ($isBoss ? 1.6 : 1)) + $classAtkBonus),
                'def' => max(0, (int) floor($base->def * $levelScale * ($isBoss ? 1.35 : 1)) + $classDefBonus),
                'exp_reward' => max(1, (int) floor($base->exp_reward * $levelScale * ($isBoss ? 3.2 : 1))),
                'sprite_key' => $base->sprite_key,
                'is_boss' => $isBoss,
                'class_id' => $bossClass?->id,
                'class_name' => $bossClass?->name,
                'element' => $element,
                'element_label' => $this->elementLabel($element),
                'element_color' => $this->elementColor($element),
            ];
            $monsters[] = $monster;
        }

        return [
            'id' => uniqid('enc_', true),
            'levelDice' => $levelDice,
            'countDice' => $countDice,
            'levelDelta' => $levelDelta,
            'targetLevel' => $targetLevel,
            'isBoss' => $isBoss,
            'count' => $count,
            'label' => $isBoss ? 'Boss encounter' : "Normal encounter x{$count}",
            'turn' => 1,
            'cooldowns' => [],
            'monsters' => $monsters,
        ];
    }

    private function randomMonsterElement(): string
    {
        $elements = ['earth', 'water', 'wind', 'fire', 'neutral'];

        return $elements[array_rand($elements)];
    }

    private function elementPayload(?string $attacker, ?string $defender): array
    {
        $attacker = $attacker ?: 'neutral';
        $defender = $defender ?: 'neutral';
        $multiplier = $this->elementMultiplier($attacker, $defender);

        return [
            'attacker' => $attacker,
            'defender' => $defender,
            'attacker_label' => $this->elementLabel($attacker),
            'defender_label' => $this->elementLabel($defender),
            'attacker_color' => $this->elementColor($attacker),
            'defender_color' => $this->elementColor($defender),
            'multiplier' => $multiplier,
        ];
    }

    private function elementMultiplier(string $attacker, string $defender): float
    {
        if ($attacker === 'neutral' || $defender === 'neutral') {
            return 1.0;
        }

        $strongAgainst = [
            'earth' => 'water',
            'wind' => 'earth',
            'fire' => 'wind',
            'water' => 'fire',
        ];

        return ($strongAgainst[$attacker] ?? null) === $defender ? 1.5 : 1.0;
    }

    private function applyElementDamage(int $damage, array $element): int
    {
        if ($damage <= 0) {
            return 0;
        }

        return max(1, (int) floor($damage * (float) $element['multiplier']));
    }

    private function elementText(array $element): string
    {
        if ((float) $element['multiplier'] <= 1.0) {
            return '';
        }

        return " | {$element['attacker_label']}ชนะ{$element['defender_label']} x{$element['multiplier']}";
    }

    private function elementLabel(?string $element): string
    {
        return match ($element) {
            'earth' => 'ธาตุดิน',
            'water' => 'ธาตุน้ำ',
            'wind' => 'ธาตุลม',
            'fire' => 'ธาตุไฟ',
            default => 'ไร้ธาตุ',
        };
    }

    private function elementColor(?string $element): string
    {
        return match ($element) {
            'earth' => '#92400e',
            'water' => '#38bdf8',
            'wind' => '#22c55e',
            'fire' => '#ef4444',
            default => '#94a3b8',
        };
    }

    private function grantBossReward(Player $player): array
    {
        $stats = ['atk_stat', 'agi', 'vit', 'luk', 'int_stat'];
        $stat = $stats[array_rand($stats)];
        $player->increment($stat);

        return [
            'stat' => $stat,
            'amount' => 1,
        ];
    }

    private function playerStats(Player $player): array
    {
        $class = CharacterClass::find($player->class_id);
        $level = $player->level;

        return [
            'max_hp' => 100 + ($level - 1) * 12 + (int) ($class?->hp_bonus ?? 0) + ($player->vit * 8),
            'max_mp' => 30 + ($level - 1) * 4 + (int) ($class?->mp_bonus ?? 0) + ($player->int_stat * 3),
            'atk' => 10 + ($level - 1) * 3 + (int) ($class?->atk_bonus ?? 0) + ($player->atk_stat * 2),
            'def' => 5 + ($level - 1) * 2 + (int) ($class?->def_bonus ?? 0) + $player->vit,
        ];
    }

    private function botStats(int $level, string $classId): array
    {
        $class = CharacterClass::find($classId);

        return [
            'max_hp' => 95 + ($level - 1) * 11 + (int) ($class?->hp_bonus ?? 0),
            'max_mp' => 28 + ($level - 1) * 4 + (int) ($class?->mp_bonus ?? 0),
            'atk' => 9 + ($level - 1) * 3 + (int) ($class?->atk_bonus ?? 0),
            'def' => 4 + ($level - 1) * 2 + (int) ($class?->def_bonus ?? 0),
        ];
    }

    private function classSkill(Player $player, ?string $skillId): ?Skill
    {
        if ($skillId) {
            $skill = Skill::where('id', $skillId)
                ->where('class_id', $player->class_id)
                ->first();

            if ($skill) {
                return $skill;
            }
        }

        return Skill::where('class_id', $player->class_id)->first()
            ?? Skill::where('class_id', 'normal')->first();
    }

    private function usableSkill(?Skill $skill, int $currentMp, array $encounter, int $turnNumber): ?Skill
    {
        if (! $skill) {
            return null;
        }

        if ($currentMp < (int) $skill->mana_cost) {
            return null;
        }

        $availableTurn = (int) ($encounter['cooldowns'][$skill->id] ?? 0);

        return $turnNumber >= $availableTurn ? $skill : null;
    }

    private function skillPayload(Skill $skill, ?array $encounter): Skill
    {
        $turnNumber = (int) ($encounter['turn'] ?? 1);
        $availableTurn = (int) ($encounter['cooldowns'][$skill->id] ?? 0);
        $skill->setAttribute('cooldown_remaining', max(0, $availableTurn - $turnNumber));

        return $skill;
    }

    private function targetMonsterIndex(array $encounter, ?string $targetMonsterId): ?int
    {
        foreach ($encounter['monsters'] as $index => $monster) {
            if (($monster['current_hp'] ?? $monster['hp']) <= 0) {
                continue;
            }

            if ($targetMonsterId === null || $monster['id'] === $targetMonsterId) {
                return $index;
            }
        }

        return null;
    }

    private function scaledMonsterHp(Monster $base, int $targetLevel, float $levelScale, bool $isBoss): int
    {
        $levelBonus = ($targetLevel * 12) + (int) floor(($targetLevel ** 1.35) * 2);
        $normalHp = (int) floor(($base->hp * $levelScale) + $levelBonus);

        return max(20, (int) floor($normalHp * ($isBoss ? 2.4 : 1)));
    }

    private function monsterFormsForLevel(int $targetLevel)
    {
        $targetStage = intdiv(max(1, $targetLevel), 10);
        $families = Monster::whereNotNull('family_key')
            ->where('evolution_stage', 0)
            ->orderByRaw('abs(level - ?)', [$targetLevel])
            ->orderBy('level')
            ->limit(4)
            ->get();

        if ($families->isEmpty()) {
            return Monster::orderByRaw('abs(level - ?)', [$targetLevel])
                ->orderBy('level')
                ->limit(4)
                ->get();
        }

        return $families
            ->map(fn (Monster $base) => Monster::where('family_key', $base->family_key)
                ->where('evolution_stage', '<=', $targetStage)
                ->orderByDesc('evolution_stage')
                ->first() ?? $base)
            ->unique('id')
            ->values();
    }

    private function randomClassForLevel(int $level): ?CharacterClass
    {
        $milestone = CharacterClass::where('milestone_level', '<=', $level)
            ->max('milestone_level') ?? 1;

        $classes = CharacterClass::where('milestone_level', $milestone)
            ->orderBy('id')
            ->get();

        if ($classes->isEmpty()) {
            return CharacterClass::find('normal');
        }

        return $classes[random_int(0, $classes->count() - 1)];
    }

    private function nextBossClassForLevel(int $level): ?CharacterClass
    {
        $baseClass = $this->randomClassForLevel($level);
        if (! $baseClass) {
            return CharacterClass::find('normal');
        }

        $nextClassIds = ClassEvolution::where('from_class_id', $baseClass->id)
            ->orderBy('choice_order')
            ->pluck('to_class_id')
            ->all();

        if ($nextClassIds === []) {
            return $baseClass;
        }

        return CharacterClass::find($nextClassIds[random_int(0, count($nextClassIds) - 1)]) ?? $baseClass;
    }

    private function addExp(Player $player, int $expGained): array
    {
        $levelsGained = 0;
        $player->exp += $expGained;

        while ($player->level < 100 && $player->exp >= $this->expRequired($player->level)) {
            $player->exp -= $this->expRequired($player->level);
            $player->level++;
            $levelsGained++;
        }

        $player->save();

        return [
            'levelsGained' => $levelsGained,
            'leveledUp' => $levelsGained > 0,
            'currentLevel' => $player->level,
        ];
    }

    private function expRequired(int $level): int
    {
        if ($level >= 100) {
            return 0;
        }

        return 100 + (($level - 1) * 50) + (int) floor(($level ** 1.45) * 12);
    }

    private function diceResult(int $dice): array
    {
        return match ($dice) {
            6 => ['key' => 'super_critical', 'label' => 'SUPER CRITICAL x2', 'effect' => 'x2', 'multiplier' => 2.0],
            5 => ['key' => 'critical', 'label' => 'CRITICAL', 'effect' => 'critical', 'multiplier' => 1.4],
            4 => ['key' => 'armor_pierce', 'label' => 'Armor Pierce / Bleed', 'effect' => 'bleed', 'multiplier' => 1.2],
            3 => ['key' => 'normal', 'label' => 'Normal Hit', 'effect' => 'normal', 'multiplier' => 1.0],
            2 => ['key' => 'blocked', 'label' => 'Blocked', 'effect' => 'blocked', 'multiplier' => 0.8],
            default => ['key' => 'dodged', 'label' => 'Dodged', 'effect' => 'dodge', 'multiplier' => 0.0],
        };
    }

    private function rollDice(?int $forcedDice = null): int
    {
        if (app()->environment('testing') && $forcedDice !== null) {
            return $forcedDice;
        }

        return random_int(1, 6);
    }

    private function applyDiceDamage(int $baseDamage, array $diceResult): int
    {
        if ($diceResult['multiplier'] === 0.0) {
            return 0;
        }

        return max(1, (int) floor($baseDamage * $diceResult['multiplier']));
    }

    private function skillBonusMultiplier(?Skill $skill): float
    {
        if ($skill?->id === 'punch') {
            return 1.1;
        }

        return 1.0;
    }

    private function availableEvolutions(Player $player)
    {
        if ($player->level < 10 || $player->level % 10 !== 0) {
            return collect();
        }

        $history = $player->class_history ?? [];
        $alreadyChangedAtLevel = CharacterClass::whereIn('id', $history)
            ->where('milestone_level', $player->level)
            ->exists();

        if ($alreadyChangedAtLevel) {
            return collect();
        }

        $targetClassIds = ClassEvolution::where('from_class_id', $player->class_id)
            ->where('required_level', '<=', $player->level)
            ->orderBy('choice_order')
            ->pluck('to_class_id');

        return CharacterClass::whereIn('id', $targetClassIds)
            ->get()
            ->sortBy(fn (CharacterClass $class) => $targetClassIds->search($class->id));
    }

    private function classEvolutionTree(Player $player): array
    {
        $history = array_values(array_unique($player->class_history ?: ['normal']));
        if ($history === []) {
            $history = ['normal'];
        }

        $historyLookup = array_flip($history);
        $currentClassId = $player->class_id;
        $rows = [];

        foreach ($history as $index => $fromClassId) {
            $choices = ClassEvolution::where('from_class_id', $fromClassId)
                ->orderBy('choice_order')
                ->get();

            if ($choices->isEmpty()) {
                continue;
            }

            $selectedNextId = $history[$index + 1] ?? null;
            $targetClassIds = $choices->pluck('to_class_id');
            $classes = CharacterClass::whereIn('id', $targetClassIds)
                ->get()
                ->keyBy('id');
            $requiredLevel = (int) $choices->min('required_level');
            $canChooseAtThisRow = $selectedNextId === null
                && $fromClassId === $currentClassId
                && $player->level >= $requiredLevel
                && $player->level % 10 === 0;

            $rows[] = [
                'from_class_id' => $fromClassId,
                'from_class_name' => CharacterClass::find($fromClassId)?->name ?? $fromClassId,
                'required_level' => $requiredLevel,
                'is_current_branch' => $fromClassId === $currentClassId || isset($historyLookup[$fromClassId]),
                'choices' => $choices->map(function (ClassEvolution $choice) use ($classes, $historyLookup, $currentClassId, $selectedNextId, $canChooseAtThisRow, $player) {
                    $class = $classes->get($choice->to_class_id);
                    $status = 'locked';
                    $statusText = "ปลดล็อก LV {$choice->required_level}";
                    $canChoose = false;

                    if (isset($historyLookup[$choice->to_class_id])) {
                        $status = $choice->to_class_id === $currentClassId ? 'current' : 'completed';
                        $statusText = $status === 'current' ? 'สายปัจจุบัน' : 'ผ่านมาแล้ว';
                    } elseif ($selectedNextId !== null) {
                        $status = 'closed';
                        $statusText = 'ปิดเส้นทาง';
                    } elseif ($canChooseAtThisRow) {
                        $status = 'available';
                        $statusText = 'ปลดล็อกแล้ว';
                        $canChoose = true;
                    } elseif ($player->level >= $choice->required_level) {
                        $statusText = 'รอจังหวะเปลี่ยนคลาส';
                    }

                    return [
                        'id' => $choice->to_class_id,
                        'name' => $class?->name ?? $choice->to_class_id,
                        'required_level' => $choice->required_level,
                        'status' => $status,
                        'status_text' => $statusText,
                        'can_choose' => $canChoose,
                    ];
                })->values(),
            ];
        }

        return [
            'history' => CharacterClass::whereIn('id', $history)
                ->get()
                ->sortBy(fn (CharacterClass $class) => array_search($class->id, $history, true))
                ->map(fn (CharacterClass $class) => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'milestone_level' => $class->milestone_level,
                    'status' => $class->id === $currentClassId ? 'current' : 'completed',
                ])
                ->values(),
            'rows' => $rows,
        ];
    }

    private function encounterKey(Player $player): string
    {
        return "encounter_{$player->id}";
    }

    private function pvpKey(Player $player): string
    {
        return "pvp_{$player->id}";
    }
}
