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

// Public Auth routes (con rate limiting exacto)
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

// Información bancaria y medios de pago para checkout
Route::get('/checkout/payment-info', [OrderController::class, 'paymentInfo']);

// Public Catalog & 3D Models routes (Semana 2, 3, 4)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/models-3d', [ProductController::class, 'models3d']);
Route::get('/media/{folder}/{filename}', [AdminController::class, 'serveMedia']);

// Swagger / OpenAPI 3.0 Spec (Semana 8)
Route::get('/openapi.json', [AdminController::class, 'openApiSpec']);

// Protected routes (requires Sanctum token & active non-banned user)
Route::middleware(['auth:sanctum', 'not.banned'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    // Compras y Pedidos del Cliente (Historia 3)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:orders-create');
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/{id}/receipt', [OrderController::class, 'receipt']);
    Route::post('/orders/{id}/payment-proof', [OrderController::class, 'uploadPaymentProof']);
    Route::get('/orders/{id}/payment-proof', [OrderController::class, 'downloadPaymentProof']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/advance', [OrderController::class, 'advance']);

    // Reseñas y calificaciones de productos
    Route::post('/products/{id}/reviews', [ProductController::class, 'storeReview']);
    Route::delete('/products/{id}/reviews/{reviewId}', [ProductController::class, 'destroyReview']);

    // Admin routes (requires admin role - Semana 4, 5, 7, 15)
    Route::prefix('admin')->middleware('is.admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/products', [AdminController::class, 'products']);
        Route::post('/products', [AdminController::class, 'storeProduct']);
        Route::put('/products/{id}', [AdminController::class, 'updateProduct']);
        Route::delete('/products/{id}', [AdminController::class, 'destroyProduct']);
        Route::patch('/variants/{id}/stock', [AdminController::class, 'restockVariant']);
        Route::post('/upload', [AdminController::class, 'uploadImage']);
        Route::get('/media-library', [AdminController::class, 'mediaLibrary']);
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::get('/orders/{id}/receipt', [AdminController::class, 'orderReceipt']);
        Route::get('/orders/{id}/payment-proof', [AdminController::class, 'downloadOrderPaymentProof']);
        Route::patch('/orders/{id}/payment', [AdminController::class, 'verifyOrderPayment']);
        Route::patch('/orders/{id}', [AdminController::class, 'updateOrder']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{id}/orders', [AdminController::class, 'userOrders']);
        Route::patch('/users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::patch('/users/{id}/ban', [AdminController::class, 'toggleUserBan']);
        Route::delete('/users/{id}', [AdminController::class, 'destroyUser']);
        Route::post('/notifications/send', [AdminController::class, 'triggerWebhook']);
        Route::post('/webhooks/n8n', [AdminController::class, 'triggerWebhook']);
    });
});
