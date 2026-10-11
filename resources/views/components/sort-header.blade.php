@props(['field', 'sortField', 'sortDirection'])

<th {{ $attributes }}>
    <button type="button" wire:click="sortBy('{{ $field }}')" class="group inline-flex items-center gap-1 uppercase hover:text-navy">
        {{ $slot }}
        @if ($sortField === $field)
            @if ($sortDirection === 'asc')
                <x-lucide-arrow-up class="h-3.5 w-3.5 text-accent" />
            @else
                <x-lucide-arrow-down class="h-3.5 w-3.5 text-accent" />
            @endif
        @else
            <x-lucide-arrow-up-down class="h-3.5 w-3.5 text-slate-300 group-hover:text-slate-400" />
        @endif
    </button>
</th>
