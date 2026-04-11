@extends('db-governor::layout')

@section('content')
    {{-- Stats cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 mb-8">
        @foreach ([
            'pending'     => ['label' => 'Pending',     'color' => 'yellow'],
            'approved'    => ['label' => 'Approved',    'color' => 'green'],
            'executed'    => ['label' => 'Executed',    'color' => 'blue'],
            'rejected'    => ['label' => 'Rejected',    'color' => 'red'],
            'rolled_back' => ['label' => 'Rolled Back', 'color' => 'purple'],
            'blocked'     => ['label' => 'Blocked',     'color' => 'rose'],
        ] as $key => $meta)
            <div class="rounded-xl bg-white shadow border border-gray-100 p-4 text-center">
                <p class="text-2xl font-bold text-gray-800">{{ $stats[$key] ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $meta['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- SQL Console --}}
    <div
        class="rounded-2xl bg-white shadow border border-gray-100 p-6 mb-8"
        x-data="{
            sql: '',
            loading: false,
            result: null,
            error: null,
            writeModal: false,
            pendingWrite: null,
            async run() {
                this.loading = true;
                this.result  = null;
                this.error   = null;
                try {
                    const res = await fetch('{{ route('db-governor.sql.execute', ['token' => $token, 'connection' => $currentConnection]) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                        body: JSON.stringify({ sql: this.sql }),
                    });
                    const data = await res.json();
                    if (data.blocked) {
                        this.error = 'Query blocked: ' + (data.message ?? 'policy violation');
                    } else if (data.type === 'write') {
                        this.pendingWrite = data;
                        this.writeModal   = true;
                    } else {
                        this.result = data;
                    }
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.loading = false;
                }
            }
        }"
    >
        <h2 class="text-base font-semibold text-gray-800 mb-3">SQL Console</h2>

        <textarea
            x-model="sql"
            rows="5"
            placeholder="SELECT * FROM users LIMIT 10;"
            class="w-full rounded-lg border border-gray-300 bg-gray-50 p-3 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
        ></textarea>

        <div class="flex items-center gap-3 mt-3">
            <button
                type="button"
                @click="run()"
                :disabled="loading || !sql.trim()"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold px-4 py-2 transition"
            >
                <span x-show="!loading">▶ Run</span>
                <span x-show="loading">⏳ Running…</span>
            </button>
            <button type="button" @click="sql=''; result=null; error=null;" class="text-sm text-gray-400 hover:text-gray-600">Clear</button>
        </div>

        {{-- Error banner --}}
        <template x-if="error">
            <div class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm" x-text="error"></div>
        </template>

        {{-- Results table --}}
        <template x-if="result && result.rows && result.rows.length > 0">
            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider">
                        <tr>
                            <template x-for="col in Object.keys(result.rows[0])" :key="col">
                                <th class="px-4 py-2 font-semibold" x-text="col"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(row, i) in result.rows" :key="i">
                            <tr class="hover:bg-gray-50">
                                <template x-for="val in Object.values(row)" :key="val">
                                    <td class="px-4 py-2 text-gray-700 truncate max-w-xs" x-text="val ?? 'NULL'"></td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p class="px-4 py-2 text-xs text-gray-400 border-t border-gray-100">
                    <span x-text="result.rows.length"></span> row(s)
                </p>
            </div>
        </template>

        {{-- Write modal --}}
        @include('db-governor::partials.write-modal')
    </div>

    {{-- Tables quick-access --}}
    @if (!empty($tables))
        <div class="rounded-2xl bg-white shadow border border-gray-100 p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-3">Tables</h2>
            <ul class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach ($tables as $table)
                    <li>
                        <a
                            href="{{ route('db-governor.table.show', ['token' => $token, 'connection' => $currentConnection, 'table' => $table]) }}"
                            class="block rounded-lg border border-gray-100 bg-gray-50 hover:bg-indigo-50 hover:border-indigo-200 px-3 py-2 text-xs font-medium text-gray-700 hover:text-indigo-700 transition truncate"
                        >
                            🗄 {{ $table }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
