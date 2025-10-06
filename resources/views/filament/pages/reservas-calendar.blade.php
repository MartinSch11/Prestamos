<x-filament::page>
    <div class="space-y-4">
        {{-- Toolbar superior --}}
        <div
            class="flex items-center justify-between bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-4">
                {{ ($this->crearReservaAction)(['size' => 'xs']) }}

                <div class="flex items-center space-x-1">
                    <button wire:click="$set('weekOffset', {{ $weekOffset - 1 }})"
                        class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-700 dark:text-gray-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button type="button" wire:click="$set('weekOffset', 0)"
                        class="text-xs px-3 py-1.5 rounded-md dark:text-white bg-gray-700 dark:bg-gray-700 hover:bg-gray-600 dark:hover:bg-gray-500 transition-colors font-medium">
                        Hoy
                    </button>

                    <button wire:click="$set('weekOffset', {{ $weekOffset + 1 }})"
                        class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-700 dark:text-gray-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <div class="flex items-center">
                    <span class="px-3 text-sm font-semibold text-gray-700 dark:text-gray-200">
                        {{ ucfirst($this->getWeekStart()->locale('es')->isoFormat('MMMM YYYY')) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Grid calendario --}}
        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden border border-gray-200 dark:border-gray-700">
            {{-- Cabecera de días --}}
            <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700 calendar-header">
                @for ($i = 1; $i < 7; $i++)
                    <div class="calendar-header-divider" style="left: calc({{ $i }} * (100% / 7));">
                    </div>
                @endfor

                @for ($i = 0; $i < 7; $i++)
                    @php
                        $day = $this->getWeekStart()->copy()->addDays($i);
                        $isToday = $day->isToday();
                    @endphp

                    {{-- Ajustado el color de fondo de la cabecera para mejor transición --}}
                    <div class="p-3 bg-gray-50 dark:bg-gray-800">
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wide">
                            {{ mb_strtoupper($day->locale('es')->isoFormat('ddd')) }}
                        </div>
                        <div class="mt-1.5">
                            <span
                                class="inline-flex items-center justify-center w-8 h-8 text-sm font-semibold rounded-full {{ $isToday ? 'today-badge' : 'text-gray-700 dark:text-gray-400' }}">
                                {{ $day->isoFormat('D') }}
                            </span>
                        </div>
                    </div>
                @endfor
            </div>
            {{-- Grid de eventos --}}
            <div class="grid grid-cols-7 relative calendar-grid bg-gray-50 dark:bg-gray-800" style="min-height: 400px;">
                {{-- Líneas divisorias verticales --}}
                @for ($i = 1; $i < 7; $i++)
                    <div class="calendar-day-divider" style="left: calc({{ $i }} * (100% / 7));">
                    </div>
                @endfor

                @php
                    $weekStart = $this->getWeekStart();
                    $weekEnd = $this->getWeekEnd();
                    // "occupied" por día -> filas ocupadas (para evitar solapamiento)
                    $occupied = array_fill(0, 7, []);
                @endphp

                @foreach ($this->getEventos() as $evento)
                    @php
                        $start = \Carbon\Carbon::parse($evento['start']);
                        $end = \Carbon\Carbon::parse($evento['end']);

                        // Bandera de si cruza semana
                        $continuaAntes = $start->lt($weekStart);
                        $continuaDespues = $end->gt($weekEnd);

                        // Recortar al rango visible de la semana
                        $clampedStart = $continuaAntes ? $weekStart : $start->copy()->startOfDay();
                        $clampedEnd = $continuaDespues ? $weekEnd : $end->copy()->startOfDay();

                        // Día de inicio/fin dentro de 0..6 (desde el lunes)
                        $startDay = $weekStart->diffInDays($clampedStart, false);
                        $endDay = $weekStart->diffInDays($clampedEnd, false);

                        $startDay = max(0, min(6, $startDay));
                        $endDay = max(0, min(6, $endDay));

                        // Span de columnas (al menos 1)
                        $span = max(1, $endDay - $startDay + 1);

                        // Buscar fila libre (layout de filas por día)
                        $row = 1;
                        while (true) {
                            $conflict = false;
                            for ($d = $startDay; $d <= $endDay; $d++) {
                                if (in_array($row, $occupied[$d])) {
                                    $conflict = true;
                                    break;
                                }
                            }
                            if (!$conflict)
                                break;
                            $row++;
                        }
                        for ($d = $startDay; $d <= $endDay; $d++) {
                            $occupied[$d][] = $row;
                        }

                        // Clip según continuidad
                        $clipClass = $continuaAntes && $continuaDespues ? 'clip-both' :
                            ($continuaAntes ? 'clip-left' :
                                ($continuaDespues ? 'clip-right' : 'clip-'));

                        // Paleta
                        $bgColor = match ($evento['estado']) { 'pendiente' => '#93C5FD', // azul pastel oscuro (Tailwind blue-300)
                            'en_curso' => '#FCD34D', // ámbar pastel oscuro (Tailwind amber-300)
                            'devuelto' => '#86EFAC', // verde pastel oscuro (Tailwind green-300)
                            'bloqueado' => '#FCA5A5', // rojo pastel oscuro (Tailwind red-300)
                            default => '#D1D5DB', // gris pastel oscuro (Tailwind gray-300)
                        };

                        $estadoLabel = match ($evento['estado']) {
                            'pendiente' => 'Pendiente',
                            'en_curso' => 'En Curso',
                            'devuelto' => 'Devuelto',
                            'bloqueado' => 'Bloqueado',
                            default => 'Sin estado'
                        };
                    @endphp

                    <div class="timeline-event-wrapper"
                        style="grid-column: {{ $startDay + 1 }} / span {{ $span }}; grid-row: {{ $row }}; padding: 3px 6px;">

                        <div x-data="{
                                showTooltip: false,
                                tooltipX: 0,
                                tooltipY: 0,
                                arrowX: 0,
                                isBelow: false,
                                positionTooltip() {
                                this.$nextTick(() => {
                                this.$nextTick(() => {
                                const ev = this.$refs.event.getBoundingClientRect();
                                const tp = this.$refs.tooltip.getBoundingClientRect();

                                                        if (tp.width === 0 || tp.height === 0) {
                                                            requestAnimationFrame(() => {
                                                                const tpRetry = this.$refs.tooltip.getBoundingClientRect();

                                                                // Si aún no tiene dimensiones, usar altura estimada
                                                                const tooltipHeight = tpRetry.height > 0 ? tpRetry.height : 150;
                                                                const tooltipWidth = tpRetry.width > 0 ? tpRetry.width : 200;

                                                                this.calculatePosition(ev, { width: tooltipWidth, height: tooltipHeight });
                                                            });
                                                            return;
                                                        }

                                                        this.calculatePosition(ev, tp);
                                                    });
                                                });
                                            },
                                            calculatePosition(ev, tp) {
                                                let top = ev.top - tp.height - 12;

                                                let left = ev.left + (ev.width / 2) - (tp.width / 2);
                                                const eventCenterX = ev.left + (ev.width / 2);

                                                if (left < 10) {
                                                    left = 10;
                                                }
                                                if (left + tp.width > window.innerWidth - 10) {
                                                    left = window.innerWidth - tp.width - 10;
                                                }

                                                let arrowX = eventCenterX - left;
                                                arrowX = Math.max(20, Math.min(tp.width - 20, arrowX));

                                                this.isBelow = false;
                                                this.arrowX = arrowX;
                                                this.tooltipX = left;
                                                this.tooltipY = top;

                                            }
                                        }" @mouseenter="showTooltip = true; positionTooltip()"
                            @mouseleave="showTooltip = false" class="relative w-full h-full">

                            <div x-ref="event" @click="$wire.openReservaModal({{ $evento['id'] }})"
                                class="timeline-event cursor-pointer {{ $clipClass }} {{ $evento['estado'] === 'devuelto' ? 'event-devuelto' : '' }}"
                                style="background-color: {{ $bgColor }};">
                                <div class="timeline-event-content">
                                    <span class="event-title">{{ $evento['title'] }}</span>
                                </div>
                            </div>

                            {{-- Tooltip --}}
                            <div x-ref="tooltip" x-show="showTooltip" x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0" :style="`left: ${tooltipX}px; top: ${tooltipY}px;`"
                                class="timeline-tooltip-alpine" style="display: none;">

                                <div class="tooltip-content">
                                    <div class="font-semibold text-sm text-gray-900 dark:text-white">
                                        {{ $evento['title'] }}
                                    </div>

                                    <div class="text-xs mt-1.5 text-gray-700 dark:text-gray-200">
                                        {{ \Carbon\Carbon::parse($evento['start'])->locale('es')->isoFormat('D MMM YYYY') }}
                                        – {{ \Carbon\Carbon::parse($evento['end'])->locale('es')->isoFormat('D MMM YYYY') }}
                                    </div>

                                    <div class="text-xs mt-1 text-gray-700 dark:text-gray-200">
                                        Estado: <span
                                            class="font-medium text-gray-900 dark:text-white">{{ $estadoLabel }}</span>
                                    </div>

                                    @if(isset($evento['items']) && count($evento['items']) > 0)
                                        <div class="mt-2 pt-2 border-t border-gray-300 dark:border-gray-600">
                                            <div class="text-xs font-semibold mb-1 text-gray-900 dark:text-white">Items:</div>
                                            @foreach($evento['items'] as $item)
                                                <div class="text-xs text-gray-700 dark:text-gray-200">{{ $item }}</div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="tooltip-arrow tooltip-arrow-bottom" :style="`left: ${arrowX}px;`"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>

    <x-filament::modal id="reserva-modal" width="2xl">
        @if($record)
            <div class="space-y-4 p-2">
                {{-- Encabezado con título y estado --}}
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $record->titulo }}
                        </h2>

                        <div class="mt-2 flex">
                            @php
                                $badgeColor = match ($record->estado) {
                                    'pendiente' => 'warning',
                                    'en_curso' => 'info',
                                    'devuelto', 'completado' => 'success',
                                    'bloqueado' => 'danger',
                                    default => 'gray',
                                };

                                $badgeIcon = match ($record->estado) {
                                    'pendiente' => 'heroicon-o-clock',
                                    'en_curso' => 'heroicon-o-bolt',
                                    'devuelto', 'completado' => 'heroicon-o-check-circle',
                                    'bloqueado' => 'heroicon-o-lock-closed',
                                    default => 'heroicon-o-question-mark-circle',
                                };
                            @endphp

                            <x-filament::badge :color="$badgeColor" :icon="$badgeIcon" size="lg"
                                class="text-base font-semibold">
                                {{ ucfirst(str_replace('_', ' ', $record->estado)) }}
                            </x-filament::badge>
                        </div>
                    </div>
                </div>

                {{-- Tiempo de reserva --}}
                <div class="bg-gray-100 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <div
                                class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Período de reserva</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ \Carbon\Carbon::parse($record->inicio)->locale('es')->isoFormat('D [de] MMMM, YYYY [a las] HH:mm') }}
                            </p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ \Carbon\Carbon::parse($record->fin)->locale('es')->isoFormat('D [de] MMMM, YYYY [a las] HH:mm') }}
                            </p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                Duración:
                                {{ \Carbon\Carbon::parse($record->inicio)->diffForHumans(\Carbon\Carbon::parse($record->fin), true) }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Lista de equipos reservados --}}
                @if($record->items && $record->items->count() > 0)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-1">
                            <svg class="w-4 h-4 mr-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m-8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            Equipos reservados
                        </h3>
                        <div class="space-y-2 pt-2">
                            @foreach($record->items as $item)
                                <div
                                    class="flex items-center justify-between p-3 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                                    <div class="flex items-center gap-1">
                                        <div
                                            class="w-8 h-8 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center">
                                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                            </svg>
                                        </div>
                                        <span class="pl-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $item->equipo->nombre }}
                                        </span>
                                    </div>

                                    <span
                                        class="inline-flex items-center px-2.5 py-1 text-xs font-semibold text-gray-700 dark:text-white bg-gray-100 dark:bg-gray-800 rounded-full">
                                        × {{ $item->cantidad }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Botones de acción --}}
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-end gap-3">

                        {{ ($this->eliminarReservaAction) }}

                        @if($record->estado === 'pendiente')
                            <x-filament::button wire:click="marcarEnCurso" color="info" outlined icon="heroicon-o-bolt"
                                size="sm">
                                Marcar en curso
                            </x-filament::button>
                        @endif

                        @if($record->estado === 'en_curso')
                            <x-filament::button wire:click="marcarDevuelto" color="success" outlined
                                icon="heroicon-o-check-circle" size="sm">
                                Marcar como devuelto
                            </x-filament::button>
                        @endif

                        <x-filament::button wire:click="editarReserva" color="primary" outlined icon="heroicon-o-pencil"
                            size="sm" :disabled="in_array($record->estado, ['devuelto', 'completado'])">
                            Editar
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif
    </x-filament::modal>
