<?php

namespace App\Filament\Pages;

use App\Models\Equipo;
use App\Models\Reserva;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
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

    public function marcarEnCurso()
    {
        if (!$this->record) {
            \Filament\Notifications\Notification::make()
                ->title('Error: No se encontró la reserva')
                ->danger()
                ->send();
            return;
        }

        try {
            $this->record->update(['estado' => 'en_curso']);

            \Filament\Notifications\Notification::make()
                ->title('Reserva marcada en curso')
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
    }

    public function marcarDevuelto()
    {
        if (!$this->record) {
            \Filament\Notifications\Notification::make()
                ->title('Error: No se encontró la reserva')
                ->danger()
                ->send();
            return;
        }

        try {
            $this->record->update(['estado' => 'devuelto']);

            \Filament\Notifications\Notification::make()
                ->title('Reserva marcada como devuelta')
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
    }

    public function editarReserva()
    {
        if (!$this->record) {
            \Filament\Notifications\Notification::make()
                ->title('Error: No se encontró la reserva')
                ->danger()
                ->send();
            return;
        }

        $this->mountAction('edit');
    }

    protected function getActions(): array
    {
        return [
            $this->editAction()->visible(false),
            $this->eliminarReservaAction()->visible(false),
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
            ->disabled(fn() => $this->record && in_array($this->record->estado, ['en_curso','devuelto', 'completado']))
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
                Forms\Components\TextInput::make('titulo')
                    ->required()
                    ->label('Título'),

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
                    ->label('Equipos reservados')
                    ->schema([
                        Forms\Components\Select::make('equipo_id')
                            ->label('Equipo')
                            ->options(function (Forms\Get $get) {
                                $inicio = $get('../../inicio');
                                $fin = $get('../../fin');

                                return Equipo::all()->mapWithKeys(function ($equipo) use ($inicio, $fin) {
                                    $label = $equipo->nombre;
                                    if ($inicio && $fin) {
                                        $disponibles = $equipo->disponibleEnRango($inicio, $fin);
                                        $label .= " (Disponibles: {$disponibles})";
                                    }
                                    return [$equipo->id => $label];
                                });
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive(),

                        Forms\Components\TextInput::make('cantidad')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->rules([
                                function (Forms\Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $equipoId = $get('equipo_id');
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');

                                        if ($equipoId && $inicio && $fin) {
                                            $equipo = Equipo::find($equipoId);
                                            if ($equipo && $value > $equipo->disponibleEnRango($inicio, $fin)) {
                                                $fail("No hay suficientes {$equipo->nombre} disponibles para esa fecha.");
                                            }
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->minItems(1)
                    ->columns(2)
                    ->createItemButtonLabel('Agregar equipo')
                    ->defaultItems(1),
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
                Forms\Components\TextInput::make('titulo')
                    ->required()
                    ->label('Título'),

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
                    ->label('Equipos reservados')
                    ->schema([
                        Forms\Components\Select::make('equipo_id')
                            ->label('Equipo')
                            ->options(function (Forms\Get $get) {
                                $inicio = $get('../../inicio');
                                $fin = $get('../../fin');

                                return Equipo::all()->mapWithKeys(function ($equipo) use ($inicio, $fin) {
                                    $label = $equipo->nombre;
                                    if ($inicio && $fin) {
                                        $disponibles = $equipo->disponibleEnRango($inicio, $fin);
                                        $label .= " (Disponibles: {$disponibles})";
                                    }
                                    return [$equipo->id => $label];
                                });
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive(),

                        Forms\Components\TextInput::make('cantidad')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->rules([
                                function (Forms\Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $equipoId = $get('equipo_id');
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');

                                        if ($equipoId && $inicio && $fin) {
                                            $equipo = Equipo::find($equipoId);
                                            if ($equipo && $value > $equipo->disponibleEnRango($inicio, $fin)) {
                                                $fail("No hay suficientes {$equipo->nombre} disponibles para esa fecha.");
                                            }
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->minItems(1)
                    ->columns(2)
                    ->createItemButtonLabel('Agregar equipo')
                    ->defaultItems(1),
            ])
            ->action(function (array $data) {
                try {
                    $reserva = Reserva::create([
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
