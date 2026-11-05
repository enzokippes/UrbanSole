<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - UrbanSole (Programación IV - UTN Concordia)
|--------------------------------------------------------------------------
*/

// Public Auth & OAuth 2.0 routes (Semana 6 & Semana 7)
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/oauth', [AuthController::class, 'socialLogin']);

// Public Catalog & 3D Models routes (Semana 2, 3, 4)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/models-3d', [ProductController::class, 'models3d']);

// Swagger / OpenAPI 3.0 Spec (Semana 8)
Route::get('/openapi.json', [AdminController::class, 'openApiSpec']);

// Protected routes (requires Sanctum token - Semana 6 & 7)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    // Compras y Pedidos del Cliente (Historia 3)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

    // Admin routes (requires admin role - Semana 4, 5, 7, 15)
    Route::prefix('admin')->middleware('is.admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/products', [AdminController::class, 'products']);
        Route::post('/products', [AdminController::class, 'storeProduct']);
        Route::put('/products/{id}', [AdminController::class, 'updateProduct']);
        Route::delete('/products/{id}', [AdminController::class, 'destroyProduct']);
        Route::post('/upload', [AdminController::class, 'uploadImage']);
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::patch('/orders/{id}', [AdminController::class, 'updateOrder']);
        Route::post('/webhooks/n8n', [AdminController::class, 'triggerWebhook']);
    });
});
