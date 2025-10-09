<?php

use Illuminate\Support\Facades\Route;
use Filament\Notifications\Notification;
use App\Models\User;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-noti', function () {
    $admin = User::first();

    Notification::make()
        ->title('Notificación de prueba desde web')
        ->body('Funciona correctamente el sistema de notificaciones.')
        ->color('success')
        ->toDatabase($admin);

    return 'Notificación enviada ✅';
});
