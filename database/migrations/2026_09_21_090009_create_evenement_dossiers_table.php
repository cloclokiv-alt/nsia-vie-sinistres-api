<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal du dossier : ajout seul, jamais de modification ni de suppression.
        // D'où l'absence de updated_at — une ligne de journal ne se corrige pas.
        Schema::create('evenement_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_sinistre_id')->constrained('dossier_sinistres')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type', 40)->index();
            // Nul pour les écritures automatiques (échéance dépassée, relance planifiée).
            $table->foreignId('auteur_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('description', 500);
            // Contexte structuré : ancien et nouveau statut, montants, identifiants.
            $table->json('donnees')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['dossier_sinistre_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenement_dossiers');
    }
};
