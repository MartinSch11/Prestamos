<?php

namespace App\Filament\Pages;

use App\Models\Equipo;
use App\Models\Reserva;
use App\Models\User;
use App\Notifications\NuevaReservaNotification;
use App\Notifications\ReservaEstadoNotification;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Illuminate\Notifications\DatabaseNotification;
use Filament\Forms\Get;

class ReservasCalendar extends Page implements HasActions
{
    use InteractsWithActions;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Calendario';
    protected static string $view = 'filament.pages.reservas-calendar';
    protected static ?string $title = '';

    public $weekOffset = 0;
    public ?Reserva $record = null;

    public function mount(): void
    {
        // `request('reserva')` obtiene el valor del parámetro 'reserva' desde la URL
        $reservaId = request('reserva');

        if ($reservaId) {
            // Si existe, llamamos a la función que ya tenías para abrir el modal
            $this->openReservaModal($reservaId);
        }
    }

    protected function getListeners(): array
    {
        return [
            'refreshCalendar' => '$refresh',
        ];
    }
    public function getWeekStart(): Carbon
    {
        return now()->startOfWeek(Carbon::MONDAY)->addWeeks($this->weekOffset)->startOfDay();
    }

    public function getWeekEnd(): Carbon
    {
        return $this->getWeekStart()->copy()->addDays(6)->endOfDay();
    }

    public function previousWeek()
    {
        $this->weekOffset--;
    }

    public function nextWeek()
    {
        $this->weekOffset++;
    }

    public function currentWeek()
    {
        $this->weekOffset = 0;
    }

