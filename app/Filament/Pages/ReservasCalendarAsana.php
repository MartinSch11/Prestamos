<?php

namespace App\Filament\Pages;

use App\Models\Reserva;
use Carbon\Carbon;
use Filament\Pages\Page;

class ReservasCalendarAsana extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Calendario estilo Asana';
    protected static string $view = 'filament.pages.reservas-calendar-asana';

    public $weekOffset = 0;

    public function getWeekStart(): Carbon
    {
        return now()->startOfWeek(Carbon::MONDAY)->addWeeks($this->weekOffset)->startOfDay();
    }

    public function getWeekEnd(): Carbon
    {
        return $this->getWeekStart()->copy()->addDays(6)->endOfDay();
    }

    public function getEventos()
    {
        $weekStart = $this->getWeekStart();
        $weekEnd   = $this->getWeekEnd();

        return Reserva::with('items.equipo')
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('inicio', [$weekStart, $weekEnd])
                  ->orWhereBetween('fin', [$weekStart, $weekEnd])
                  ->orWhere(function ($q2) use ($weekStart, $weekEnd) {
                      $q2->where('inicio', '<', $weekStart)
                         ->where('fin', '>', $weekEnd);
                  });
            })
            ->get()
            ->map(function ($reserva) use ($weekStart, $weekEnd) {
                return [
                    'id'     => $reserva->id,
                    'title'  => $reserva->titulo,
                    'start'  => $reserva->inicio,
                    'end'    => $reserva->fin,
                    'estado' => $reserva->estado,
                    'items'  => $reserva->items->map(fn($i) => $i->equipo->nombre . " (x{$i->cantidad})")->toArray(),
                    'continuaAntes' => $reserva->inicio < $weekStart,
                    'continuaDespues' => $reserva->fin > $weekEnd,
                ];
            });
    }
}
