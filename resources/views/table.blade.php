@extends('db-governor::layout')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-bold text-gray-800">🗄 {{ $table }}</h1>
        <a
            href="{{ route('db-governor.dashboard', ['token' => $token, 'connection' => $currentConnection]) }}"
            class="text-xs text-indigo-600 hover:text-indigo-800"
        >← Dashboard</a>
    </div>

    {{-- Filter builder --}}
    @include('db-governor::partials.filter-builder')

    {{-- Data table --}}
    <div class="rounded-2xl bg-white shadow border border-gray-100 overflow-x-auto">
        @if ($paginator->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-400">No rows found.</div>
        @else
            <table class="min-w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        @foreach ($columns as $col)
                            @php
                                $isActive = request('sort') === $col['name'];
                                $dir      = $isActive && request('dir') === 'asc' ? 'desc' : 'asc';
                                $sortUrl  = request()->fullUrlWithQuery(['sort' => $col['name'], 'dir' => $dir, 'page' => 1]);
                            @endphp
                            <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-indigo-600">
                                    {{ $col['name'] }}
                                    @if ($isActive)
                                        <span>{{ request('dir') === 'asc' ? '↑' : '↓' }}</span>
                                    @else
                                        <span class="text-gray-300">↕</span>
                                    @endif
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($paginator as $row)
                        <tr class="hover:bg-gray-50">
                            @foreach ($row as $cell)
                                <td class="px-4 py-2 text-xs text-gray-700 max-w-xs truncate">
                                    {{ $cell ?? 'NULL' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between gap-4 flex-wrap">
                <span class="text-xs text-gray-400">
                    Page {{ $paginator->currentPage() }} &middot; {{ $paginator->count() }} row(s)
                </span>
                <div class="text-xs">
                    {{ $paginator->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
