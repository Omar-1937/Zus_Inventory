@props(['color' => 'neutral'])

@php
    $classes = match ($color) {
        'success' => 'bg-green-50 text-green-700 ring-green-600/20',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'danger' => 'bg-red-50 text-red-700 ring-red-600/20',
        'info' => 'bg-accent-50 text-accent ring-accent/20',
        'primary' => 'bg-navy/10 text-navy ring-navy/20',
        default => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset whitespace-nowrap {$classes}"]) }}>
    {{ $slot }}
</span>
