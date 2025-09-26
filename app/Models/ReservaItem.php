<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservaItem extends Model
{
    protected $fillable = [
        'reserva_id',
        'equipo_id',
        'cantidad',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}
