<?php

namespace App\Notifications;

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

    /**
     * Create a new notification instance.
     */
    public function __construct(Reserva $reserva)
    {
        $this->reserva = $reserva;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->reserva->loadMissing('user');

        return FilamentNotification::make()
            ->title('Nueva reserva pendiente')
            ->body('El alumno ' . $this->reserva->user->name . ' ha solicitado una nueva reserva.')
            ->actions([
                Action::make('Aceptar')
                    ->url(route('reservas.aceptar', $this->reserva->id))
                    ->button(),
                Action::make('Rechazar')
                    ->url(route('reservas.rechazar', $this->reserva->id))
                    ->color('danger')
                    ->button(),
            ])
            ->getDatabaseMessage() + ['reserva_id' => $this->reserva->id];
    }
}