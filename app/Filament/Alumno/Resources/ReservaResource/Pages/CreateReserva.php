<?php

namespace App\Filament\Alumno\Resources\ReservaResource\Pages;

use App\Filament\Alumno\Resources\ReservaResource;
use App\Models\User;
use App\Notifications\NuevaReservaNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log; // Added for debugging

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
        $data['estado'] = 'pendiente';

        return $data;
    }

    protected function afterCreate(): void
    {
        $reserva = $this->getRecord();

        Log::info('[v0] Reserva creada con ID: ' . $reserva->id);

        $admins = User::where('es_admin', true)->get();

        Log::info('[v0] Admins encontrados: ' . $admins->count());

        if ($admins->isEmpty()) {
            Log::warning('[v0] No se encontraron administradores con es_admin = true');
        }

        if ($admins->isNotEmpty()) {
            try {
                Log::info('[v0] Enviando notificación a ' . $admins->count() . ' administrador(es)');

                Notification::send($admins, new NuevaReservaNotification($reserva));

                Log::info('[v0] Notificación enviada exitosamente');

                $notificationCount = DB::table('notifications')
                    ->where('type', 'App\Notifications\NuevaReservaNotification')
                    ->whereNull('read_at')
                    ->count();
                Log::info('[v0] Notificaciones en base de datos: ' . $notificationCount);

            } catch (\Exception $e) {
                Log::error('[v0] Error al enviar notificación: ' . $e->getMessage());
                Log::error('[v0] Stack trace: ' . $e->getTraceAsString());
            }
        }
    }
}
