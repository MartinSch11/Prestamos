<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Notifications\NuevaReservaNotification;
use App\Notifications\ReservaEstadoNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Filament\Notifications\Notification as FilamentNotification;

class ReservaController extends Controller
{
    public function aceptar(Reserva $reserva)
    {
        if ($reserva->estado !== 'pendiente') {
            FilamentNotification::make()
                ->title('Acción no permitida')
                ->body('Esta reserva ya ha sido gestionada.')
                ->warning()
                ->send();
            
            $this->actualizarNotificacion($reserva, $reserva->estado);
            return redirect()->route('filament.admin.resources.reservas.index');
        }

        $reserva->update(['estado' => 'aceptado']);
        
        $this->actualizarNotificacion($reserva, 'aceptada');
        
        // Aseguramos que la relación user está cargada antes de notificar
        $reserva->loadMissing('user');
        $reserva->user->notify(new ReservaEstadoNotification($reserva, 'aceptado'));

        return redirect()->route('filament.admin.resources.reservas.index')
            ->with('success', 'Reserva aceptada correctamente.');
    }

    public function rechazar(Reserva $reserva)
    {
        if ($reserva->estado !== 'pendiente') {
            FilamentNotification::make()
                ->title('Acción no permitida')
                ->body('Esta reserva ya ha sido gestionada.')
                ->warning()
                ->send();

            $this->actualizarNotificacion($reserva, $reserva->estado);
            return redirect()->route('filament.admin.resources.reservas.index');
        }

        $reserva->update(['estado' => 'rechazado']);
        $this->actualizarNotificacion($reserva, 'rechazada');

        // Aseguramos que la relación user está cargada antes de notificar
        $reserva->loadMissing('user');
        $reserva->user->notify(new ReservaEstadoNotification($reserva, 'rechazada'));

        return redirect()->route('filament.admin.resources.reservas.index')
            ->with('success', 'Reserva rechazada correctamente.');
    }

    private function actualizarNotificacion(Reserva $reserva, string $estadoAccion)
    {
        $reserva->loadMissing('user');

        $notificaciones = DatabaseNotification::query()
            ->where('type', NuevaReservaNotification::class)
            ->where('data->reserva_id', $reserva->id)
            ->get();

        foreach ($notificaciones as $notificacion) {
            $datos = $notificacion->data;
            unset($datos['actions']);
            $datos['body'] = "La reserva de {$reserva->user->name} fue {$estadoAccion}.";
            
            $notificacion->update(['data' => $datos, 'read_at' => now()]);
        }
    }
}