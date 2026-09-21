<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le registre du bureau de réception : tout part d'ici.
        Schema::create('courriers', function (Blueprint $table) {
            $table->id();
            $table->string('numero_ordre', 32)->unique();
            $table->string('canal', 40)->index();
            $table->timestamp('date_reception');
            $table->string('expediteur_nom', 180);
            $table->string('expediteur_qualite', 120)->nullable();
            $table->string('expediteur_telephone', 32)->nullable();
            $table->string('expediteur_adresse', 255)->nullable();
            $table->string('objet', 255);
            $table->unsignedSmallInteger('nombre_pieces')->default(0);
            // Numéro de police tel que l'expéditeur l'a écrit : souvent incomplet ou erroné,
            // on le conserve brut pour la recherche avant rattachement au contrat.
            $table->string('numero_police_declare', 60)->nullable()->index();
            $table->foreignId('recu_par_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('accuse_code', 32)->nullable()->unique();
            $table->timestamp('accuse_remis_le')->nullable();
            $table->timestamp('oriente_le')->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->index(['date_reception', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courriers');
    }
};
