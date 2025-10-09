<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            // Modificamos la columna ENUM para añadir los nuevos estados
            $table->enum('estado', [
                'pendiente',
                'aceptado',   // <-- NUEVO
                'rechazado',  // <-- NUEVO
                'en_curso',
                'devuelto'
            ])->default('pendiente')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            // Esto revierte al estado anterior si es necesario
            $table->enum('estado', [
                'pendiente',
                'en_curso',
                'devuelto'
            ])->default('pendiente')->change();
        });
    }
};