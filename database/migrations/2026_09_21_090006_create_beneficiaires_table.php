<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les bénéficiaires sont portés par le dossier, pas par le contrat : la clause
        // bénéficiaire désigne souvent des personnes « à identifier » (« mes héritiers »),
        // et c'est l'instruction du sinistre qui leur donne un nom.
        Schema::create('beneficiaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_sinistre_id')->constrained('dossier_sinistres')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('nom', 180);
            $table->string('prenoms', 180)->nullable();
            $table->string('qualite', 40)->index();
            $table->string('statut', 32)->index();
            // Part du capital, en pourcentage. La somme des parts actives fait 100.
            $table->decimal('quote_part', 5, 2)->default(0);
            $table->date('date_naissance')->nullable();
            $table->string('type_piece_identite', 40)->nullable();
            $table->string('numero_piece_identite', 60)->nullable();
            $table->string('telephone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('adresse', 255)->nullable();
            $table->string('mode_reglement', 32)->nullable();
            $table->string('coordonnees_reglement', 180)->nullable();
            // Montant arrêté à la liquidation : quote-part appliquée au capital liquidé.
            $table->unsignedBigInteger('montant_du_xaf')->default(0);
            $table->string('motif_ecartement', 255)->nullable();
            $table->timestamps();

            $table->index(['dossier_sinistre_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaires');
    }
};
