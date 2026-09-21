<?php

namespace App\Models;

use App\Enums\RoleUtilisateur;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Un agent NSIA. Les assurés ne sont pas des utilisateurs : ils déposent
 * un courrier, ils ne se connectent pas.
 */
#[Fillable(['matricule', 'name', 'email', 'phone', 'password', 'role', 'agence', 'actif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleUtilisateur::class,
            'actif' => 'boolean',
        ];
    }

    /**
     * Les dossiers dont l'agent est le gestionnaire attitré.
     *
     * @return HasMany<DossierSinistre, $this>
     */
    public function dossiers(): HasMany
    {
        return $this->hasMany(DossierSinistre::class, 'gestionnaire_id');
    }

    /**
     * Les courriers enregistrés par l'agent au guichet.
     *
     * @return HasMany<Courrier, $this>
     */
    public function courriers(): HasMany
    {
        return $this->hasMany(Courrier::class, 'recu_par_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function actifs(Builder $query): void
    {
        $query->where('actif', true);
    }

    public function aLeRole(RoleUtilisateur ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function estAdministrateur(): bool
    {
        return $this->role === RoleUtilisateur::Administrateur;
    }

    /**
     * Peut enregistrer un courrier entrant au registre du bureau de réception.
     */
    public function peutReceptionner(): bool
    {
        return in_array($this->role, RoleUtilisateur::receptionnistes(), true);
    }

    /**
     * Peut ouvrir et instruire un dossier sinistre.
     */
    public function peutInstruire(): bool
    {
        return in_array($this->role, RoleUtilisateur::instructeurs(), true);
    }

    /**
     * Peut prononcer une prise en charge, un rejet ou un classement.
     */
    public function peutDecider(): bool
    {
        return in_array($this->role, RoleUtilisateur::decideurs(), true);
    }

    /**
     * Peut émettre et constater un règlement.
     */
    public function peutRegler(): bool
    {
        return in_array($this->role, RoleUtilisateur::payeurs(), true);
    }

    /**
     * Seul le médecin-conseil (et l'administrateur) accède aux pièces médicales.
     */
    public function peutConsulterLeMedical(): bool
    {
        return $this->aLeRole(RoleUtilisateur::MedecinConseil, RoleUtilisateur::Administrateur);
    }
}
