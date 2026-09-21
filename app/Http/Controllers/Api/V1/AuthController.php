<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Connexion d'un agent. Un jeton par poste de travail, révocable seul.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
            // Même message dans les deux cas : ne pas révéler quels comptes existent.
            throw ValidationException::withMessages([
                'email' => __('Identifiants incorrects.'),
            ]);
        }

        if (! $user->actif) {
            throw ValidationException::withMessages([
                'email' => __('Ce compte est désactivé.'),
            ]);
        }

        $poste = $request->string('poste')->value() ?: 'poste-inconnu';

        return response()->json([
            'token' => $user->createToken($poste)->plainTextToken,
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Déconnexion : seul le jeton du poste courant est révoqué.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
