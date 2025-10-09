<?php

use App\Http\Controllers\ReservaController;
use Illuminate\Support\Facades\Route;
use Filament\Notifications\Notification;
use App\Models\User;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reservas/{reserva}/aceptar', [ReservaController::class, 'aceptar'])->name('reservas.aceptar');
Route::get('/reservas/{reserva}/rechazar', [ReservaController::class, 'rechazar'])->name('reservas.rechazar');

