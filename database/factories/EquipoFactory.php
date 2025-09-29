<?php

namespace Database\Factories;

use App\Models\Equipo;
use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipoFactory extends Factory
{
    protected $model = Equipo::class;

    public function definition()
    {
        return [
            'nombre' => 'Equipo de prueba',
            'descripcion' => 'Desc',
            'cantidad' => 10,
            'tipo_equipo_id' => TipoEquipo::factory(), // 👈 crea un tipo automáticamente
        ];
    }
}
