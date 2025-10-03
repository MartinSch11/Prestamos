<div class="space-y-4 p-2">
    {{-- contenido de tu modal de antes copiado aquí --}}
    <h2 class="text-xl font-bold">{{ $record->titulo }}</h2>
    <p><strong>Estado:</strong> {{ ucfirst($record->estado) }}</p>

    <div class="mt-4">
        <p><strong>Desde:</strong> {{ $record->inicio }}</p>
        <p><strong>Hasta:</strong> {{ $record->fin }}</p>
        <p><strong>Duración:</strong> {{ \Carbon\Carbon::parse($record->inicio)->diffForHumans($record->fin, true) }}
        </p>
    </div>

    <div class="mt-4">
        <h3 class="font-semibold">Equipos:</h3>
        <ul class="list-disc ml-4">
            @foreach($record->items as $item)
                <li>{{ $item->equipo->nombre }} × {{ $item->cantidad }}</li>
            @endforeach
        </ul>
    </div>

    <div class="flex justify-end gap-2 pt-4 border-t">
        <x-filament::button color="danger" wire:click="ejecutarAccion('delete')"
            icon="heroicon-o-trash">Eliminar</x-filament::button>
        @if($record->estado === 'pendiente')
            <x-filament::button color="info" wire:click="ejecutarAccion('en_curso')" icon="heroicon-o-bolt">En
                curso</x-filament::button>
        @endif
        @if($record->estado === 'en_curso')
            <x-filament::button color="success" wire:click="ejecutarAccion('devolver')"
                icon="heroicon-o-check-circle">Devolver</x-filament::button>
        @endif
        <x-filament::button color="primary" wire:click="ejecutarAccion('edit')"
            icon="heroicon-o-pencil">Editar</x-filament::button>
    </div>
</div>