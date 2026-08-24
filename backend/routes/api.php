<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Products (public)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);

// Protected routes (requires Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Cart
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Orders
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);

    // Admin routes (requires admin role)
    Route::prefix('admin')->middleware('is.admin')->group(function () {
        Route::get('/products', [AdminController::class, 'productIndex']);
        Route::post('/products', [AdminController::class, 'productStore']);
        Route::get('/products/{id}', [AdminController::class, 'productShow']);
        Route::put('/products/{id}', [AdminController::class, 'productUpdate']);
        Route::delete('/products/{id}', [AdminController::class, 'productDestroy']);

        Route::get('/orders', [AdminController::class, 'orderIndex']);
        Route::put('/orders/{id}', [AdminController::class, 'orderUpdate']);

        Route::get('/stats', [AdminController::class, 'stats']);
    });
});
