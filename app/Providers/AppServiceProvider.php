<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
    }

    /**
     * Hors production, toute erreur discrète (chargement paresseux, attribut inexistant)
     * devient une exception : les bugs sortent en développement, pas au guichet.
     */
    protected function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // La route de connexion est la plus exposée au bourrage d'identifiants.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by((string) $request->input('email')),
        ]);
    }
}
