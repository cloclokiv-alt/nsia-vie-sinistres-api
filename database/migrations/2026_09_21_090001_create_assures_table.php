<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assures', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120);
            $table->string('prenoms', 180);
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance', 120)->nullable();
            $table->string('sexe', 1)->nullable();
            $table->string('type_piece_identite', 40)->nullable();
            $table->string('numero_piece_identite', 60)->nullable();
            $table->string('telephone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('adresse', 255)->nullable();
            $table->string('profession', 120)->nullable();
            // Renseignée à l'ouverture d'un dossier décès ; sert aux contrôles de cohérence.
            $table->date('date_deces')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['nom', 'prenoms']);
            $table->index('numero_piece_identite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assures');
    }
};
