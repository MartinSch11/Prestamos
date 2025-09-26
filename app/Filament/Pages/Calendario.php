<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ReservasCalendarWidget;
use Filament\Pages\Page;

class Calendario extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static string $view = 'filament.pages.calendario';
    protected static ?string $title = 'Calendario';

    protected function getHeaderWidgets(): array
    {
        return [
            ReservasCalendarWidget::class,
        ];
    }
}
