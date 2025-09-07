<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TeamManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| admin routes
|--------------------------------------------------------------------------
|
| admin routes for cms
|
*/

Route::middleware(['jwt.auth', 'admin'])->prefix('admin')->group(function () {

    // dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/statistics', [DashboardController::class, 'statistics']);

    // team management
    Route::get('teams', [TeamManagementController::class, 'index']);
    Route::get('teams/{team}', [TeamManagementController::class, 'show']);
    Route::put('teams/{team}', [TeamManagementController::class, 'update']);
    Route::delete('teams/{team}', [TeamManagementController::class, 'destroy']);
    Route::get('teams/{team}/administrators', [TeamManagementController::class, 'administrators']);
    Route::post('teams/{team}/users/{user}/promote', [TeamManagementController::class, 'promoteAdmin']);
    Route::post('teams/{team}/users/{user}/demote', [TeamManagementController::class, 'demoteAdmin']);

    // user management
    Route::get('users', [UserManagementController::class, 'index']);
    Route::get('users/{user}', [UserManagementController::class, 'show']);
    Route::put('users/{user}', [UserManagementController::class, 'update']);
    Route::post('users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus']);
    Route::delete('users/{user}', [UserManagementController::class, 'destroy']);
});
