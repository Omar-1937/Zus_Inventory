@props(['items' => []])

{{-- $items: ['Label' => url-or-null, ...]; the last item is the current page. --}}
<nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-1.5 text-xs text-slate-500">
    <a href="{{ route('dashboard') }}" class="hover:text-accent">Home</a>
    @foreach ($items as $label => $url)
        <x-lucide-chevron-right class="h-3 w-3 shrink-0 text-slate-400" />
        @if ($url && ! $loop->last)
            <a href="{{ $url }}" class="truncate hover:text-accent">{{ $label }}</a>
        @else
            <span class="truncate font-medium text-slate-700" aria-current="page">{{ $label }}</span>
        @endif
    @endforeach
</nav>
