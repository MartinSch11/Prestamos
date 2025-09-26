<?php

namespace App\Filament\Resources\ReservaResource\Pages;

use App\Filament\Resources\ReservaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateReserva extends CreateRecord
{
    protected static string $resource = ReservaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
