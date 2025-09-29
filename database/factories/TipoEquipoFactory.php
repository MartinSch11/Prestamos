<?php

namespace Database\Factories;

use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Factories\Factory;

class TipoEquipoFactory extends Factory
{
    protected $model = TipoEquipo::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->word,
        ];
    }
}