    public function getEventos()
    {
        $weekStart = $this->getWeekStart();
        $weekEnd = $this->getWeekEnd();

        return Reserva::with('items.equipo')
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('inicio', [$weekStart, $weekEnd])
                    ->orWhereBetween('fin', [$weekStart, $weekEnd])
                    ->orWhere(function ($q2) use ($weekStart, $weekEnd) {
                        $q2->where('inicio', '<', $weekStart)
                            ->where('fin', '>', $weekEnd);
                    });
            })
            ->get()
            ->map(function ($reserva) use ($weekStart, $weekEnd) {
                return [
                    'id' => $reserva->id,
                    'title' => $reserva->titulo,
                    'start' => $reserva->inicio,
                    'end' => $reserva->fin,
                    'estado' => $reserva->estado,
                    'items' => $reserva->items->map(fn($i) => $i->equipo->nombre . " (x{$i->cantidad})")->toArray(),
                    'continuaAntes' => $reserva->inicio < $weekStart,
                    'continuaDespues' => $reserva->fin > $weekEnd,
                ];
            });
    }

    public function openReservaModal($id): void
    {
        $this->record = Reserva::with('items.equipo')->findOrFail($id);
        $this->dispatch('open-modal', id: 'reserva-modal');
    }

    public function closeReservaModal(): void
    {
        $this->record = null;
        $this->dispatch('close-modal', id: 'reserva-modal');
    }

    // Acciones de los Botones (Lógica nueva y refactorizada)

    public function aceptarReserva()
    {
        if ($this->record && $this->record->estado === 'pendiente') {
            $this->actualizarEstado('aceptado', 'Reserva Aceptada');
        }
    }

    public function rechazarReserva()
    {
        if ($this->record && $this->record->estado === 'pendiente') {
            $this->actualizarEstado('rechazado', 'Reserva Rechazada');
        }
    }

    public function marcarEnCurso()
    {
        if ($this->record && $this->record->estado === 'aceptado') {
            $this->actualizarEstado('en_curso', 'Reserva marcada como "en curso"');
        }
    }

    public function marcarDevuelto()
    {
        if ($this->record && $this->record->estado === 'en_curso') {
            $this->actualizarEstado('devuelto', 'Reserva marcada como "devuelta"');
        }
    }

    private function actualizarEstado(string $nuevoEstado, string $mensajeExito): void
    {
        if (!$this->record) {
            Notification::make()->title('Error')->body('No se encontró la reserva para actualizar.')->danger()->send();
            return;
        }

        try {
            $this->record->update(['estado' => $nuevoEstado]);
            Notification::make()->title($mensajeExito)->success()->send();

            // Notificamos al alumno también desde el calendario
            if ($nuevoEstado === 'aceptado' || $nuevoEstado === 'rechazado') {
                $this->record->user->notify(new ReservaEstadoNotification($this->record, $nuevoEstado));
            }
            $this->actualizarNotificacionAsociada($this->record, $nuevoEstado);

            $this->closeReservaModal();
            $this->dispatch('refreshCalendar');

        } catch (\Exception $e) {
            Notification::make()->title('Error al actualizar la reserva')->body($e->getMessage())->danger()->send();
        }
    }

    private function actualizarNotificacionAsociada(Reserva $reserva, string $estadoAccion): void
    {
        $reserva->loadMissing('user');

        $notificaciones = DatabaseNotification::query()
            ->where('type', NuevaReservaNotification::class)
            ->where('data->reserva_id', $reserva->id)
            ->get();

        foreach ($notificaciones as $notificacion) {
            $datos = $notificacion->data;

            // Quitamos los botones de acción para que no aparezcan más
            unset($datos['actions']);
            $datos['body'] = "La reserva de {$reserva->user->name} fue {$estadoAccion}.";

            // Actualizamos la notificación y la marcamos como leída
            $notificacion->update([
                'data' => $datos,
                'read_at' => now()
            ]);
        }
    }

    public function editarReserva()
    {
        if (!$this->record) {
            Notification::make()->title('Error')->body('No se encontró la reserva para editar.')->danger()->send();
            return;
        }
        $this->mountAction('edit');
    }

    // --- Definición de Acciones de Filament  ---

    protected function getActions(): array
    {
        return [
            $this->editAction()->visible(false),
            $this->eliminarReservaAction()->visible(false),
            $this->crearReservaAction(),
        ];
    }

    protected function eliminarReservaAction(): Action
    {
        return Action::make('eliminarReserva')
            ->label('Eliminar')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->outlined()
            ->size('sm')
            ->disabled(fn() => $this->record && in_array($this->record->estado, ['en_curso', 'devuelto', 'completado']))
            ->requiresConfirmation()
            ->modalHeading('Eliminar reserva')
            ->modalDescription('¿Estás seguro de que deseas eliminar esta reserva? Esta acción no se puede deshacer.')
            ->modalSubmitActionLabel('Sí, eliminar')
            ->modalCancelActionLabel('Cancelar')
            ->action(function () {
                if (!$this->record) {
                    \Filament\Notifications\Notification::make()
                        ->title('Error: No se encontró la reserva')
                        ->danger()
                        ->send();
                    return;
                }

                try {
                    $this->record->delete();

                    \Filament\Notifications\Notification::make()
                        ->title('Reserva eliminada exitosamente')
                        ->success()
                        ->send();

                    $this->closeReservaModal();
                    $this->dispatch('$refresh');

                } catch (\Exception $e) {
                    \Filament\Notifications\Notification::make()
                        ->title('Error al eliminar la reserva')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Editar Reserva')
            ->modalHeading('Editar Reserva')
            ->modalWidth('2xl')
            ->fillForm(function () {
                if (!$this->record) {
                    return [];
                }

                return [
                    'titulo' => $this->record->titulo,
                    'inicio' => $this->record->inicio,
                    'fin' => $this->record->fin,
                    'items' => $this->record->items->map(fn($item) => [
                        'equipo_id' => $item->equipo_id,
                        'cantidad' => $item->cantidad,
                    ])->toArray(),
                ];
            })
            ->form([
                Forms\Components\Select::make('user_id')
                    ->label('Alumno')
                    ->options(User::where('es_admin', false)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        // Cuando se selecciona un alumno, autocompletamos el título
                        if ($state) {
                            $userName = User::find($state)?->name;
                            $set('titulo', 'Reserva de ' . $userName);
                        } else {
                            $set('titulo', null);
                        }
                    }),
                Forms\Components\TextInput::make('titulo')
                    ->label('Título')
                    ->required(),

                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\DateTimePicker::make('inicio')
                            ->required()
                            ->label('Fecha inicio')
                            ->reactive(),

                        Forms\Components\DateTimePicker::make('fin')
                            ->required()
                            ->label('Fecha fin')
                            ->reactive()
                            ->rules([
                                function (Forms\Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $inicio = $get('inicio');
                                        if ($inicio && $value && $value < $inicio) {
                                            $fail('La fecha de fin no puede ser anterior a la fecha de inicio.');
                                        }
                                    };
                                },
                            ]),
                    ]),

                Forms\Components\Repeater::make('items')
                    ->label('Equipos')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('equipo_id')
                            ->label('Equipo')
                            ->columnSpan(4)
                            ->required()
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->options(function (Get $get, ?string $state): array {
                                $selectedIds = collect($get('../../items'))->pluck('equipo_id')->filter()->all();
                                $inicio = $get('../../inicio');
                                $fin = $get('../../fin');
                                $reservaId = $get('../../id'); // ID de la reserva actual
                                        
                                if (!$inicio || !$fin) {
                                    if ($state && $equipoActual = \App\Models\Equipo::find($state)) {
                                        return [$equipoActual->id => $equipoActual->nombre . ' (Fechas no definidas)'];
                                    }
                                    return [];
                                }
                                $query = \App\Models\Equipo::query()
                                    ->where(function ($query) use ($selectedIds, $state) {
                                        $query->whereNotIn('id', $selectedIds)
                                            ->orWhere('id', $state);
                                    });

                                // 👇 MODIFICAR ESTA LÍNEA para pasar el ID de la reserva
                                return $query->get()->mapWithKeys(function ($equipo) use ($inicio, $fin, $reservaId) {
                                    $disponibles = $equipo->disponibleEnRango($inicio, $fin, $reservaId);
                                    return [$equipo->id => "{$equipo->nombre} (Disponibles: {$disponibles})"];
                                })->toArray();
                            })
                            ->disabled(fn(Get $get): bool => !$get('../../inicio') || !$get('../../fin')),
                        Forms\Components\TextInput::make('cantidad')
                            ->label('Cantidad')
                            ->columnSpan(1)
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->disabled(fn(Get $get): bool => !$get('../../inicio') || !$get('../../fin'))
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $equipoId = $get('equipo_id');
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        $reservaId = $get('../../id');

                                        if ($equipoId && $inicio && $fin) {
                                            $equipo = \App\Models\Equipo::find($equipoId);

                                            // 👇 MODIFICAR ESTA LÍNEA para pasar el ID de la reserva
                                            if ($equipo && $value > $equipo->disponibleEnRango($inicio, $fin, $reservaId)) {
                                                $fail("No hay suficientes {$equipo->nombre} disponibles para esa fecha.");
                                            }
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->minItems(1)
                    ->columns(5)
                    ->createItemButtonLabel(label: 'Añadir equipo')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                if (!$this->record) {
                    \Filament\Notifications\Notification::make()
                        ->title('Error: No se encontró la reserva')
                        ->danger()
                        ->send();
                    return;
                }

                try {
                    $this->record->update([
                        'titulo' => $data['titulo'],
                        'inicio' => $data['inicio'],
                        'fin' => $data['fin'],
                    ]);

                    $this->record->items()->delete();

                    if (!empty($data['items'])) {
                        foreach ($data['items'] as $item) {
                            $this->record->items()->create([
                                'equipo_id' => $item['equipo_id'],
                                'cantidad' => $item['cantidad'],
                            ]);
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Reserva actualizada exitosamente')
                        ->success()
                        ->send();

                    $this->closeReservaModal();
                    $this->dispatch('$refresh');

                } catch (\Exception $e) {
                    \Filament\Notifications\Notification::make()
                        ->title('Error al actualizar la reserva')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected function getHeaderActions(): array
    {
        return [
        ];
    }

    public function crearReservaAction(): Action
    {
        return Action::make('crearReserva')
            ->label('Agregar reserva')
            ->icon('heroicon-o-plus')
            ->size('xs')
            ->button()
            ->color('gray')
            ->extraAttributes([
                'class' => 'border-none font-medium bg-transparent hover:bg-gray-100 text-gray-700 shadow-none'
            ])
            ->modalHeading('Nueva Reserva')
            ->modalWidth('2xl')
            ->form([
                Forms\Components\Select::make('user_id')
                    ->label('Alumno')
                    ->options(User::where('es_admin', false)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        // Cuando se selecciona un alumno, autocompletamos el título
                        if ($state) {
                            $userName = User::find($state)?->name;
                            $set('titulo', 'Reserva de ' . $userName);
                        } else {
                            $set('titulo', null);
                        }
                    }),
                Forms\Components\TextInput::make('titulo')
                    ->label('Título')
                    ->required(),

                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\DateTimePicker::make('inicio')
                            ->required()
                            ->label('Fecha inicio')
                            ->default(now())
                            ->reactive(),

                        Forms\Components\DateTimePicker::make('fin')
                            ->required()
                            ->label('Fecha fin')
                            ->default(now()->addDay())
                            ->reactive()
                            ->rules([
                                function (Forms\Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $inicio = $get('inicio');
                                        if ($inicio && $value && $value < $inicio) {
                                            $fail('La fecha de fin no puede ser anterior a la fecha de inicio.');
                                        }
                                    };
                                },
                            ]),
                    ]),

                Forms\Components\Repeater::make('items')
                    ->label('Equipos')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('equipo_id')
                            ->label('Equipo')
                            ->columnSpan(4)
                            ->required()
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->options(function (Get $get, ?string $state): array {
                                $selectedIds = collect($get('../../items'))->pluck('equipo_id')->filter()->all();
                                $inicio = $get('../../inicio');
                                $fin = $get('../../fin');
                                $reservaId = $get('../../id'); // ID de la reserva actual
                                        
                                if (!$inicio || !$fin) {
                                    if ($state && $equipoActual = \App\Models\Equipo::find($state)) {
                                        return [$equipoActual->id => $equipoActual->nombre . ' (Fechas no definidas)'];
                                    }
                                    return [];
                                }
                                $query = \App\Models\Equipo::query()
                                    ->where(function ($query) use ($selectedIds, $state) {
                                        $query->whereNotIn('id', $selectedIds)
                                            ->orWhere('id', $state);
                                    });

                                // 👇 MODIFICAR ESTA LÍNEA para pasar el ID de la reserva
                                return $query->get()->mapWithKeys(function ($equipo) use ($inicio, $fin, $reservaId) {
                                    $disponibles = $equipo->disponibleEnRango($inicio, $fin, $reservaId);
                                    return [$equipo->id => "{$equipo->nombre} (Disponibles: {$disponibles})"];
                                })->toArray();
                            })
                            ->disabled(fn(Get $get): bool => !$get('../../inicio') || !$get('../../fin')),
                        Forms\Components\TextInput::make('cantidad')
                            ->label('Cantidad')
                            ->columnSpan(1)
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->disabled(fn(Get $get): bool => !$get('../../inicio') || !$get('../../fin'))
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $equipoId = $get('equipo_id');
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        $reservaId = $get('../../id');

                                        if ($equipoId && $inicio && $fin) {
                                            $equipo = \App\Models\Equipo::find($equipoId);

                                            // 👇 MODIFICAR ESTA LÍNEA para pasar el ID de la reserva
                                            if ($equipo && $value > $equipo->disponibleEnRango($inicio, $fin, $reservaId)) {
                                                $fail("No hay suficientes {$equipo->nombre} disponibles para esa fecha.");
                                            }
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->minItems(1)
                    ->columns(5)
                    ->createItemButtonLabel(label: 'Añadir equipo')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                try {
                    $reserva = Reserva::create([
                        'user_id' => $data['user_id'],
                        'titulo' => $data['titulo'],
                        'inicio' => $data['inicio'],
                        'fin' => $data['fin'],
                        'estado' => 'pendiente',
                    ]);

                    if (!empty($data['items'])) {
                        foreach ($data['items'] as $item) {
                            $reserva->items()->create([
                                'equipo_id' => $item['equipo_id'],
                                'cantidad' => $item['cantidad'],
                            ]);
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Reserva creada exitosamente')
                        ->success()
                        ->send();

                    $this->dispatch('$refresh');

                } catch (\Exception $e) {
                    \Filament\Notifications\Notification::make()
                        ->title('Error al crear la reserva')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
