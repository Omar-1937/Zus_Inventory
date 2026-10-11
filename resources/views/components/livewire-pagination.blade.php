@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3 px-1 text-sm">
        <p class="text-slate-500">
            Showing <span class="font-semibold text-ink">{{ $paginator->firstItem() }}</span>
            to <span class="font-semibold text-ink">{{ $paginator->lastItem() }}</span>
            of <span class="font-semibold text-ink">{{ $paginator->total() }}</span> results
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-50">Previous</span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="btn btn-secondary btn-sm">Previous</button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-slate-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="btn btn-primary btn-sm">{{ $page }}</span>
                        @else
                            <button type="button" wire:key="page-{{ $paginator->getPageName() }}-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" class="btn btn-secondary btn-sm hidden sm:inline-flex">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="btn btn-secondary btn-sm">Next</button>
            @else
                <span class="btn btn-secondary btn-sm opacity-50">Next</span>
            @endif
        </div>
    </nav>
@endif
