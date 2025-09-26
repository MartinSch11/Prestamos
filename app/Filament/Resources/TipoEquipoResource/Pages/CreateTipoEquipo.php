<?php

namespace App\Filament\Resources\TipoEquipoResource\Pages;

use App\Filament\Resources\TipoEquipoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoEquipo extends CreateRecord
{
    protected static string $resource = TipoEquipoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
