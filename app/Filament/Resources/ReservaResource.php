<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservaResource\Pages;
use App\Models\Reserva;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
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
    protected static ?string $navigationLabel = 'Reservas';
    protected static ?string $pluralModelLabel = 'Reservas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Alumno')
                            ->options(User::where('es_admin', false)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive() // Hacemos que el formulario reaccione a los cambios
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

                        // 📅 FECHAS en la misma fila
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('inicio')
                                    ->label('Fecha inicio')
                                    ->required(),

                                Forms\Components\DateTimePicker::make('fin')
                                    ->label('Fecha fin')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $inicio = $get('inicio');

                                        if ($inicio && $state && $state < $inicio) {
                                            // Reset y mensaje inmediato
                                            $set('fin', null);
                                            $set('error_fin', 'La fecha de fin no puede ser anterior a la fecha de inicio.');
                                        } else {
                                            $set('error_fin', null);
                                        }
                                    })
                                    ->helperText(function (Get $get) {
                                        $error = $get('error_fin');

                                        if ($error) {
                                            // 🔴 Rojo en error (modo claro/oscuro)
                                            return new HtmlString(
                                                '<span class="text-danger-600 dark:text-danger-500 font-medium">'
                                                . e($error) .
                                                '</span>'
                                            );
                                        }
                                    }),
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
                                    ->preload()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->options(function (Get $get, ?string $state): array {
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');
                                        $reservaId = $get('../../id');

                                        if (!$inicio || !$fin) {
                                            if ($state && $equipoActual = \App\Models\Equipo::find($state)) {
                                                return [$equipoActual->id => $equipoActual->nombre . ' (Fechas no definidas)'];
                                            }
                                            return [];
                                        }

                                        return \App\Models\Equipo::query()
                                            ->get()
                                            ->mapWithKeys(function ($equipo) use ($inicio, $fin, $reservaId) {
                                            $disponibles = $equipo->disponibleEnRango($inicio, $fin, $reservaId);
                                            return [$equipo->id => "{$equipo->nombre} (Disponibles: {$disponibles})"];
                                        })
                                            ->toArray();
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

                                                $reservaId = $get('../../id');

                                                if ($equipoId && $inicio && $fin) {
                                                    $equipo = \App\Models\Equipo::find($equipoId);

                                                    if ($equipo && $value > $equipo->disponibleEnRango($inicio, $fin, $reservaId)) {
                                                        $fail("No hay suficientes equipos disponibles");
                                                    }
                                                }
                                            };
                                        },
                                    ]),
                            ])
                            ->grid(2)
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
                Tables\Columns\TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('inicio')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fin')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado')->badge()->colors([
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

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Equipos')
                    ->counts('items'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'aceptado' => 'Aceptado',
                        'rechazado' => 'Rechazado',
                        'en_curso' => 'En curso',
                        'devuelto' => 'Devuelto',
                    ]),
            ])
            ->recordUrl(null)
            ->recordAction('view')
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),

                    Tables\Actions\Action::make('en_curso')
                        ->label('Marcar en curso')
                        ->icon('heroicon-o-play')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn(Reserva $record) => $record->estado === 'aceptado')
                        ->action(fn(Reserva $record) => $record->update(['estado' => 'en_curso'])),

                    Tables\Actions\Action::make('devolver')
                        ->label('Marcar como devuelto')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn(Reserva $record) => $record->estado === 'en_curso')
                        ->action(fn(Reserva $record) => $record->update(['estado' => 'devuelto'])),
                ])
                    ->label('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->defaultSort('inicio', 'desc'); // 👉 Orden por defecto descendente
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
            // 'edit' => Pages\EditReserva::route('/{record}/edit'),
        ];
    }

}
