<?php

namespace App\Notifications;

use App\Models\Reserva;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReservaEstadoNotification extends Notification
{
    use Queueable;

    protected $reserva;
    protected $estado;

    public function __construct(Reserva $reserva, string $estado)
    {
        $this->reserva = $reserva;
        $this->estado = $estado;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        // 1. Se obtiene el estado de la reserva
        $textoEstado = ($this->estado === 'aceptado') ? 'aceptada' : 'rechazada';

        // 2. Creacion del titulo y cuerpo
        $title = 'Reserva ' . ucfirst($textoEstado);
        $body = 'Tu reserva' . ' ha sido ' . $textoEstado . '.';

        // 3. Se define el ícono y el color según el estado
        $icon = ($this->estado === 'aceptado') ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
        $color = ($this->estado === 'aceptado') ? 'success' : 'danger';

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->color($color)
            ->getDatabaseMessage();
    }
}