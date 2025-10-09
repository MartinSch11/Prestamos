<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservaResource\Pages;
use App\Models\Reserva;
use Filament\Actions\DeleteAction;
use Illuminate\Support\HtmlString;
use Filament\Forms\Get;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                        // 🧩 TÍTULO ocupa toda la parte superior
                        Forms\Components\TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->columnSpanFull(),

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

                        // 🧰 REPEATER en una sola línea (fila compacta)
                        Forms\Components\Repeater::make('items')
                            ->label('Equipos reservados')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('equipo_id')
                                    ->label('Equipo')
                                    ->relationship('equipo', 'nombre')
                                    ->required()
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->options(function (Get $get) {
                                        $inicio = $get('../../inicio');
                                        $fin = $get('../../fin');

                                        // Si no hay fechas seleccionadas, no mostrar opciones
                                        if (!$inicio || !$fin) {
                                            return [];
                                        }

                                        // Si hay fechas, mostrar equipos con disponibilidad
                                        return \App\Models\Equipo::all()
                                            ->pluck('nombre', 'id')
                                            ->map(function ($nombre, $id) use ($inicio, $fin) {
                                            $equipo = \App\Models\Equipo::find($id);
                                            $disponibles = $equipo->disponibleEnRango($inicio, $fin);
                                            return "{$nombre} (Disponibles: {$disponibles})";
                                        });
                                    })
                                    ->disabled(fn(Get $get): bool => !$get('../../inicio') || !$get('../../fin')), // 👈 Deshabilita el select

                                Forms\Components\TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->disabled(fn(Get $get): bool => !$get('../../inicio') || !$get('../../fin')) // 👈 Deshabilita el select
                                    ->rules([
                                        function (\Filament\Forms\Get $get) {
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
                            ->columns(2) // equipo + cantidad en una sola línea
                            ->createItemButtonLabel('Agregar equipo')
                            ->columnSpanFull(), // ocupa el ancho total
                    ]),
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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),

                    Tables\Actions\Action::make('en_curso')
                        ->label('Marcar en curso')
                        ->icon('heroicon-o-play')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn(Reserva $record) => $record->estado === 'pendiente')
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
            // 'create' => Pages\CreateReserva::route('/create'),
            // 'edit' => Pages\EditReserva::route('/{record}/edit'),
        ];
    }

}
