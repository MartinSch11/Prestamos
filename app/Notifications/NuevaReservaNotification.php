<?php

namespace App\Notifications;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevaReservaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Reserva $reserva,
        public string $calendarUrl
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];  // Envía por email Y base de datos
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva reserva pendiente - ' . $this->reserva->titulo)
            ->view('emails.nueva-reserva', [
                'admin' => $notifiable,
                'reserva' => $this->reserva,
                'calendarUrl' => $this->calendarUrl,
            ]);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'reserva_id' => $this->reserva->id,
            'titulo' => $this->reserva->titulo,
            'alumno' => $this->reserva->user->name,
            'url' => $this->calendarUrl,
        ];
    }
}
