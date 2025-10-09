<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            // Ya no tienes una sola relación con equipo, sino con items,
            // por lo que esta línea probablemente no debería estar aquí.
            // La quito para evitar confusiones.
            
            // ✅ AÑADE LA RELACIÓN CON EL USUARIO AQUÍ
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            $table->string('titulo');
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->enum('estado', [
                'pendiente', 
                'aceptado', 
                'rechazado', 
                'en_curso', 
                'devuelto'
            ])->default('pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};