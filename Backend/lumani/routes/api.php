<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\XpConversionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::post('/register', [AuthController::class, 'register'])->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/user', [AuthController::class, 'user'])->name('api.user');
    Route::get('/user/referral-code', [UserController::class, 'referralCode'])->name('api.user.referral-code');

    // Missions
    Route::get('/missions', [MissionController::class, 'index'])->name('api.missions.index');
    Route::post('/missions/checkin', [MissionController::class, 'checkin'])->name('api.missions.checkin');
    Route::post('/missions/watch-ad', [MissionController::class, 'watchAd'])->name('api.missions.watch-ad');
    Route::post('/missions/complete/{missionKey}', [MissionController::class, 'complete'])->name('api.missions.complete');

    // XP Conversion
    Route::post('/xp/convert', [XpConversionController::class, 'convert'])->name('api.xp.convert');
});
