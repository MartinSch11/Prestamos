<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservaResource\Pages;
use App\Models\Equipo;
use App\Models\Reserva;
use App\Models\User;
use Closure;
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
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state) {
                                    $userName = User::find($state)?->name;
                                    $set('titulo', 'Reserva ' . $userName);
                                } else {
                                    $set('titulo', null);
                                }
                            }),

                        Forms\Components\TextInput::make('titulo')
                            ->label('Título')
                            ->required(),
                    ]),

                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\DateTimePicker::make('inicio')
                            ->label('Fecha y hora de Inicio')
                            ->prefix('Empieza')
                            ->default(now())
                            ->seconds(false)
                            ->required()
                            ->displayFormat('d/m/Y H:i')
                            ->live(),

                        Forms\Components\DateTimePicker::make('fin')
                            ->label('Fecha y hora de fin')
                            ->required()
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
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('equipo_id')
                            ->label('Equipo')
                            ->live(onBlur: true)
                            ->columnSpan(4)
                            ->required()
                            ->preload()
                            ->searchable()
                            ->options(function (Get $get, ?string $state): array {
                                $inicio = $get('../../inicio');
                                $fin = $get('../../fin');
                                $reservaId = $get('../../id');

                                if (!$inicio || !$fin || $fin <= $inicio) {
                                    if ($state && $equipoActual = Equipo::with('tipo')->find($state)) {
                                        return [$equipoActual->id => $equipoActual->nombre . ' (Fechas no válidas)'];
                                    }
                                    return [];
                                }

                                return Equipo::query()
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

                                $equipo = Equipo::find($value);
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
                                            $equipo = Equipo::find($equipoId);
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
                    ])
                    ->columns(5)
                    ->addActionLabel(label: 'Añadir equipo')
                    ->columnSpanFull(),
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
            ->recordAction('view')
            ->recordUrl(null)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),

                    Tables\Actions\Action::make('aceptado')
                        ->label('Aceptar')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn(Reserva $record) => $record->estado === 'pendiente')
                        ->action(fn(Reserva $record) => $record->update(['estado' => 'aceptado'])),

                    Tables\Actions\Action::make('rechazado')
                        ->label('Rechazar')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn(Reserva $record) => $record->estado === 'pendiente')
                        ->action(fn(Reserva $record) => $record->update(['estado' => 'rechazado'])),

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
            ->defaultSort('inicio', 'desc');
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
