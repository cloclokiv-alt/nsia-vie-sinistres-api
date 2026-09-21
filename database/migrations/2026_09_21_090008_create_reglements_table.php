<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_sinistre_id')->constrained('dossier_sinistres')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('beneficiaire_id')->constrained('beneficiaires')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('montant_xaf');
            $table->string('mode', 32);
            $table->string('coordonnees', 180)->nullable();
            // Référence du virement, numéro de chèque ou identifiant Mobile Money.
            $table->string('reference', 120)->nullable();
            $table->timestamp('emis_le');
            $table->foreignId('emis_par_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('paye_le')->nullable();
            $table->foreignId('paye_par_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            // Un bénéficiaire n'est réglé qu'une fois : le doublon de paiement
            // est l'incident le plus coûteux du service.
            $table->unique('beneficiaire_id');
            $table->index(['dossier_sinistre_id', 'paye_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglements');
    }
};
