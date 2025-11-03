<div class="space-y-6 p-2">
    @php
        $record = $getRecord(); // Para no escribir $getRecord() todo el tiempo
    @endphp

    {{-- Encabezado con título y estado --}}
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <h2 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                {{ $getRecord()->titulo }}
            </h2>

            <div class="mt-2 flex">
                @php
                    $badgeColor = match ($record->estado) {
                        'pendiente' => 'warning',
                        'aceptado' => 'success',
                        'rechazado' => 'danger',
                        'en_curso' => 'info',
                        'devuelto', 'completado' => 'gray',
                        'bloqueado' => 'danger',
                        default => 'gray',
                    };
                    $badgeIcon = match ($record->estado) {
                        'pendiente' => 'heroicon-o-clock',
                        'aceptado' => 'heroicon-o-check-circle',
                        'rechazado' => 'heroicon-o-x-circle',
                        'en_curso' => 'heroicon-o-bolt',
                        'devuelto', 'completado' => 'heroicon-o-check-circle',
                        'bloqueado' => 'heroicon-o-lock-closed',
                        default => 'heroicon-o-question-mark-circle',
                    };
                @endphp

                <x-filament::badge :color="$badgeColor" :icon="$badgeIcon" size="lg" class="text-base font-semibold">
                    {{ ucfirst(str_replace('_', ' ', $record->estado)) }}
                </x-filament::badge>
            </div>
        </div>
    </div>

    {{-- Tiempo de reserva --}}
    <div class="bg-gray-100 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 rounded-lg flex items-center justify-center">
                    <x-heroicon-o-calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
            <div class="flex-1">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Período de
                    reserva</p>
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
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-1">
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 32 32"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M1.735 17.832l12.054 6.081 2.152-6.081-12.053-5.758-2.153 5.758zM16.211 17.832l2.045 6.027 12.484-6.081-2.422-5.704-12.107 5.758zM-0.247 7.212l4.144 4.843 12.053-6.134-3.928-5.005-12.269 6.296zM32.247 7.319l-12.001-6.403-4.09 5.005 12.162 6.134 3.929-4.736zM3.175 19.353l-0.041 5.839 12.713 5.893v-10.98l-1.816 4.736-10.856-5.488zM16.291 20.105v10.979l12.674-5.893v-5.799l-10.99 5.46-1.684-4.747z">
                    </path>
                </svg>
                Equipos reservados
            </h3>

            <div
                class="space-y-1 p-1 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors mt-2">
                @foreach($record->items as $item)
                    <div class="flex items-center justify-between bg-white dark:bg-gray-900 ">
                        <div class="flex items-center gap-1">
                            <svg class="w-3 h-3 text-gray-500 dark:text-gray-400 flex-shrink-0"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.5 7.5a3 3 0 0 1 3-3h9a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3v-9Z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
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
</div>