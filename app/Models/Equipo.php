<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    use HasFactory;
    protected $fillable = [
        'nombre',
        'descripcion',
        'cantidad',
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
        $total = $this->cantidad;

        $reservado = $this->reservaItems()
            ->whereHas('reserva', function ($q) use ($inicio, $fin) {
                $q->whereIn('estado', ['pendiente', 'en_curso'])
                    ->where(function ($query) use ($inicio, $fin) {
                        $query->where('inicio', '<', $fin)   // empieza antes de que termine mi rango
                            ->where('fin', '>', $inicio); // termina después de que empiece mi rango
                    });
            })
            ->sum('cantidad');

        return max(0, $total - $reservado);
    }



}
