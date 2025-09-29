<?php

// database/factories/ReservaItemFactory.php
namespace Database\Factories;

use App\Models\ReservaItem;
use App\Models\Reserva;
use App\Models\Equipo;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservaItemFactory extends Factory
{
    protected $model = ReservaItem::class;

    public function definition(): array
    {
        return [
            'reserva_id' => Reserva::factory(),
            'equipo_id'  => Equipo::factory(),
            'cantidad'   => 1,
        ];
    }
}

