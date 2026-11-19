<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinutes(5, 3)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Alcanzaste el límite de registros (máximo 3 cada 5 minutos). Esperá un momento e intentá nuevamente.',
                ], 429);
            });
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Demasiados intentos de inicio de sesión (máximo 5 por minuto). Esperá un minuto antes de volver a intentar.',
                ], 429);
            });
        });

        RateLimiter::for('orders-create', function (Request $request) {
            $key = $request->user()?->id ? 'user:' . $request->user()->id : 'ip:' . $request->ip();

            return Limit::perMinutes(2, 5)->by($key)->response(function () {
                return response()->json([
                    'message' => 'Realizaste varios pedidos seguidos (máximo 5 cada 2 minutos). Esperá un momento antes de confirmar otro.',
                ], 429);
            });
        });
    }
}
