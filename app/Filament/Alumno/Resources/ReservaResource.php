<?php

namespace App\Filament\Alumno\Resources;

use App\Filament\Alumno\Resources\ReservaResource\Pages;
use App\Models\Reserva;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReservaResource extends Resource
{
    protected static ?string $model = Reserva::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Reservas';
    protected static ?string $pluralModelLabel = 'Reservas';

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
                                    ->label('Fecha de inicio')
                                    ->minDate(today())
                                    ->prefix('Empieza')
                                    ->default(now())
                                    ->seconds(false)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if (!$state) {
                                            // Si inicio se borra, limpiamos fin y errores
                                            $set('fin', null);
                                            $set('error_fin', null); // Limpiar error si inicio cambia
                                            return;
                                        }
                                        $fin = $get('fin');
                                        // Establecer fin 1 hora después si fin está vacío o es anterior
                                        if (empty($fin) || $fin <= $state) {
                                            $newFin = Carbon::parse($state)->addHour();
                                            $set('fin', $newFin);
                                            // Como estamos estableciendo fin, asegurarnos de que no haya error
                                            $set('error_fin', null);
                                        } else {
                                            // Si fin ya era válido, igual recalculamos helper text
                                            $set('error_fin', null);
                                        }
                                    }),

                                Forms\Components\DateTimePicker::make('fin')
                                    ->label('Fecha de fin')
                                    ->required()
                                    ->reactive()
                                    ->default(now()->addDay())
                                    ->prefix('Termina')
                                    ->seconds(false)
                                    ->minDate(fn(Get $get): ?string => $get('inicio'))
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $inicio = $get('inicio');
                                        // Validar que fin no sea anterior a inicio
                                        if ($inicio && $state && $state <= $inicio) {
                                            $set('error_fin', 'La fecha de fin debe ser posterior a la fecha de inicio.');
                                        } else {
                                            $set('error_fin', null);
                                        }
                                    })
                                    ->helperText(function (Get $get) {
                                        $error = $get('error_fin');
                                        if ($error) {
                                            return new HtmlString(
                                                '<span class="text-danger-600 dark:text-danger-500 font-medium">'
                                                . e($error) .
                                                '</span>'
                                            );
                                        }
                                        $inicio = $get('inicio');
                                        if ($inicio) {
                                            // No mostrar helper si hay error
                                            return new HtmlString('Debe ser posterior a: <span class="font-medium">' . Carbon::parse($inicio)->format('d/m/Y H:i') . '</span>');
                                        }
                                        return null;
                                    }),
                            ]),

                        Forms\Components\Repeater::make('items')
                            ->label('Equipos')
                            ->relationship()
                            ->collapsible()
                            ->schema([
                                Forms\Components\Select::make('equipo_id')
                                    ->label('Equipo')
                                    ->live(onBlur: true)
                                    ->columnSpan(4)
                                    ->required()
                                    ->preload()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->options(function (Get $get, ?string $state): array {
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        $reservaId = $get('../../id');

                                        // Si las fechas no son válidas (o no existen), no mostrar opciones
                                        if (!$inicio || !$fin || $fin <= $inicio) {
                                            if ($state && $equipoActual = \App\Models\Equipo::find($state)) {
                                                return [$equipoActual->id => $equipoActual->nombre . ' (Fechas no válidas)'];
                                            }
                                            return [];
                                        }

                                        return \App\Models\Equipo::query()
                                            ->get()
                                            ->mapWithKeys(function ($equipo) use ($inicio, $fin, $reservaId) {
                                                $disponibles = $equipo->disponibleEnRango($inicio, $fin, $reservaId);
                                                return [$equipo->id => "{$equipo->nombre} (Disponibles: {$disponibles})"];
                                            })
                                            ->filter()
                                            ->toArray();
                                    })
                                    ->disabled(function (Get $get): bool {
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        // Deshabilitado si falta inicio, falta fin, O fin no es posterior a inicio
                                        return !$inicio || !$fin || $fin <= $inicio;
                                    }),
                                Forms\Components\TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->columnSpan(1)
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->disabled(function (Get $get): bool {
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        // Deshabilitado si falta inicio, falta fin, O fin no es posterior a inicio
                                        return !$inicio || !$fin || $fin <= $inicio;
                                    })
                                    ->rules([
                                        function (Get $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $equipoId = $get('equipo_id');
                                                $inicio = $get('../../inicio');
                                                $fin = $get('../../fin');
                                                $reservaId = $get('../../id');

                                                // Solo validar si las fechas y equipo son válidos
                                                if ($equipoId && $inicio && $fin && $fin > $inicio) {
                                                    $equipo = \App\Models\Equipo::find($equipoId);
                                                    if ($equipo && $value > $equipo->disponibleEnRango($inicio, $fin, $reservaId)) {
                                                        $fail("No hay suficientes equipos disponibles");
                                                    }
                                                }
                                            };
                                        },
                                    ]),
                            ])
                            ->minItems(1)
                            ->columns(5)
                            ->addActionLabel(label: 'Añadir equipo')
                            ->columnSpanFull(),
                    ])
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
            'edit' => Pages\EditReserva::route('/{record}/edit'),
            'view' => Pages\ViewReserva::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }
}

