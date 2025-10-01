<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservaResource\Pages;
use App\Models\Reserva;
use Filament\Actions\DeleteAction;
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
                Forms\Components\TextInput::make('titulo')
                    ->label('Título')
                    ->required(),

                Forms\Components\DateTimePicker::make('inicio')
                    ->label('Fecha inicio')
                    ->required(),

                Forms\Components\DateTimePicker::make('fin')
                    ->label('Fecha fin')
                    ->required()
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
                            ->getOptionLabelFromRecordUsing(function ($record, \Filament\Forms\Get $get) {
                                $inicio = $get('../../inicio');
                                $fin = $get('../../fin');

                                if ($inicio && $fin) {
                                    $disponibles = $record->disponibleEnRango($inicio, $fin);
                                    return "{$record->nombre} (Disponibles: {$disponibles})";
                                }

                                return $record->nombre;
                            }),

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
                    ->columns(2)
                    ->createItemButtonLabel('Agregar equipo'),
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
            'create' => Pages\CreateReserva::route('/create'),
            'edit' => Pages\EditReserva::route('/{record}/edit'),
        ];
    }
}
