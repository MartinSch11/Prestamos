<?php

namespace App\Filament\Alumno\Resources;

use App\Filament\Alumno\Resources\ReservaResource\Pages;
use App\Models\Reserva;
use Filament\Forms;
use Filament\Forms\Form;
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
                Forms\Components\Grid::make(1)
                    ->schema([
                        Forms\Components\TextInput::make('titulo')
                            ->label('Título')
                            ->default('Reserva ' . auth()->user()->name)
                            ->readOnly()
                            ->required()
                            ->columnSpanFull(),

                        // 📅 FECHAS en la misma fila
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('inicio')
                                    ->label('Fecha inicio')
                                    ->minDate(now())
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
                                    ->columnSpan(4)
                                    ->required()
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->options(function (Get $get, ?string $state): array { // Se añade $state
                            
                                        // Obtenemos los equipos ya seleccionados en OTRAS filas
                                        $selectedIds = collect($get('../../items'))
                                            ->pluck('equipo_id')
                                            ->filter()
                                            ->all();

                                        // Obtenemos las fechas para verificar disponibilidad
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');

                                        // Si no hay fechas, no mostramos opciones
                                        if (!$inicio || !$fin) {
                                            // Si estamos editando, al menos mostramos el item actual
                                            if ($state && $equipoActual = \App\Models\Equipo::find($state)) {
                                                return [$equipoActual->id => $equipoActual->nombre . ' (Fechas no definidas)'];
                                            }
                                            return [];
                                        }

                                        // Creamos la consulta base de equipos
                                        $query = \App\Models\Equipo::query()
                                            // Filtramos los equipos ya seleccionados,
                                            // PERO siempre incluimos el de la fila actual (`$state`)
                                            ->where(function ($query) use ($selectedIds, $state) {
                                            $query->whereNotIn('id', $selectedIds)
                                                ->orWhere('id', $state);
                                        });

                                        // Mapeamos los resultados para añadir la info de disponibilidad
                                        return $query->get()->mapWithKeys(function ($equipo) use ($inicio, $fin) {
                                            $disponibles = $equipo->disponibleEnRango($inicio, $fin);
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
                                    ->disabled(fn(Get $get): bool => !$get('../../inicio') || !$get('../../fin')) // 👈 Deshabilita el select
                                    ->rules([
                                        function (Get $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $equipoId = $get('equipo_id');
                                                $inicio = $get('../../inicio');
                                                $fin = $get('../../fin');

                                                if ($equipoId && $inicio && $fin) {
                                                    $equipo = \App\Models\Equipo::find($equipoId);
                                                    if ($equipo && $value > $equipo->disponibleEnRango($inicio, $fin)) {
                                                        $fail("No hay suficientes {$equipo->nombre} disponibles para esa fecha.");
                                                    }
                                                }
                                            };
                                        },
                                    ]),
                            ])
                            ->minItems(1)
                            ->columns(5) // equipo + cantidad en una sola línea
                            ->createItemButtonLabel(label: 'Añadir equipo')
                            ->columnSpanFull(), // ocupa el ancho total
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

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'warning' => 'pendiente',
                        'info' => 'en_curso',
                        'success' => 'devuelto',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pendiente' => 'Pendiente',
                        'en_curso' => 'En curso',
                        'devuelto' => 'Devuelto',
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
                        'en_curso' => 'En curso',
                        'devuelto' => 'Devuelto',
                    ]),
            ])
            ->recordUrl(null)
            ->recordAction('view')
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Opcional: Permitir al alumno cancelar su propia reserva si está pendiente
                Tables\Actions\DeleteAction::make('Cancelar')
                    ->requiresConfirmation()
                    ->visible(fn(Reserva $record) => $record->estado === 'pendiente'),
            ])
            ->defaultSort('inicio', direction: 'desc'); // 👉 Orden por defecto descendente
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

}
