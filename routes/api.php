<?php

use App\Http\Controllers\Api\V1\AccuseReceptionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BeneficiaireController;
use App\Http\Controllers\Api\V1\ControlePieceController;
use App\Http\Controllers\Api\V1\CourrierController;
use App\Http\Controllers\Api\V1\DepotPieceController;
use App\Http\Controllers\Api\V1\DossierAffectationController;
use App\Http\Controllers\Api\V1\DossierSinistreController;
use App\Http\Controllers\Api\V1\DossierStatutController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\LiquidationController;
use App\Http\Controllers\Api\V1\PieceJustificativeController;
use App\Http\Controllers\Api\V1\ReglementController;
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
| Les dossiers s'adressent par leur numéro de sinistre (SIN-VIE-2026-000042)
| et les courriers par leur numéro d'ordre (COU-2026-000123) : ce sont les
| références que les agents ont sous les yeux.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('ping', fn () => response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]))->name('ping');

    // --- Authentification ---------------------------------------------------
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        // --- Bureau de réception du courrier --------------------------------
        Route::get('courriers', [CourrierController::class, 'index'])->name('courriers.index');
        Route::post('courriers', [CourrierController::class, 'store'])->name('courriers.store');
        Route::get('courriers/{courrier}', [CourrierController::class, 'show'])->name('courriers.show');
        Route::match(['put', 'patch'], 'courriers/{courrier}', [CourrierController::class, 'update'])->name('courriers.update');
        Route::post('courriers/{courrier}/accuse', [AccuseReceptionController::class, 'store'])->name('courriers.accuse.store');

        // --- Dossiers sinistre ----------------------------------------------
        Route::get('dossiers', [DossierSinistreController::class, 'index'])->name('dossiers.index');
        Route::post('dossiers', [DossierSinistreController::class, 'store'])->name('dossiers.store');
        Route::get('dossiers/{dossier}', [DossierSinistreController::class, 'show'])->name('dossiers.show');

        Route::scopeBindings()->group(function (): void {
            // Circuit : avancer, affecter, liquider
            Route::post('dossiers/{dossier}/statut', [DossierStatutController::class, 'store'])->name('dossiers.statut.store');
            Route::post('dossiers/{dossier}/affectation', [DossierAffectationController::class, 'store'])->name('dossiers.affectation.store');
            Route::post('dossiers/{dossier}/liquidation', [LiquidationController::class, 'store'])->name('dossiers.liquidation.store');

            // Bénéficiaires
            Route::get('dossiers/{dossier}/beneficiaires', [BeneficiaireController::class, 'index'])->name('dossiers.beneficiaires.index');
            Route::post('dossiers/{dossier}/beneficiaires', [BeneficiaireController::class, 'store'])->name('dossiers.beneficiaires.store');
            Route::match(['put', 'patch'], 'dossiers/{dossier}/beneficiaires/{beneficiaire}', [BeneficiaireController::class, 'update'])->name('dossiers.beneficiaires.update');
            Route::delete('dossiers/{dossier}/beneficiaires/{beneficiaire}', [BeneficiaireController::class, 'destroy'])->name('dossiers.beneficiaires.destroy');

            // Pièces justificatives
            Route::get('dossiers/{dossier}/pieces', [PieceJustificativeController::class, 'index'])->name('dossiers.pieces.index');
            Route::post('dossiers/{dossier}/pieces', [PieceJustificativeController::class, 'store'])->name('dossiers.pieces.store');
            Route::get('dossiers/{dossier}/pieces/{piece}/fichier', [PieceJustificativeController::class, 'show'])->name('dossiers.pieces.fichier');
            Route::delete('dossiers/{dossier}/pieces/{piece}', [PieceJustificativeController::class, 'destroy'])->name('dossiers.pieces.destroy');
            // POST plutôt que PUT : les téléversements multipart passent mal en PUT.
            Route::post('dossiers/{dossier}/pieces/{piece}/depot', [DepotPieceController::class, 'update'])->name('dossiers.pieces.depot');
            Route::post('dossiers/{dossier}/pieces/{piece}/controle', [ControlePieceController::class, 'update'])->name('dossiers.pieces.controle');

            // Règlements
            Route::get('dossiers/{dossier}/reglements', [ReglementController::class, 'index'])->name('dossiers.reglements.index');
            Route::post('dossiers/{dossier}/reglements', [ReglementController::class, 'store'])->name('dossiers.reglements.store');
            Route::match(['put', 'patch'], 'dossiers/{dossier}/reglements/{reglement}', [ReglementController::class, 'update'])->name('dossiers.reglements.update');

            // Journal de traçabilité
            Route::get('dossiers/{dossier}/journal', [JournalController::class, 'index'])->name('dossiers.journal.index');
        });
    });
});
