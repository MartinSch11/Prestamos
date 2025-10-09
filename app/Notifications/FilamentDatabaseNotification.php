<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class FilamentDatabaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $title;
    public string $body;
    public string $color;

    public function __construct(string $title, string $body, string $color = 'info')
    {
        $this->title = $title;
        $this->body = $body;
        $this->color = $color;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'color' => $this->color,
            'icon' => 'heroicon-o-bell',
        ];
    }
}
