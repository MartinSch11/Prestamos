<?php

namespace App\Filament\Alumno\Resources\ReservaResource\Pages;

use App\Filament\Alumno\Resources\ReservaResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

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
        $reserva = $this->getRecord()->loadMissing('user');

        $admins = User::where('es_admin', true)->get();

        if ($admins->isEmpty()) {
            Log::warning('[v5] No se encontraron administradores con es_admin = true');
            return;
        }

        try {
            $calendarUrl = url('/admin/reservas-calendar?reserva=' . $reserva->id);
            $notificationTitle = 'Nueva reserva pendiente';
            $notificationBody = 'El alumno ' . $reserva->user->name . ' ha solicitado una nueva reserva.';

            foreach ($admins as $admin) {
                Notification::make()
                    ->title($notificationTitle)
                    ->body($notificationBody)
                    ->icon('heroicon-o-calendar')
                    ->iconColor('warning')
                    ->actions([
                        Action::make('ver')
                            ->label('Ver detalles')
                            ->url($calendarUrl)
                            ->link(),
                    ])
                    ->sendToDatabase($admin);
            }

        } catch (\Exception $e) {
            Log::error('Error al enviar notificación Filament: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '¡Solicitud enviada con éxito!';
    }

    // Opcional: personalizar el mensaje completo
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('¡Tu petición ha sido enviada!')
            ->body('Te notificaremos cuando sea revisada por un administrador.');
    }
}
