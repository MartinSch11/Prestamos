<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cantidad')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_items');
    }
};
