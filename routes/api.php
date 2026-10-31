<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TenantStatusController;
use Illuminate\Support\Facades\Route;

// ---- Central (no tenancy initialized) ----
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/tenant/status', [TenantStatusController::class, 'show']);
    Route::post('/tenant/retry', [TenantStatusController::class, 'retry'])->middleware('owner');

    // ---- Tenant routes: SetTenant initializes the user's own store database ----
    Route::middleware('tenant')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);

        Route::get('/channels', [ChannelController::class, 'index']);
        Route::post('/channels/{id}/test', [ChannelController::class, 'test'])->whereNumber('id');
        Route::middleware('owner')->group(function () {
            Route::post('/channels', [ChannelController::class, 'store']);
            Route::delete('/channels/{id}', [ChannelController::class, 'destroy'])->whereNumber('id');
        });
    });
});
