<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteShowController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// SSO login / logout
Route::get('/login',  [AuthController::class, 'login'])->name('login');
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

// Authenticated dashboard
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sites', [SiteShowController::class, 'index'])->name('sites.index');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
    Route::get('/sites/{site}', [SiteShowController::class, 'show'])->name('sites.show');

    // Per-site deploy (sync).
    Route::post('/sites/{site}/deploy', [DeploymentController::class, 'deployOne'])->name('deploy.one');

    // "Deploy All" is two steps: run demo synchronously, then queue the rest.
    Route::post('/deploy-all',         [DeploymentController::class, 'deployAll'])->name('deploy.all');
    Route::get('/deploy-all/confirm',  [DeploymentController::class, 'deployConfirm'])->name('deploy.confirm');
    Route::post('/deploy-all/confirm', [DeploymentController::class, 'deployAllConfirm'])->name('deploy.all.confirm');
});
