<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_justificatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_sinistre_id')->constrained('dossier_sinistres')->cascadeOnUpdate()->cascadeOnDelete();
            // Renseigné pour les pièces réclamées à une personne précise
            // (identité, RIB, quittance) ; nul pour les pièces du dossier.
            $table->foreignId('beneficiaire_id')->nullable()->constrained('beneficiaires')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('statut', 32)->index();
            $table->string('libelle', 180)->nullable();
            $table->boolean('obligatoire')->default(true);
            // Chemin sur le disque « sinistres », hors dossier public : une pièce
            // de dossier ne doit jamais être servie par une URL devinable.
            $table->string('chemin', 255)->nullable();
            $table->string('nom_origine', 255)->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('taille_octets')->nullable();
            // Empreinte SHA-256 : détecte les doublons et prouve qu'une pièce
            // archivée n'a pas été remplacée après coup.
            $table->string('empreinte', 64)->nullable()->index();
            $table->timestamp('deposee_le')->nullable();
            $table->foreignId('deposee_par_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('controlee_le')->nullable();
            $table->foreignId('controlee_par_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('motif_non_conformite', 255)->nullable();
            $table->timestamps();

            $table->index(['dossier_sinistre_id', 'statut']);
            // Nom donné à la main : le nom généré par Laravel dépasserait les
            // 64 caractères que MySQL accepte pour un identifiant.
            $table->index(['dossier_sinistre_id', 'beneficiaire_id', 'type'], 'pieces_dossier_beneficiaire_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_justificatives');
    }
};
