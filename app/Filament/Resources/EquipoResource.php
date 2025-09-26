<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipoResource\Pages;
use App\Models\Equipo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EquipoResource extends Resource
{
    protected static ?string $model = Equipo::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Equipos';
    protected static ?string $pluralModelLabel = 'Equipos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('tipo_equipo_id')
                    ->label('Tipo de equipo')
                    ->relationship('tipo', 'nombre')
                    ->required()
                    ->preload()
                    ->searchable()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->unique(\App\Models\TipoEquipo::class, 'nombre')
                            ->maxLength(100),
                    ])
                    ->createOptionAction(fn($action) => $action->modalHeading('Crear nuevo tipo de equipo')),

                Forms\Components\Textarea::make('descripcion')
                    ->label('Descripción')
                    ->rows(3),

                Forms\Components\TextInput::make('cantidad')
                    ->label('Cantidad total')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),

                Forms\Components\Toggle::make('disponible')
                    ->label('Disponible')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->label('ID'),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo.nombre')
                    ->label('Tipo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(50),

                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->sortable(),

                Tables\Columns\TextColumn::make('disponibles')
                    ->label('Disponibles ahora')
                    ->getStateUsing(fn($record) => $record->disponibleEnRango(now(), now()->addDays(30))),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('disponible')
                    ->label('Disponibilidad')
                    ->boolean()
                    ->trueLabel('Solo disponibles')
                    ->falseLabel('Solo no disponibles'),

                Tables\Filters\SelectFilter::make('tipo_equipo_id')
                    ->label('Tipo de equipo')
                    ->relationship('tipo', 'nombre'),
            ])
            ->recordUrl(null)
            ->recordAction('view')
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListEquipos::route('/'),
            'create' => Pages\CreateEquipo::route('/create'),
            'edit' => Pages\EditEquipo::route('/{record}/edit'),
        ];
    }
}
