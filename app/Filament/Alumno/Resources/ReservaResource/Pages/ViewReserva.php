<?php

namespace App\Filament\Alumno\Resources\ReservaResource\Pages;

use App\Filament\Alumno\Resources\ReservaResource;
use App\Models\Reserva;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Actions\Action;
use Filament\Support\Enums\Alignment;

class ViewReserva extends ViewRecord
{
    protected static string $resource = ReservaResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Componente que renderiza tu vista Blade personalizada
                Infolists\Components\View::make('filament.alumno.resources.reserva-resource.infolists.reserva-detalle')
                    ->columnSpanFull(),
            ]);
    }
}

