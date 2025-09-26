<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'cantidad',
        'disponible',
        'tipo_equipo_id',
    ];

    public function reservaItems()
    {
        return $this->hasMany(ReservaItem::class);
    }

    public function tipo()
    {
        return $this->belongsTo(TipoEquipo::class, 'tipo_equipo_id');
    }

    public function disponibleAhora(): int
    {
        return $this->disponibleEnRango(now(), now());
    }

    public function disponibleEnRango($inicio, $fin): int
    {
        // Cantidad total del equipo
        $total = $this->cantidad;

        // Reservas que se solapan en el rango
        $reservado = $this->reservaItems()
            ->whereHas('reserva', function ($q) use ($inicio, $fin) {
                $q->whereIn('estado', ['pendiente', 'en_curso'])
                    ->where(function ($query) use ($inicio, $fin) {
                        $query->where('inicio', '<', $fin)   // la reserva empieza antes que termine el rango
                            ->where('fin', '>', $inicio); // y termina después que empieza el rango
                    });
            })
            ->sum('cantidad');

        return max(0, $total - $reservado);
    }

}
