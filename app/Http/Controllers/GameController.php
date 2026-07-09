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
        return view('game');
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'browserToken' => ['required', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $player = Player::firstOrCreate(
            ['browser_token' => $data['browserToken']],
            [
                'name' => $data['name'] ?? 'Adventurer',
                'element' => 'earth',
                'inventory' => [],
                'class_history' => ['normal'],
            ]
        );

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
        $data = $request->validate([
            'opponent_id' => ['required', 'integer', 'exists:players,id'],
        ]);

        if ((int) $data['opponent_id'] === $player->id) {
            return response()->json(['message' => 'You cannot duel yourself.'], 422);
        }

        $opponent = Player::findOrFail($data['opponent_id']);
        $pvp = $this->generatePvpEncounter($player->fresh(), $opponent->fresh());
        $request->session()->put($this->pvpKey($player), $pvp);
        $player->forceFill(['last_seen_at' => now()])->save();

        return response()->json($this->gamePayload($request, $player->fresh()));
    }

    public function fightPvp(Request $request, Player $player): JsonResponse
    {
        $data = $request->validate([
            'skill_id' => ['nullable', 'string', 'exists:skills,id'],
            'dice' => ['nullable', 'integer', 'between:1,6'],
            'opponent_dice' => ['nullable', 'integer', 'between:1,6'],
        ]);

        $pvp = $request->session()->get($this->pvpKey($player));
        if (! $pvp) {
            return response()->json(['message' => 'Enter the arena and choose an opponent first.'], 422);
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
            $this->recordPvpResult($player, (int) $pvp['opponent']['id'], $won);
            $request->session()->forget($this->pvpKey($player));
        } else {
            $request->session()->put($this->pvpKey($player), $pvp);
        }

        $player->forceFill([
            'hp' => $won || $lost ? $stats['max_hp'] : max(1, $playerHp),
            'mp' => $won || $lost ? $stats['max_mp'] : $playerMp,
            'max_hp' => $stats['max_hp'],
            'max_mp' => $stats['max_mp'],
            'atk' => $stats['atk'],
            'def' => $stats['def'],
            'last_seen_at' => now(),
        ])->save();

        return response()->json([
            ...$this->gamePayload($request, $player->fresh()),
            'pvpBattle' => [
                'won' => $won,
                'lost' => $lost,
                'turns' => $turns,
                'encounter' => $pvp,
                'ratingChange' => $won ? 12 : ($lost ? -8 : 0),
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
        $pvp = $request->session()->get($this->pvpKey($player));

        return [
            'player' => $player,
            'class' => CharacterClass::find($player->class_id),
            'nextExp' => $this->expRequired($player->level),
            'availableEvolutions' => $this->availableEvolutions($player)->values(),
            'skills' => Skill::whereIn('class_id', $skillClassIds)
                ->orderByRaw('case when class_id = ? then 0 else 1 end', [$player->class_id])
                ->get()
                ->map(fn (Skill $skill) => $this->skillPayload($skill, $encounter))
                ->unique('id')
                ->values(),
            'encounter' => $encounter,
            'pvp' => $pvp,
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
                ->select(['id', 'name', 'level', 'class_id', 'element', 'pvp_wins', 'pvp_losses', 'pvp_rating', 'last_seen_at'])
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

        $baseMonsters = Monster::orderByRaw('abs(level - ?)', [$targetLevel])
            ->orderBy('level')
            ->limit(4)
            ->get();

        if ($baseMonsters->isEmpty()) {
            $baseMonsters = Monster::limit(1)->get();
        }

        $monsters = [];
        for ($i = 0; $i < $count; $i++) {
            $base = $baseMonsters[$i % $baseMonsters->count()];
            $levelScale = max(1, $targetLevel) / max(1, $base->level);
            $element = $forcedElement ?? $this->randomMonsterElement();
            $monster = [
                'id' => "{$base->id}-{$i}",
                'base_id' => $base->id,
                'name' => $isBoss ? "Boss {$base->name}" : $base->name,
                'level' => $targetLevel,
                'hp' => $this->scaledMonsterHp($base, $targetLevel, $levelScale, $isBoss),
                'current_hp' => $this->scaledMonsterHp($base, $targetLevel, $levelScale, $isBoss),
                'atk' => max(1, (int) floor($base->atk * $levelScale * ($isBoss ? 1.6 : 1))),
                'def' => max(0, (int) floor($base->def * $levelScale * ($isBoss ? 1.35 : 1))),
                'exp_reward' => max(1, (int) floor($base->exp_reward * $levelScale * ($isBoss ? 3.2 : 1))),
                'sprite_key' => $base->sprite_key,
                'is_boss' => $isBoss,
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

    private function encounterKey(Player $player): string
    {
        return "encounter_{$player->id}";
    }

    private function pvpKey(Player $player): string
    {
        return "pvp_{$player->id}";
    }
}
