<?php

namespace App\Filament\Resources\ReservaResource\Pages;

use App\Filament\Resources\ReservaResource;
use App\Models\User;
use App\Notifications\NuevaReservaNotification;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Notification;

class CreateReserva extends CreateRecord
{
    protected static string $resource = ReservaResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $reserva = $this->getRecord();
        $admins = User::where('es_admin', true)->get();

        Notification::send($admins, new NuevaReservaNotification($reserva));
    }
}