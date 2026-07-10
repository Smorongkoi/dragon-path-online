<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
Route::get('/', [GameController::class, 'index'])->name('game.index');
Route::post('/game/bootstrap', [GameController::class, 'bootstrap'])->name('game.bootstrap');
Route::get('/game/world', [GameController::class, 'world'])->name('game.world');
Route::patch('/game/player/{player}/rename', [GameController::class, 'rename'])->name('game.rename');
Route::post('/game/player/{player}/heartbeat', [GameController::class, 'heartbeat'])->name('game.heartbeat');
Route::post('/game/player/{player}/chat', [GameController::class, 'chat'])->name('game.chat');
Route::patch('/game/player/{player}/element', [GameController::class, 'chooseElement'])->name('game.element');
Route::post('/game/player/{player}/roll-encounter', [GameController::class, 'rollEncounter'])->name('game.roll-encounter');
Route::post('/game/player/{player}/fight', [GameController::class, 'fight'])->name('game.fight');
Route::post('/game/player/{player}/pvp/start', [GameController::class, 'startPvp'])->name('game.pvp.start');
Route::post('/game/player/{player}/pvp/bot/start', [GameController::class, 'startBotPvp'])->name('game.pvp.bot.start');
Route::post('/game/player/{player}/pvp/fight', [GameController::class, 'fightPvp'])->name('game.pvp.fight');
Route::post('/game/player/{player}/change-class', [GameController::class, 'changeClass'])->name('game.change-class');
