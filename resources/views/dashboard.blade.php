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
