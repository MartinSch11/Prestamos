<?php

namespace App\Filament\Alumno\Resources\ReservaResource\Pages;

use App\Filament\Alumno\Resources\ReservaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReserva extends EditRecord
{
    protected static string $resource = ReservaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
