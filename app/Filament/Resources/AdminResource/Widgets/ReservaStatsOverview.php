<?php

namespace App\Filament\Resources\AdminResource\Widgets;

use App\Models\Reserva;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReservaStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Reservas Pendientes', Reserva::where('estado', 'pendiente')->count())
                ->description('Esperando aprobación')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
                
            Stat::make('Reservas En Curso', Reserva::where('estado', 'en_curso')->count())
                ->description('Equipos prestados actualmente')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('success'),
        ];
    }
}
