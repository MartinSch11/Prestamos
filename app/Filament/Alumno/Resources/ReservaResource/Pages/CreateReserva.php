<?php

namespace App\Filament\Alumno\Resources\ReservaResource\Pages;

use App\Filament\Alumno\Resources\ReservaResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class CreateReserva extends CreateRecord 
{

    protected static string $resource = ReservaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['estado'] = 'pendiente';
        unset($data['terms_accepted']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $reserva = $this->getRecord()->loadMissing('user');
        $admins = User::where('es_admin', true)->get();

        if ($admins->isEmpty()) {
            Log::warning('No se encontraron administradores');
            return;
        }

        try {
            $calendarUrl = url('/admin/reservas-calendar?reserva=' . $reserva->id);

            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\NuevaReservaNotification($reserva, $calendarUrl));

                Notification::make()
                    ->title('Nueva reserva pendiente')
                    ->body('El alumno ' . $reserva->user->name . ' ha solicitado una nueva reserva.')
                    ->icon('heroicon-o-calendar')
                    ->iconColor('warning')
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('ver')
                            ->label('Ver detalles')
                            ->url($calendarUrl)
                            ->link(),
                    ])
                    ->sendToDatabase($admin);
            }

        } catch (\Exception $e) {
            Log::error('Error al enviar notificaciones: ' . $e->getMessage());
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '¡Solicitud enviada con éxito!';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('¡Tu petición ha sido enviada!')
            ->body('Te notificaremos cuando sea revisada por un administrador.');
    }
}