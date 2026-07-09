<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
Route::get('/', [GameController::class, 'index'])->name('game.index');
Route::post('/game/bootstrap', [GameController::class, 'bootstrap'])->name('game.bootstrap');
Route::patch('/game/player/{player}/rename', [GameController::class, 'rename'])->name('game.rename');
Route::post('/game/player/{player}/roll-encounter', [GameController::class, 'rollEncounter'])->name('game.roll-encounter');
Route::post('/game/player/{player}/fight', [GameController::class, 'fight'])->name('game.fight');
Route::post('/game/player/{player}/change-class', [GameController::class, 'changeClass'])->name('game.change-class');
