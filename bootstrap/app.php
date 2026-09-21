<?php

use App\Exceptions\TransitionInterdite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un refus du circuit est une règle métier qui s'applique, pas un incident :
        // le journaliser en erreur noierait les vraies pannes sous les gestes normaux
        // (un gestionnaire qui tente une transition non autorisée, par exemple).
        $exceptions->dontReport(TransitionInterdite::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Les agents sont francophones : les messages d'erreur du framework
        // le sont aussi, sans changer les codes HTTP attendus par le client.
        // Une transition de circuit refusée n'est pas une erreur technique :
        // c'est une règle métier, renvoyée comme une erreur de validation.
        $exceptions->render(fn (TransitionInterdite $e, Request $request) => $request->expectsJson()
            ? response()->json([
                'message' => $e->getMessage(),
                'errors' => ['statut' => [$e->getMessage()]],
            ], 422)
            : null);

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => __('Authentification requise.')], 401)
            : null);

        $exceptions->render(fn (AuthorizationException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => __('Action non autorisée.')], 403)
            : null);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->expectsJson() && $e->getMessage() === ''
            ? response()->json(['message' => __('Ressource introuvable.')], 404)
            : null);
    })->create();
