<?php

namespace App\Filament\Resources\AdminResource\Widgets;

use App\Models\Reserva;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class ReservasChart extends ChartWidget
{
    protected static ?string $heading = 'Reservas Creadas (Últimos 7 Días)';

    protected static string $color = 'info';
    
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = Trend::model(Reserva::class)
            ->between(
                start: now()->subDays(6),
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Reservas Creadas',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => Carbon::parse($value->date)->format('d/m')),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1, // Fuerza al eje a ir de 1 en 1
                    ],
                ],
            ],
        ];
    }
}