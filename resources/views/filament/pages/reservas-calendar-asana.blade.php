<x-filament::page>
    <div class="grid grid-cols-7 border border-gray-600 relative auto-rows-[40px]">
        {{-- Cabecera --}}
        @for ($i = 0; $i < 7; $i++)
            @php
                $day = $this->getWeekStart()->copy()->addDays($i);
            @endphp
            <div class="border border-gray-700 p-2 font-bold uppercase text-xs text-white bg-red-800"
                style="grid-column: {{ $i + 1 }}; grid-row: 1;">
                {{ $day->locale('es')->isoFormat('ddd D') }}
            </div>
        @endfor

        {{-- EVENTOS --}}
        @php
            $weekStart = $this->getWeekStart();
            $weekEnd = $this->getWeekEnd();
            $occupied = array_fill(0, 7, []);
        @endphp

        @foreach ($this->getEventos() as $evento)
            @php
                $start = \Carbon\Carbon::parse($evento['start']);
                $end = \Carbon\Carbon::parse($evento['end']);

                $weekStart = $this->getWeekStart()->copy()->startOfDay();
                $weekEnd = $this->getWeekEnd()->copy()->endOfDay();

                // Clamp dentro de la semana
                $clampedStart = $start->lt($weekStart) ? $weekStart : $start->copy()->startOfDay();
                $clampedEnd = $end->gt($weekEnd) ? $weekEnd : $end->copy()->startOfDay();

                // Día dentro de la semana (0-6)
                $startDay = $weekStart->diffInDays($clampedStart, false);
                $endDay = $weekStart->diffInDays($clampedEnd, false);

                $startDay = max(0, min(6, $startDay));
                $endDay = max(0, min(6, $endDay));
                $span = max(1, $endDay - $startDay + 1);

                // Buscar fila libre
                $row = 2;
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

                // Marcar ocupados
                for ($d = $startDay; $d <= $endDay; $d++) {
                    $occupied[$d][] = $row;
                }
            @endphp

            <div class="asana-event
                        {{ $evento['continuaAntes'] ? 'asana-left' : '' }}
                        {{ $evento['continuaDespues'] ? 'asana-right' : '' }}" style="
                        --bg-color: {{ $evento['estado'] === 'pendiente' ? '#a5f3fc' : ($evento['estado'] === 'en_curso' ? '#fde68a' : '#f0abfc') }};
                        grid-column: {{ $startDay + 1 }} / span {{ $span }};
                        grid-row: {{ $row }};
                     ">
                <span class="title">{{ $evento['title'] }}</span>
                <div class="items text-xs">
                    {!! implode('<br>', $evento['items']) !!}
                </div>
            </div>
        @endforeach
    </div>
</x-filament::page>

@push('styles')
    @vite(['resources/css/fullcalendar-asana.css'])
@endpush