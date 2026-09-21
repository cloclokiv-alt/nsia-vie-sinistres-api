<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — sinistres Assurance Vie
|--------------------------------------------------------------------------
|
| Toutes les routes sont préfixées par /api/v1 et renvoient du JSON.
| L'authentification se fait par jeton Sanctum : en-tête
| « Authorization: Bearer <token> ».
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('ping', fn () => response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]))->name('ping');
});
