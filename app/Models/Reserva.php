<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    protected $fillable = [
        'titulo',
        'inicio',
        'fin',
        'estado',
    ];

    public function items()
    {
        return $this->hasMany(ReservaItem::class);
    }
}