</x-filament::page>

<x-filament-actions::modals />

@push('styles')
    <style>
        .calendar-grid {
            position: relative;
            overflow: visible !important;
        }

        .calendar-day-divider {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 1px;
            background-color: #e5e7eb;
            pointer-events: none;
            z-index: 0;
        }

        .dark .calendar-day-divider {
            background-color: #374151;
        }

        .calendar-header {
            position: relative;
        }

        .calendar-header-divider {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 1px;
            background-color: #e5e7eb;
            pointer-events: none;
            z-index: 1;
        }

        .dark .calendar-header-divider {
            background-color: #374151;
        }

        .timeline-event-wrapper {
            position: relative;
            height: 44px;
            overflow: visible !important;
            z-index: 1;
        }

        .timeline-event {
            position: relative;
            height: 100%;
            width: 100%;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
            display: flex;
            align-items: center;
            padding: 0 14px;
            font-size: 10px;
            font-weight: 300;
            color: #000;
            transition: all 0.15s ease;
        }

        .timeline-event:hover {
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
            z-index: 100;
        }

        .timeline-event-content {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 4px;
            z-index: 2;
            position: relative;
        }

        .event-title {
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.3;
        }

        .timeline-tooltip-alpine {
            position: fixed;
            z-index: 99999;
            pointer-events: none;
        }

        /* Tooltip content con soporte para modo claro y oscuro */
        .tooltip-content {
            background-color: #ffffff;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.75rem;
            line-height: 1.5;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid #e5e7eb;
            min-width: 200px;
        }

        .dark .tooltip-content {
            background-color: #2a2d34;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            border-color: #374151;
        }

        /* Estilos base para la flecha del tooltip */
        .tooltip-arrow {
            position: absolute;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
        }

        /* Flecha apuntando hacia arriba (tooltip está debajo del evento) */
        .tooltip-arrow-top {
            top: -6px;
            border-bottom: 6px solid #ffffff;
        }

        .dark .tooltip-arrow-top {
            border-bottom-color: #2a2d34;
        }

        /* Flecha apuntando hacia abajo (tooltip está arriba del evento) */
        .tooltip-arrow-bottom {
            bottom: -6px;
            border-top: 6px solid #ffffff;
        }

        .dark .tooltip-arrow-bottom {
            border-top-color: #2a2d34;
        }

        /* Clips para los eventos que continúan */
        .clip- {
            clip-path: inset(0 round 6px);
        }

        .clip-left {
            clip-path: polygon(10px 0%, 100% 0%,
                    100% 100%, 10px 100%,
                    0% 50%);
        }

        .clip-right {
            clip-path: polygon(0% 0%, calc(100% - 10px) 0%,
                    100% 50%,
                    calc(100% - 10px) 100%, 0% 100%);
        }

        .clip-both {
            clip-path: polygon(10px 0%, calc(100% - 10px) 0%,
                    100% 50%,
                    calc(100% - 10px) 100%, 10px 100%,
                    0% 50%);
        }

        .today-badge {
            background-color: #2563eb !important;
            color: white !important;
        }

        .dark .today-badge {
            background-color: #3b82f6 !important;
        }

        .today-badge:hover {
            background-color: #1d4ed8 !important;
        }

        /* Efecto gris para reservas devueltas */
        .timeline-event.event-devuelto::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(156, 163, 175, 0.6);
            /* gray-400 con 60% opacidad */
            border-radius: 6px;
            pointer-events: none;
            z-index: 1;
        }

        .dark .timeline-event.event-devuelto::before {
            background-color: rgba(75, 85, 99, 0.7);
            /* gray-600 con 70% opacidad en modo oscuro */
        }

        .timeline-event.event-devuelto .timeline-event-content {
            opacity: 0.85;
        }

        @media (max-width: 768px) {
            .grid.grid-cols-7 {
                overflow-x: auto;
                min-width: 700px;
            }
        }
    </style>
@endpush