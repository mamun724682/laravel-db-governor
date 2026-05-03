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


    {{-- Recent tables (tracked via localStorage) --}}
    <div
        x-data="{
            recentTables: [],
            init() {
                const key = 'dbg_recent_{{ $currentConnection }}';
                this.recentTables = JSON.parse(localStorage.getItem(key) || '[]');
            }
        }"
        class="rounded-2xl bg-white shadow border border-gray-100 p-6"
    >
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-semibold text-gray-800">Recently Visited Tables</h2>
            <span class="text-xs text-gray-400">Click a table in the sidebar to track it here</span>
        </div>

        <template x-if="recentTables.length === 0">
            <p class="text-sm text-gray-400 italic">No tables visited yet. Click any table in the sidebar to get started.</p>
        </template>

        <template x-if="recentTables.length > 0">
            <ul class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                <template x-for="table in recentTables" :key="table.name">
                    <li>
                        <a
                            :href="table.url"
                            class="block rounded-lg border border-gray-100 bg-gray-50 hover:bg-indigo-50 hover:border-indigo-200 px-3 py-2 text-xs font-medium text-gray-700 hover:text-indigo-700 transition truncate"
                            x-text="'🗄 ' + table.name"
                        ></a>
                    </li>
                </template>
            </ul>
        </template>
    </div>
@endsection
