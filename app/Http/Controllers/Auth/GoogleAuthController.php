<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->googleConfigured()) {
            return redirect()->route('game.index')
                ->with('auth_error', 'Google Login ยังไม่ได้ตั้งค่า GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! $this->googleConfigured()) {
            return redirect()->route('game.index')
                ->with('auth_error', 'Google Login ยังไม่ได้ตั้งค่า GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('game.index')
                ->with('auth_error', 'เข้าสู่ระบบด้วย Google ไม่สำเร็จ ลองใหม่อีกครั้ง');
        }

        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google Player',
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => Str::password(48),
            ]
        );

        Auth::login($user, true);

        return redirect()->route('game.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('game.index');
    }

    private function googleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
