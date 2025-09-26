<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['user_id']); // 👈 elimina FK primero
            $table->dropColumn('user_id');    // 👈 después elimina la columna
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });
    }
};
