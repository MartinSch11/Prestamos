<?php

namespace App\Filament\Alumno\Resources\ReservaResource\Pages;

use App\Filament\Alumno\Resources\ReservaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReservas extends ListRecords
{
    protected static string $resource = ReservaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
