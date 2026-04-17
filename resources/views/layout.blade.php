<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Governance — {{ $currentConnection ?? 'Dashboard' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col" x-data>

    {{-- Top bar --}}
    <header class="bg-white border-b border-gray-200 shadow-sm">
        <div class="flex items-center justify-between px-6 py-3">

            <div class="flex items-center gap-3">
                <span class="text-lg font-bold text-indigo-700">🛡 DB Governance</span>
                @if ($currentConnection)
                    <span class="text-gray-400">/</span>
                    <span class="text-sm font-medium text-gray-600">{{ $currentConnection }}</span>
                @endif
            </div>

            <div class="flex items-center gap-4">
                {{-- Connection switcher --}}
                @include('db-governor::partials.connection-switcher')

                {{-- User badge --}}
                @if ($guardEmail)
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <span class="inline-block w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 font-bold text-center leading-7">
                            {{ strtoupper(substr($guardEmail, 0, 1)) }}
                        </span>
                        <span>{{ $guardEmail }}</span>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-200">
                            {{ $guardRole }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </header>

    {{-- Body --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- Sidebar --}}
        <aside class="w-56 flex-shrink-0 bg-white border-r border-gray-200 overflow-y-auto">
            @include('db-governor::partials.sidebar')
        </aside>

        {{-- Main content --}}
        <main class="flex-1 overflow-y-auto p-6">
            @yield('content')
        </main>
    </div>

@stack('scripts')
</body>
</html>

