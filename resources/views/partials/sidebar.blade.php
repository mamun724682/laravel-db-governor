<nav class="p-4 flex flex-col h-full">
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
                            <li x-show="!tableSearch || '{{ $table }}'.toLowerCase().includes(tableSearch.toLowerCase())">
                                <a
                                    href="{{ route('db-governor.table.show', ['token' => $token, 'connection' => $currentConnection, 'table' => $table]) }}"
                                    class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition truncate"
                                >
                                    🗄 {{ $table }}
                                </a>
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

