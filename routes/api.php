<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// auth routes
Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('jwt.auth');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('jwt.auth');
    Route::get('profile', [AuthController::class, 'profile'])->middleware('jwt.auth');
});

// protected routes
Route::middleware(['jwt.auth'])->group(function () {

    // team routes
    Route::apiResource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite']);
    Route::post('teams/join', [TeamController::class, 'join']);
    Route::delete('teams/{team}/leave', [TeamController::class, 'leave']);
    Route::get('teams/{team}/members', [TeamController::class, 'members']);

    // team-specific routes (require membership)
    Route::middleware(['team.member'])->group(function () {

        // channel routes
        Route::apiResource('teams.channels', ChannelController::class);

        // message routes
        Route::apiResource('teams.channels.messages', MessageController::class);

        // activity log routes
        Route::get('teams/{team}/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('teams/{team}/activity-logs/export', [ActivityLogController::class, 'export']);
    });
});
