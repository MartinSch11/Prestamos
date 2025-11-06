<?php

namespace App\Filament\Alumno\Resources;

use App\Filament\Alumno\Resources\ReservaResource\Pages;
use App\Models\Equipo;
use App\Models\Reserva;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Infolists;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReservaResource extends Resource
{
    protected static ?string $model = Reserva::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Mis Reservas';
    protected static ?string $pluralModelLabel = 'Mis Reservas';

    // public static function canViewAny(): bool
    // {
    //     $user = Auth::user();
    //     if (!$user) {
    //         return false;
    //     }
    //     // Ejemplo:
    //     // Asegúrate que la propiedad `carrera_id` exista en tu modelo User o ajústala.
    //     // return $user->carrera_id === 1; // Ajusta esta lógica
    //     return true; // Temporalmente habilitado para todos para pruebas
    // }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(1)
                    ->schema([
                        Forms\Components\TextInput::make('titulo')
                            ->label('Título')
                            ->default('Reserva ' . Auth::user()->name)
                            ->readOnly()
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('inicio')
                                    ->label('Fecha y hora de Inicio')
                                    ->minDate(today())
                                    ->prefix('Empieza')
                                    ->default(now())
                                    ->seconds(false)
                                    ->required()
                                    ->displayFormat('d/m/Y H:i')
                                    ->live(),

                                Forms\Components\DateTimePicker::make('fin')
                                    ->label('Fecha y hora de fin')
                                    ->required()
                                    ->minDate(today())
                                    ->default(now()->addDay())
                                    ->prefix('Termina')
                                    ->seconds(false)
                                    ->live()
                                    ->displayFormat('d/m/Y H:i')
                                    ->after('inicio')
                                    ->validationMessages([
                                        'after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
                                    ]),
                            ]),

                        Forms\Components\Repeater::make('items')
                            ->label('Equipos')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('equipo_id')
                                    ->label('Equipo')
                                    ->live(onBlur: true)
                                    ->columnSpan(4)
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->options(function (Get $get, ?string $state): array {
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        $reservaId = $get('../../id');

                                        if (!$inicio || !$fin || $fin <= $inicio) {
                                            if ($state && $equipoActual = \App\Models\Equipo::with('tipo')->find($state)) {
                                                return [$equipoActual->id => $equipoActual->nombre . ' (Fechas no válidas)'];
                                            }
                                            return [];
                                        }

                                        return \App\Models\Equipo::query()
                                            ->with('tipo')
                                            ->get()
                                            ->mapWithKeys(function ($equipo) use ($inicio, $fin, $reservaId) {
                                                $disponibles = $equipo->disponibleEnRango($inicio, $fin, $reservaId);
                                                $tipoNombre = $equipo->tipo ? $equipo->tipo->nombre : '';

                                                return [$equipo->id => "{$equipo->nombre} (Disponibles: {$disponibles}) · {$tipoNombre}"];
                                            })
                                            ->filter()
                                            ->toArray();
                                    })
                                    ->disableOptionWhen(function ($value, Get $get) {
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        $reservaId = $get('../../id');

                                        if (!$inicio || !$fin || $fin <= $inicio || !$value) {
                                            return false;
                                        }

                                        $equipo = \App\Models\Equipo::find($value);
                                        if (!$equipo) {
                                            return false;
                                        }

                                        // Verificar disponibilidad
                                        $disponibles = $equipo->disponibleEnRango($inicio, $fin, $reservaId);
                                        if ($disponibles === 0) {
                                            return true;
                                        }

                                        $items = $get('../../items');
                                        $currentIndex = $get('../');

                                        if (!is_array($items)) {
                                            return false;
                                        }

                                        foreach ($items as $index => $item) {
                                            // Saltar el item actual
                                            if ($index === $currentIndex) {
                                                continue;
                                            }

                                            // Si el equipo está seleccionado en otro item, deshabilitar
                                            if (isset($item['equipo_id']) && $item['equipo_id'] == $value) {
                                                return true;
                                            }
                                        }

                                        return false;
                                    })
                                    ->disabled(function (Get $get): bool {
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        return !$inicio || !$fin || $fin <= $inicio;
                                    })
                                    ->validationMessages([
                                        'required' => 'Debes seleccionar un equipo.',
                                    ]),
                                Forms\Components\TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->columnSpan(1)
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->disabled(function (Get $get): bool {
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        return !$inicio || !$fin || $fin <= $inicio;
                                    })
                                    ->rules([
                                        function (Get $get) {
                                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                                $equipoId = $get('equipo_id');
                                                $inicio = $get('../../inicio');
                                                $fin = $get('../../fin');
                                                $reservaId = $get('../../id');

                                                if ($equipoId && $inicio && $fin && $fin > $inicio) {
                                                    $equipo = \App\Models\Equipo::find($equipoId);
                                                    if ($equipo && $value > $equipo->disponibleEnRango($inicio, $fin, $reservaId)) {
                                                        $fail("No hay suficientes equipos disponibles");
                                                    }
                                                }
                                            };
                                        },
                                    ])
                                    ->validationMessages([
                                        'required' => 'La cantidad es obligatoria.',
                                    ]),
                            ])
                            ->minItems(1)
                            ->validationMessages([
                                'min' => 'Debes añadir al menos un equipo a la reserva.',
                            ])->columns(5)
                            ->addActionLabel(label: 'Añadir equipo')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Checkbox::make('terms_accepted')
                    ->label('Acepto los términos y condiciones')
                    ->required()
                    ->accepted()
                    ->validationMessages([
                        'accepted' => 'Debes aceptar los términos y condiciones para continuar.',
                    ])
                    ->columnSpanFull()
                    ->hintAction(
                        Forms\Components\Actions\Action::make('viewTerms')
                            ->label('Ver Términos')
                            ->link()
                            ->modalHeading('Términos y Condiciones')
                            ->modalSubmitAction(false) // Esto oculta el botón de "submit"
                            ->modalCancelActionLabel('Cerrar')
                            ->modalWidth('2xl')
                            ->modalContent(view('filament.alumno.resources.reserva-resource.terms-view'))
                            ->modalFooterActionsAlignment('center')
                    ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('titulo')->label('Título')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('inicio')->label('Inicio')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('fin')->label('Fin')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'warning' => 'pendiente',
                        'success' => 'aceptado',
                        'danger' => 'rechazado',
                        'info' => 'en_curso',
                        'gray' => 'devuelto',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pendiente' => 'Pendiente',
                        'en_curso' => 'En curso',
                        'devuelto' => 'Devuelto',
                        'aceptado' => 'Aceptado',
                        'rechazado' => 'Rechazado',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('items_count')->label('Equipos')->counts('items'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')->label('Estado')->options([
                    'pendiente' => 'Pendiente',
                    'en_curso' => 'En curso',
                    'devuelto' => 'Devuelto',
                    'aceptado' => 'Aceptado',
                    'rechazado' => 'Rechazado',
                ]),
            ])
            ->recordAction('view')
            ->recordUrl(null)
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->modal()
                    ->modalHeading('')
                    ->modalSubmitAction(false)
                    ->modalFooterActionsAlignment('center')
                    ->infolist([
                        Infolists\Components\View::make('filament.alumno.resources.reserva-resource.infolists.reserva-detalle')
                            ->columnSpanFull(),
                    ])
                    ->modalFooterActions(fn(Reserva $record): array => [
                        // Botón de Editar
                        Tables\Actions\Action::make('editar')
                            ->label('Editar')
                            ->outlined()
                            ->icon('heroicon-o-pencil')
                            ->color('warning')
                            ->url(fn(Reserva $record): string => self::getUrl('edit', ['record' => $record]))
                            ->visible(fn(Reserva $record): bool => $record->estado === 'pendiente'),

                        // Botón de Cancelar Reserva
                        Tables\Actions\Action::make('cancelar')
                            ->label('Cancelar')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->outlined()
                            ->requiresConfirmation()
                            ->modalHeading('Confirmar cancelación')
                            ->modalDescription('¿Estás seguro de que quieres cancelar esta reserva?')
                            ->modalSubmitActionLabel('Sí, cancelar')
                            ->visible(fn(Reserva $record): bool => $record->estado === 'pendiente')
                            ->action(function (Reserva $record) {
                                $record->delete();
                                Notification::make()
                                    ->title('Reserva cancelada exitosamente')
                                    ->success()
                                    ->send();
                            }),
                    ]),

                Tables\Actions\DeleteAction::make('cancelar')
                    ->label('Cancelar')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->modalHeading('Confirmar cancelación')
                    ->modalDescription('¿Estás seguro de que quieres cancelar esta reserva?')
                    ->modalSubmitActionLabel('Sí, cancelar')
                    ->visible(fn(Reserva $record): bool => $record->estado === 'pendiente')
                    ->action(function (Reserva $record) {
                        $record->delete();
                        Notification::make()
                            ->title('Reserva cancelada exitosamente')
                            ->body('Tu reserva ha sido cancelada.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn(Reserva $record): bool => $record->estado === 'pendiente'),
            ])
            ->defaultSort('inicio', direction: 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservas::route('/'),
            'create' => Pages\CreateReserva::route('/create'),
            'view' => Pages\ViewReserva::route('/{record}'),
        ];
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
