<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

// Legacy URLs are HMVC (module/controller/method) and CodeIgniter answers any method;
// each action checks the method itself where it matters.
Route::get('/sessions', [SessionController::class, 'index']);
Route::match(['get', 'post'], '/sessions/login', [SessionController::class, 'login']);
Route::get('/sessions/logout', [SessionController::class, 'logout']);

Route::middleware('admin')->group(function () {
    // default_controller = dashboard
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
