<?php

namespace App\Notifications;

use App\Filament\Pages\ReservasCalendar;
use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;

class NuevaReservaNotification extends Notification
{
    use Queueable;

    public $reserva;

    public function __construct(Reserva $reserva)
    {
        $this->reserva = $reserva;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->reserva->loadMissing('user');

        $calendarUrl = ReservasCalendar::getUrl(
            parameters: ['reserva' => $this->reserva->id],
            panel: 'admin'
        );

        // Filament detectará automáticamente la clave 'url' y hará la notificación clickable
        return [
            'title' => 'Nueva reserva pendiente',
            'body' => 'El alumno ' . $this->reserva->user->name . ' ha solicitado una nueva reserva.',
            'icon' => 'heroicon-o-calendar',
            'color' => 'warning',
            'reserva_id' => $this->reserva->id,
            'url' => $calendarUrl,
            'actions' => [
                [
                    'name' => 'Aceptar',
                    'url' => route('reservas.aceptar', $this->reserva->id),
                    'color' => 'primary',
                ],
                [
                    'name' => 'Rechazar',
                    'url' => route('reservas.rechazar', $this->reserva->id),
                    'color' => 'danger',
                ],
            ],
        ];
    }
}
