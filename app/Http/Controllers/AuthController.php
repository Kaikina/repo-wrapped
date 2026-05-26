<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirectToGitHub(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['read:user'])
            ->redirect();
    }

    public function handleGitHubCallback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $user = User::updateOrCreate(
            ['github_id' => $githubUser->getId()],
            [
                'name'            => $githubUser->getName() ?? $githubUser->getNickname(),
                'github_username' => $githubUser->getNickname(),
                'github_token'    => $githubUser->token,
                'avatar_url'      => $githubUser->getAvatar(),
                'email'           => $githubUser->getEmail(),
            ]
        );

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }
}
