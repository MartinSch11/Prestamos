<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use App\Models\User;

class TestNotificacion extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Test Notificación';
    protected static string $view = 'filament.pages.test-notificacion';

    public function mount(): void
    {
        $admin = User::first();

        Notification::make()
            ->title('Notificación desde Filament')
            ->body('Esta se envió desde dentro del panel.')
            ->color('success')
            ->sendToDatabase($admin);

        Notification::make()
            ->title('Notificación enviada')
            ->body('Se guardó correctamente en la base de datos.')
            ->success()
            ->send();
    }
}
