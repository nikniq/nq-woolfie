<?php

use App\Http\Controllers\Api\CampaignResultController;
use App\Http\Controllers\Api\GameRunController;
use App\Http\Controllers\Api\LevelController;
use Illuminate\Support\Facades\Route;

Route::get('/levels', [LevelController::class, 'index'])->name('api.levels.index');
Route::get('/levels/{level}', [LevelController::class, 'show'])->name('api.levels.show');
Route::post('/runs', [GameRunController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('api.runs.store');
Route::post('/campaigns', [CampaignResultController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('api.campaigns.store');
