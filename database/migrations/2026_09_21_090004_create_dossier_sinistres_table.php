<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossier_sinistres', function (Blueprint $table) {
            $table->id();
            $table->string('numero_sinistre', 32)->unique();
            $table->foreignId('contrat_id')->constrained('contrats')->cascadeOnUpdate()->restrictOnDelete();
            // Le courrier déclencheur. Les courriers suivants (pièces complémentaires)
            // sont rattachés par courriers.dossier_sinistre_id.
            $table->foreignId('courrier_id')->nullable()->constrained('courriers')->cascadeOnUpdate()->nullOnDelete();
            $table->string('nature', 40)->index();
            $table->string('statut', 32)->index();
            $table->date('date_survenance');
            $table->date('date_declaration');
            $table->string('lieu_survenance', 180)->nullable();
            $table->text('circonstances')->nullable();
            $table->foreignId('gestionnaire_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('ouvert_par_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            // Capital arrêté en liquidation, puis figé par la décision.
            $table->unsignedBigInteger('capital_liquide_xaf')->default(0);
            $table->string('motif_rejet', 40)->nullable();
            $table->text('motif_rejet_detail')->nullable();
            $table->timestamp('decide_le')->nullable();
            $table->foreignId('decide_par_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('clos_le')->nullable();
            // Échéance interne d'instruction : sert au pilotage des retards.
            $table->date('echeance_instruction')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'echeance_instruction']);
            $table->index(['gestionnaire_id', 'statut']);
            $table->index(['contrat_id', 'nature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_sinistres');
    }
};
