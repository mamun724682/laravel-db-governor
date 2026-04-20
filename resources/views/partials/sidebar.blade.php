<nav class="p-4 flex flex-col h-full" @click.stop>
    <div class="flex-1">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Navigation</p>

        @if ($token && $currentConnection)
            <ul class="space-y-1 mb-6">
                <li>
                    <a
                        href="{{ route('db-governor.dashboard', ['token' => $token, 'connection' => $currentConnection]) }}"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition"
                    >
                        📊 Dashboard
                    </a>
                </li>
                <li>
                    <a
                        href="{{ route('db-governor.queries', ['token' => $token, 'connection' => $currentConnection]) }}"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition"
                    >
                        📋 Query Log
                    </a>
                </li>
            </ul>

            @isset($tables)
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">Tables</p>
                <div x-data="{ tableSearch: '' }">
                    <input
                        type="text"
                        x-model="tableSearch"
                        id="table-search"
                        placeholder="Search tables…"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-400 mb-2"
                    >
                    <ul class="space-y-0.5">
                        @foreach ($tables as $table)
                            <li
                                x-data="{ open: false, cols: [], loading: false, loaded: false }"
                                x-show="!tableSearch || '{{ $table }}'.toLowerCase().includes(tableSearch.toLowerCase())"
                            >
                                <div class="flex items-center gap-1 rounded-lg px-2 py-1.5 hover:bg-gray-50 group transition">
                                    {{-- Expand toggle --}}
                                    <button
                                        type="button"
                                        @click="
                                            open = !open;
                                            if (open && !loaded) {
                                                loading = true;
                                                fetch('{{ route('db-governor.schema.table', ['token' => $token, 'connection' => $currentConnection, 'table' => '__T__']) }}'.replace('__T__', '{{ $table }}'))
                                                    .then(r => r.json())
                                                    .then(d => { cols = d.columns || []; loaded = true; loading = false; })
                                                    .catch(() => { loading = false; });
                                            }
                                        "
                                        class="flex-shrink-0 text-gray-400 hover:text-indigo-500 transition w-4 h-4 flex items-center justify-center"
                                        :title="open ? 'Collapse' : 'Expand columns'"
                                    >
                                        <svg x-show="!open" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        <svg x-show="open" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>

                                    {{-- Table link --}}
                                    <a
                                        href="{{ route('db-governor.table.show', ['token' => $token, 'connection' => $currentConnection, 'table' => $table]) }}"
                                        class="flex-1 flex items-center gap-1.5 text-xs text-gray-600 hover:text-indigo-600 transition truncate"
                                    >
                                        🗄 {{ $table }}
                                    </a>
                                </div>

                                {{-- Columns sub-list --}}
                                <div x-show="open" x-cloak class="ml-5 mt-0.5 mb-1 space-y-0.5">
                                    <template x-if="loading">
                                        <p class="text-xs text-gray-400 italic px-2">Loading…</p>
                                    </template>
                                    <template x-for="col in cols" :key="col.name">
                                        <div class="flex items-center gap-1.5 px-2 py-0.5 rounded text-xs text-gray-500 hover:bg-gray-50">
                                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                                                :class="col.required ? 'bg-red-400' : 'bg-gray-300'"></span>
                                            <span class="truncate font-mono" x-text="col.name"></span>
                                            <span class="text-gray-400 truncate ml-auto max-w-[60px]" x-text="col.type"></span>
                                        </div>
                                    </template>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endisset
        @endif
    </div>

    <div class="pt-4 border-t border-gray-200 mt-4">
        @if ($token)
            <form method="POST" action="{{ route('db-governor.logout', ['token' => $token]) }}">
                @csrf
                <button
                    type="submit"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 hover:text-red-700 transition w-full"
                >
                    🚪 Logout
                </button>
            </form>
        @else
            <a
                href="{{ route('db-governor.login') }}"
                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 hover:text-red-700 transition w-full"
            >
                🚪 Logout
            </a>
        @endif
    </div>
</nav>

