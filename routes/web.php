<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

// Home
Route::get('/', [HomeController::class, 'index'])->name('home');

// GitHub OAuth
Route::get('/auth/github', [AuthController::class, 'redirectToGitHub'])->name('auth.github');
Route::get('/auth/github/callback', [AuthController::class, 'handleGitHubCallback'])->name('auth.github.callback');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Stats (requires auth)
Route::middleware('auth')->group(function () {
    Route::post('/generate', [StatsController::class, 'generate'])->name('stats.generate');
});

// Shareable stats page (public — anyone with the link can view)
Route::get('/u/{owner}/{repo}/{username}', [StatsController::class, 'show'])->name('stats.show');
Route::get('/u/{owner}/{repo}/{username}/status', [StatsController::class, 'status'])->name('stats.status');

// SVG card endpoint (public)
Route::get('/card/{owner}/{repo}/{username}', [CardController::class, 'show'])->name('card.show');
