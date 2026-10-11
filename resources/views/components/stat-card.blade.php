@props(['label', 'value', 'icon', 'color' => 'navy', 'href' => null, 'hint' => null])

@php
    $iconClasses = match ($color) {
        'success' => 'bg-green-50 text-success',
        'warning' => 'bg-amber-50 text-warning',
        'danger' => 'bg-red-50 text-danger',
        'accent' => 'bg-accent-50 text-accent',
        default => 'bg-navy/10 text-navy',
    };
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card group flex items-start gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-md']) }}>
    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $iconClasses }}">
        <x-dynamic-component :component="'lucide-'.$icon" class="h-6 w-6" />
    </span>
    <div class="min-w-0">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        <p class="mt-1 text-3xl font-bold tracking-tight text-navy" data-stat="{{ \Illuminate\Support\Str::slug($label) }}">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
