<?php

namespace App\Filament\Resources\TipoEquipoResource\Pages;

use App\Filament\Resources\TipoEquipoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoEquipo extends EditRecord
{
    protected static string $resource = TipoEquipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
