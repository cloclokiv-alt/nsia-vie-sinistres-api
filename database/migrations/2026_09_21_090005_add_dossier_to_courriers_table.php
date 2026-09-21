<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rattachement du courrier au dossier. Ajouté après coup parce que les deux
     * tables se référencent mutuellement : le dossier naît d'un courrier, et
     * les courriers suivants viennent nourrir un dossier déjà ouvert.
     */
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->foreignId('dossier_sinistre_id')
                ->nullable()
                ->after('numero_police_declare')
                ->constrained('dossier_sinistres')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dossier_sinistre_id');
        });
    }
};
