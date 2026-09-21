<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->string('numero_police', 60)->unique();
            $table->foreignId('assure_id')->constrained('assures')->cascadeOnUpdate()->restrictOnDelete();
            // Le souscripteur n'est pas toujours l'assuré (employeur, banque, parent).
            $table->string('souscripteur_nom', 180)->nullable();
            $table->string('type', 40)->index();
            $table->string('statut', 32)->index();
            $table->date('date_effet');
            $table->date('date_echeance')->nullable();
            // Le franc CFA n'a pas de subdivision : des entiers, jamais de flottants.
            $table->unsignedBigInteger('capital_garanti_xaf')->default(0);
            $table->unsignedBigInteger('prime_xaf')->default(0);
            $table->unsignedBigInteger('provision_mathematique_xaf')->default(0);
            $table->string('periodicite', 24)->nullable();
            $table->date('date_derniere_prime')->nullable();
            // Délai pendant lequel la garantie ne joue pas encore, en mois.
            $table->unsignedSmallInteger('carence_mois')->default(0);
            // Contrats emprunteur : l'établissement prêteur est bénéficiaire de premier rang.
            $table->string('organisme_preteur', 180)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assure_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};
