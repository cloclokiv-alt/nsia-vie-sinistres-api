<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes web
|--------------------------------------------------------------------------
|
| Ce dépôt est une API sans interface : la racine se contente d'annoncer
| le service. La supervision utilise /up (health check de Laravel).
|
*/

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'description' => 'API de gestion des sinistres Assurance Vie',
    'api' => url('/api/v1/ping'),
    'health' => url('/up'),
]))->name('home');
