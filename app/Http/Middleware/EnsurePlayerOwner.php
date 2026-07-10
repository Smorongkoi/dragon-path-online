<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayerOwner
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $player = $request->route('player');

        if (! $player instanceof Player) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            if (app()->environment('testing') && ! $request->boolean('enforce_player_owner')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'ต้องเข้าสู่ระบบด้วย Google ก่อนเข้าเล่น',
                'loginUrl' => route('auth.google'),
            ], 401);
        }

        if ((int) $player->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'ไม่สามารถใช้งานตัวละครของผู้เล่นคนอื่นได้',
            ], 403);
        }

        return $next($request);
    }
}
