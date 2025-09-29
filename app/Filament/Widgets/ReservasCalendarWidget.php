<?php

namespace App\Filament\Widgets;

use App\Models\Equipo;
use App\Models\Reserva;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\Action;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Actions\DeleteAction;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class ReservasCalendarWidget extends FullCalendarWidget
{
    public Model|string|null $model = Reserva::class;

    public function config(): array
    {
        return [
            'firstDay' => 1,
            'headerToolbar' => [
                'left' => 'dayGridMonth,timeGridWeek,timeGridDay',
                'center' => 'title',
                'right' => 'prev,next today',
            ],
            'initialView' => 'dayGridMonth',
        ];
    }

    public function fetchEvents(array $fetchInfo): array
    {
        return Reserva::query()
            ->whereBetween('inicio', [$fetchInfo['start'], $fetchInfo['end']])
            ->get()
            ->map(fn(Reserva $reserva) => [
                'id' => $reserva->id,
                'title' => $reserva->titulo . ' (' . ucfirst($reserva->estado) . ')',
                'start' => $reserva->inicio,
                'end' => $reserva->fin,

                // colores según estado
                'backgroundColor' => match ($reserva->estado) {
                    'pendiente' => '#fbbf24', // amarillo
                    'en_curso' => '#3b82f6', // azul
                    'devuelto' => '#22c55e', // verde
                    default => '#9ca3af', // gris
                },
                'textColor' => '#000000',
            ])
            ->toArray();
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('titulo')
                ->required()
                ->label('Título'),

            Forms\Components\Grid::make()
                ->schema([
                    Forms\Components\DateTimePicker::make('inicio')
                        ->required()
                        ->label('Fecha inicio'),

                    Forms\Components\DateTimePicker::make('fin')
                        ->required()
                        ->label('Fecha fin')
                        ->rules([
                            function (\Filament\Forms\Get $get) {
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
                ->statePath('items') // 👈 en memoria, no relationship
                ->schema([
                    Forms\Components\Select::make('equipo_id')
                        ->label('Equipo')
                        ->options(function (\Filament\Forms\Get $get) {
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
                            function (\Filament\Forms\Get $get) {
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
                ->createItemButtonLabel('Agregar equipo'),
        ];
    }

    protected function modalActions(): array
    {
        return [
            EditAction::make()
                ->after(fn() => $this->refreshRecords()),

            DeleteAction::make()
                ->after(fn() => $this->refreshRecords()),

            Action::make('en_curso')
                ->label('Marcar en curso')
                ->color('info')
                ->visible(fn(Reserva $record) => $record->estado === 'pendiente')
                ->action(function (Reserva $record) {
                    $record->update(['estado' => 'en_curso']);
                    $this->refreshRecords();
                }),

            Action::make('devolver')
                ->label('Marcar como devuelto')
                ->color('success')
                ->visible(fn(Reserva $record) => $record->estado === 'en_curso')
                ->action(function (Reserva $record) {
                    $record->update(['estado' => 'devuelto']);
                    $this->refreshRecords();
                }),
        ];
    }

    protected function afterCreate(Model $record, array $data): void
    {
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $equipo = Equipo::find($item['equipo_id']);

                if (!$equipo) {
                    continue;
                }

                $disponibles = $equipo->disponibleEnRango($data['inicio'], $data['fin']);

                if ($item['cantidad'] > $disponibles) {
                    throw new \Exception("No hay suficientes {$equipo->nombre} disponibles. Disponibles: {$disponibles}");
                }

                $record->items()->create([
                    'equipo_id' => $item['equipo_id'],
                    'cantidad'  => $item['cantidad'],
                ]);
            }
        }
    }

    protected function afterEdit(Model $record, array $data): void
    {
        $record->items()->delete();

        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $record->items()->create([
                    'equipo_id' => $item['equipo_id'],
                    'cantidad'  => $item['cantidad'],
                ]);
            }
        }
    }
}
