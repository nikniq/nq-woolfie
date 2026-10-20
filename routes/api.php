<?php

use App\Http\Controllers\Api\CampaignResultController;
use App\Http\Controllers\Api\DailyController;
use App\Http\Controllers\Api\GameRunController;
use App\Http\Controllers\Api\LevelController;
use Illuminate\Support\Facades\Route;

// The game posts from the browser session, so these run with the web
// middleware: a signed-in player's runs are credited to their account.
Route::middleware('web')->group(function () {
    Route::get('/levels', [LevelController::class, 'index'])->name('api.levels.index');
    Route::get('/levels/{level}', [LevelController::class, 'show'])->name('api.levels.show');
    Route::post('/runs', [GameRunController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('api.runs.store');
    Route::post('/campaigns', [CampaignResultController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('api.campaigns.store');
    Route::get('/daily', [DailyController::class, 'show'])->name('api.daily.show');
});
