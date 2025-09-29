<?php

// database/factories/ReservaFactory.php
namespace Database\Factories;

use App\Models\Reserva;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservaFactory extends Factory
{
    protected $model = Reserva::class;

    public function definition(): array
    {
        return [
            'titulo' => 'Reserva test',
            'inicio' => now()->addHour(),
            'fin' => now()->addHours(2),
            'estado' => 'pendiente', // 'pendiente' | 'en_curso' | 'devuelto'
        ];
    }
}

