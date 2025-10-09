<?php

namespace App\Filament\Alumno\Resources\ReservaResource\Pages;

use App\Filament\Alumno\Resources\ReservaResource;
use App\Models\User;
use App\Notifications\NuevaReservaNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class CreateReserva extends CreateRecord
{
    protected static string $resource = ReservaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['estado'] = 'pendiente'; // También asignamos el estado inicial aquí

        return $data;
    }

    protected function afterCreate(): void
    {
        $reserva = $this->getRecord();
        $admins = User::where('es_admin', true)->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NuevaReservaNotification($reserva));
        }
    }
}