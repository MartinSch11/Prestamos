<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservaResource\Pages;
use App\Models\Reserva;
use App\Models\Equipo;
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
                    ->required(),

                // 🔹 Equipos como detalles de la reserva
                Forms\Components\Repeater::make('items')
                    ->label('Equipos reservados')
                    ->relationship() // usa la relación hasMany Reserva->items
                    ->schema([
                        Forms\Components\Select::make('equipo_id')
                            ->label('Equipo')
                            ->relationship('equipo', 'nombre')
                            ->required()
                            ->preload()
                            ->searchable(),

                        Forms\Components\TextInput::make('cantidad')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
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
                        'success' => 'devuelto',
                    ]),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Equipos')
                    ->counts('items'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'devuelto' => 'Devuelto',
                    ]),
            ])
            // 👇 Esto evita que redireccione a Edit
            ->recordUrl(null)
            // 👇 Esto hace que el click en la fila abra el modal de "Ver"
            ->recordAction('view')
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('devolver')
                        ->label('Marcar como devuelto')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn(Reserva $record) => $record->estado !== 'devuelto')
                        ->action(fn(Reserva $record) => $record->update(['estado' => 'devuelto'])),
                ])
                    ->label('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ]);
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
