@php
    $allConnections = config('db-governor.connections', []);
@endphp

@if (count($allConnections) > 1 && $isLoggedIn)
    <div x-data="{ open: false }" class="relative">
        <button
            type="button"
            @click="open = !open"
            class="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none"
        >
            🔌 {{ $currentConnection ?? 'Switch connection' }}
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div
            x-show="open"
            @click.outside="open = false"
            x-transition
            class="absolute right-0 z-50 mt-1 w-48 rounded-xl bg-white shadow-lg ring-1 ring-black/5 py-1"
        >
            @foreach ($allConnections as $key => $connectionName)
                <button
                    type="button"
                    @click="localStorage.setItem('dbg_last_connection', '{{ $key }}'); window.location.href = '{{ route('db-governor.dashboard', ['connection' => $key]) }}';"
                    class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm {{ $currentConnection === $key ? 'text-indigo-700 bg-indigo-50 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}"
                >
                    @if ($currentConnection === $key)
                        <span class="text-indigo-500">✓</span>
                    @else
                        <span class="w-3.5"></span>
                    @endif
                    {{ $key }}
                    <span class="text-xs text-gray-400 ml-auto">{{ $connectionName }}</span>
                </button>
            @endforeach
        </div>
    </div>
@elseif ($isLoggedIn && count($allConnections) === 1)
    <span class="text-xs text-gray-400">
        🔌 {{ array_key_first($allConnections) }}
    </span>
@endif

