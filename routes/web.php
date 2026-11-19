<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GameRunController as AdminGameRunController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\MarketingController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NikniqController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\SavedGameController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Middleware\EditorAuth;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/play', [GameController::class, 'index'])->name('game');
Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/health', [HealthController::class, 'json'])->middleware('throttle:30,1')->name('health');
Route::get('/players/{name}', [PlayerController::class, 'show'])->name('player');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/auth/google', [SocialAuthController::class, 'redirect'])->middleware('throttle:20,1')->name('oauth.google');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'callback'])->middleware('throttle:20,1')->name('oauth.google.callback');
    Route::get('/auth/nikniq', [NikniqController::class, 'redirect'])->middleware('throttle:20,1')->name('auth.nikniq');
    Route::get('/auth/nikniq/callback', [NikniqController::class, 'callback'])->middleware('throttle:20,1')->name('auth.nikniq.callback');
    Route::get('/auth/callback', [NikniqController::class, 'callback'])->middleware('throttle:20,1')->name('auth.callback');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/save', [SavedGameController::class, 'show'])->name('save.show');
    Route::put('/save', [SavedGameController::class, 'store'])->middleware('throttle:60,1')->name('save.store');
    Route::delete('/save', [SavedGameController::class, 'destroy'])->name('save.destroy');
});

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

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/admin', [AdminUserController::class, 'toggleAdmin'])->name('users.toggle-admin');
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::get('runs', [AdminGameRunController::class, 'index'])->name('runs.index');
    Route::delete('runs/{run}', [AdminGameRunController::class, 'destroy'])->name('runs.destroy');
    Route::get('health', [HealthController::class, 'index'])->name('health');
    Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring');
    Route::get('logs', [LogsController::class, 'index'])->name('logs');
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/export/{dataset}', [ReportsController::class, 'export'])->name('reports.export');
    Route::get('marketing', [MarketingController::class, 'index'])->name('marketing');
});
