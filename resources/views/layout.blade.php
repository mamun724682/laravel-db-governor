<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Governance — {{ $currentConnection ?? 'Dashboard' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col" x-data="{ sidebarOpen: false }">

    {{-- Top bar --}}
    <header class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-30">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 gap-3 flex-wrap">

            <div class="flex items-center gap-3">
                {{-- Mobile hamburger --}}
                <button
                    type="button"
                    class="lg:hidden flex-shrink-0 p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 focus:outline-none"
                    @click="sidebarOpen = !sidebarOpen"
                    aria-label="Toggle sidebar"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <span class="text-lg font-bold text-indigo-700">🛡 DB Governance</span>
                @if ($currentConnection)
                    <span class="text-gray-400 hidden sm:inline">/</span>
                    <span class="text-sm font-medium text-gray-600 hidden sm:inline">{{ $currentConnection }}</span>
                @endif
            </div>

            <div class="flex items-center gap-2 sm:gap-4 flex-wrap">
                {{-- Connection switcher --}}
                @include('db-governor::partials.connection-switcher')

                {{-- User badge --}}
                @if ($guardEmail)
                    <div class="flex items-center gap-1.5 sm:gap-2 text-sm text-gray-600">
                        <span class="inline-block w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 font-bold text-center leading-7 flex-shrink-0">
                            {{ strtoupper(substr($guardEmail, 0, 1)) }}
                        </span>
                        <span class="hidden sm:inline truncate max-w-[140px]">{{ $guardEmail }}</span>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-200">
                            {{ $guardRole }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </header>

    {{-- Body --}}
    <div class="flex flex-1 overflow-hidden relative">

        {{-- Mobile overlay --}}
        <div
            x-show="sidebarOpen"
            x-cloak
            class="fixed inset-0 z-20 bg-black/40 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        {{-- Sidebar --}}
        <aside
            class="fixed lg:static inset-y-0 left-0 z-20 w-64 lg:w-56 flex-shrink-0 bg-white border-r border-gray-200 overflow-y-auto transform transition-transform duration-200 ease-in-out lg:translate-x-0 top-0 pt-14 lg:pt-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            @include('db-governor::partials.sidebar')
        </aside>

        {{-- Main content --}}
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 min-w-0">
            @if (session('error'))
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm flex items-start gap-2">
                    <span class="font-bold flex-shrink-0">⚠</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            @if (session('success'))
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm flex items-start gap-2">
                    <span class="font-bold flex-shrink-0">✓</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @yield('content')
        </main>
    </div>

@stack('scripts')
</body>
</html>

