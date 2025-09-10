<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\DirectMessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| routes for the api
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

    // direct message routes
    Route::group(['prefix' => 'direct-messages'], function () {
        Route::post('/', [DirectMessageController::class, 'store']);
        Route::get('/conversations', [DirectMessageController::class, 'conversations']);
        Route::get('/conversations/{user}', [DirectMessageController::class, 'conversation']);
        Route::post('/mark-read/{user}', [DirectMessageController::class, 'markAsRead']);
        Route::get('/unread-count', [DirectMessageController::class, 'unreadCount']);
        Route::put('/{message}', [DirectMessageController::class, 'update']);
        Route::delete('/{message}', [DirectMessageController::class, 'destroy']);
    });

    // team routes
    Route::apiResource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite']);
    Route::post('teams/join', [TeamController::class, 'join']);
    Route::delete('teams/{team}/leave', [TeamController::class, 'leave']);
    Route::get('teams/{team}/members', [TeamController::class, 'members']);

    // require membership
    Route::middleware(['team.member'])->group(function () {

        // channel routes
        Route::apiResource('teams.channels', ChannelController::class);

        // message routes
        Route::apiResource('teams.channels.messages', MessageController::class);

        // reaction routes
        Route::get('teams/{team}/channels/{channel}/messages/{message}/reactions', [ReactionController::class, 'index']);
        Route::post('teams/{team}/channels/{channel}/messages/{message}/reactions', [ReactionController::class, 'store']);
        Route::delete('teams/{team}/channels/{channel}/messages/{message}/reactions', [ReactionController::class, 'destroy']);
    });
});
