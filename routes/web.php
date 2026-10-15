<?php

use App\Http\Controllers\EditorController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Middleware\EditorAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'index'])->name('game');
Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');

Route::prefix('editor')->name('editor.')->group(function () {
    Route::get('login', [EditorController::class, 'login'])->name('login');
    Route::post('login', [EditorController::class, 'authenticate'])->name('authenticate');
    Route::middleware(EditorAuth::class)->group(function () {
        Route::get('/', [EditorController::class, 'index'])->name('index');
        Route::post('logout', [EditorController::class, 'logout'])->name('logout');
        Route::get('new', [EditorController::class, 'create'])->name('create');
        Route::post('/', [EditorController::class, 'store'])->name('store');
        Route::get('{level}', [EditorController::class, 'edit'])->name('edit');
        Route::put('{level}', [EditorController::class, 'update'])->name('update');
        Route::delete('{level}', [EditorController::class, 'destroy'])->name('destroy');
    });
});
