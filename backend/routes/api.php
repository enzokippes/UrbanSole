<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ProductController;

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

    // Admin routes (requires admin role - Semana 4, 5, 7, 15)
    Route::prefix('admin')->middleware('is.admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        Route::post('/webhooks/n8n', [AdminController::class, 'triggerWebhook']);
    });
});
