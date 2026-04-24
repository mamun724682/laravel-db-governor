<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Governor — Select Connection</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center p-6">

    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">🛡 DB Governor</h1>
            <p class="text-gray-500 mt-1">Choose a database connection to govern</p>
        </div>

        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            x-data="{ last: localStorage.getItem('dbg_last_connection') }"
        >
            @foreach ($connections as $key => $connectionName)
                <button
                    type="button"
                    onclick="localStorage.setItem('dbg_last_connection', '{{ $key }}'); window.location.href = '{{ route('db-governor.dashboard', ['connection' => $key]) }}';"
                    :class="last === '{{ $key }}' ? 'ring-2 ring-indigo-500 border-indigo-400' : 'border-gray-200 hover:border-indigo-300'"
                    class="relative text-left rounded-2xl bg-white shadow border p-6 transition cursor-pointer focus:outline-none"
                >
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 font-bold text-lg uppercase">
                            {{ substr($key, 0, 1) }}
                        </span>
                        <div>
                            <p class="font-semibold text-gray-800">{{ $key }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $connectionName }}</p>
                        </div>
                    </div>
                    <template x-if="last === '{{ $key }}'">
                        <span class="absolute top-3 right-3 text-xs text-indigo-500 font-medium">Last used</span>
                    </template>
                </button>
            @endforeach
        </div>
    </div>

</body>
</html>

