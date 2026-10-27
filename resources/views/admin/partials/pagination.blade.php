@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex flex-wrap items-center justify-between gap-4 px-4 py-3 bg-slate-900/70 border border-slate-800 rounded-xl text-xs">
        <div class="text-slate-400">
            Showing
            <span class="font-semibold text-white">{{ $paginator->firstItem() ?? 0 }}</span>
            to
            <span class="font-semibold text-white">{{ $paginator->lastItem() ?? 0 }}</span>
            of
            <span class="font-semibold text-indigo-400">{{ $paginator->total() }}</span>
            results
        </div>

        <div class="flex items-center gap-1.5">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="px-2.5 py-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-600 cursor-not-allowed">
                    &laquo; Prev
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="px-2.5 py-1.5 rounded-lg bg-slate-950 border border-slate-700 text-slate-200 hover:bg-indigo-600 hover:border-indigo-500 hover:text-white transition">
                    &laquo; Prev
                </a>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span class="px-2 py-1.5 text-slate-500">{{ $element }}</span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="px-3 py-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-300 hover:bg-slate-800 hover:text-white transition">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="px-2.5 py-1.5 rounded-lg bg-slate-950 border border-slate-700 text-slate-200 hover:bg-indigo-600 hover:border-indigo-500 hover:text-white transition">
                    Next &raquo;
                </a>
            @else
                <span class="px-2.5 py-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-600 cursor-not-allowed">
                    Next &raquo;
                </span>
            @endif
        </div>
    </nav>
@elseif ($paginator->total() > 0)
    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-900/40 border border-slate-800/70 rounded-xl text-xs text-slate-400">
        <div>
            Showing all <span class="font-semibold text-white">{{ $paginator->total() }}</span> record(s)
        </div>
        <div>Page 1 of 1</div>
    </div>
@endif
