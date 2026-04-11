<nav class="p-4">
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
            <ul class="space-y-0.5" x-data="{ open: null }">
                @foreach ($tables as $table)
                    <li>
                        <a
                            href="{{ route('db-governor.table.show', ['token' => $token, 'connection' => $currentConnection, 'table' => $table]) }}"
                            class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition truncate"
                        >
                            🗄 {{ $table }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endisset
    @endif
</nav>

