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
    <div
        class="rounded-2xl bg-white shadow border border-gray-100 overflow-x-auto"
        x-data="{
            cell: null,
            col: null,
            openIfTrimmed(el) {
                if (el.scrollWidth <= el.offsetWidth) return;
                this.col  = el.dataset.col;
                this.cell = el.dataset.value;
            },
            isJson(val) {
                if (!val) return false;
                const t = val.trim();
                return t.startsWith('{') || t.startsWith('[');
            },
            pretty(val) {
                try { return JSON.stringify(JSON.parse(val), null, 2); } catch(e) { return val; }
            },
            copy() {
                navigator.clipboard.writeText(this.cell ?? '');
            }
        }"
        x-init="$nextTick(() => {
            $el.querySelectorAll('td[data-value]').forEach(td => {
                if (td.scrollWidth > td.offsetWidth) {
                    td.classList.add('cursor-pointer', 'underline', 'decoration-dotted', 'underline-offset-2', 'decoration-gray-400');
                    td.setAttribute('title', 'Click to view full value');
                }
            });
        })"
    >
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
                            @foreach ($row as $colName => $cell)
                                @php $isNull = is_null($cell); @endphp
                                <td
                                    class="px-4 py-2 text-xs max-w-xs truncate {{ $isNull ? 'text-gray-300 italic' : 'text-gray-700 cursor-default' }}"
                                    @if (!$isNull)
                                        data-col="{{ $colName }}"
                                        data-value="{{ $cell }}"
                                        @click="openIfTrimmed($el)"
                                    @endif
                                >{{ $isNull ? 'NULL' : $cell }}</td>
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

        {{-- Cell detail modal --}}
        <div
            x-show="cell !== null"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @click="cell = null"
            @keydown.escape.window="cell = null"
            style="display:none"
        >
            <div class="w-full max-w-2xl mx-4 rounded-2xl bg-white shadow-xl flex flex-col max-h-[80vh]" @click.stop>
                {{-- Header --}}
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                    <span class="text-sm font-semibold text-gray-700" x-text="col"></span>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="copy()"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                        >Copy</button>
                        <button
                            type="button"
                            @click="cell = null"
                            class="text-gray-400 hover:text-gray-600 text-lg leading-none"
                        >&times;</button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="overflow-y-auto p-5 flex-1">
                    <template x-if="isJson(cell)">
                        <pre class="text-xs font-mono text-gray-800 bg-gray-50 rounded-lg border border-gray-200 p-4 overflow-x-auto whitespace-pre-wrap break-all" x-text="pretty(cell)"></pre>
                    </template>
                    <template x-if="!isJson(cell)">
                        <p class="text-sm text-gray-800 whitespace-pre-wrap break-words" x-text="cell"></p>
                    </template>
                </div>
            </div>
        </div>
    </div>
@endsection
