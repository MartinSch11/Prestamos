<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoEquipoResource\Pages;
use App\Models\TipoEquipo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TipoEquipoResource extends Resource
{
    protected static ?string $model = TipoEquipo::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Tipos de equipo';
    protected static ?string $pluralModelLabel = 'Tipos de equipo';
    protected static ?string $modelLabel = 'Tipo de equipo';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->unique(ignoreRecord: true) // evita duplicados
                    ->maxLength(100),
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
            ])
            ->filters([
                // No hace falta por ahora
            ])
            // 👇 Esto evita que redireccione a Edit
            ->recordUrl(null)
            // 👇 Esto hace que el click en la fila abra el modal de "Ver"
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
            'index' => Pages\ListTipoEquipos::route('/'),
            'create' => Pages\CreateTipoEquipo::route('/create'),
            'edit' => Pages\EditTipoEquipo::route('/{record}/edit'),
        ];
    }
}
