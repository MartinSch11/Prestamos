<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ReservaResource;
use App\Models\Equipo;
use App\Models\Reserva;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\Action; // 👈 para la acción personalizada
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
                'left' => 'dayGridMonth',
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
                'title' => $reserva->titulo,
                'start' => $reserva->inicio,
                'end' => $reserva->fin,
                'url' => ReservaResource::getUrl(name: 'edit', parameters: ['record' => $reserva]),
                'shouldOpenUrlInNewTab' => false,
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
                        ->label('Fecha fin'),
                ]),

            Forms\Components\Repeater::make('items')
                ->label('Equipos reservados')
                ->schema([
                    Forms\Components\Select::make('equipo_id')
                        ->label('Equipo')
                        ->options(fn() => Equipo::pluck('nombre', 'id'))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('cantidad')
                        ->label('Cantidad')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->minItems(1)
                ->columns(2)
                ->createItemButtonLabel('Agregar equipo')
                ->statePath('items'),
        ];
    }

    protected function modalActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            Action::make('devolver')
                ->label('Marcar como devuelto')
                ->color('success')
                ->visible(fn(Reserva $record) => $record->estado !== 'devuelto')
                ->action(fn(Reserva $record) => $record->update(['estado' => 'devuelto'])),
        ];
    }

    protected function afterCreate(Model $record, array $data): void
    {
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $equipo = Equipo::find($item['equipo_id']);

                if (!$equipo)
                    continue;

                $disponibles = $equipo->disponibleEnRango($data['inicio'], $data['fin']);

                if ($item['cantidad'] > $disponibles) {
                    throw new \Exception("No hay suficientes {$equipo->nombre} disponibles. Disponibles: {$disponibles}");
                }

                $record->items()->create([
                    'equipo_id' => $item['equipo_id'],
                    'cantidad' => $item['cantidad'],
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
                    'cantidad' => $item['cantidad'],
                ]);
            }
        }
    }
}
