@props(['title' => null, 'maxWidth' => 'lg'])

@php
    $width = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        default => 'sm:max-w-lg',
    };
@endphp

{{-- Usage: <x-modal wire:model="showModal" title="..."> ... <x-slot:footer>...</x-slot:footer></x-modal> --}}
<div
    x-data="{ open: false }"
    x-modelable="open"
    {{ $attributes->whereStartsWith('wire:model') }}
    x-on:keydown.escape.window="open = false"
    class="no-print"
>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
            <div x-show="open" x-transition.opacity class="fixed inset-0 bg-navy-900/60 backdrop-blur-sm" x-on:click="open = false"></div>

            <div x-show="open" x-transition class="relative w-full {{ $width }} overflow-hidden rounded-2xl bg-white shadow-2xl">
                @if ($title)
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <h2 class="text-lg font-semibold text-navy">{{ $title }}</h2>
                        <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" x-on:click="open = false" aria-label="Close">
                            <x-lucide-x class="h-5 w-5" />
                        </button>
                    </div>
                @endif

                <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </template>
</div>
