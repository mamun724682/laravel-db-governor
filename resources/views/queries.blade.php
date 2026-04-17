@extends('db-governor::layout')

@php
    $statusColors = [
        'pending'     => 'bg-yellow-50 text-yellow-700 ring-yellow-200',
        'approved'    => 'bg-green-50 text-green-700 ring-green-200',
        'executed'    => 'bg-blue-50 text-blue-700 ring-blue-200',
        'rejected'    => 'bg-red-50 text-red-700 ring-red-200',
        'rolled_back' => 'bg-purple-50 text-purple-700 ring-purple-200',
        'blocked'     => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];

    $riskColors = [
        'low'      => 'bg-green-50 text-green-700',
        'medium'   => 'bg-yellow-50 text-yellow-700',
        'high'     => 'bg-orange-50 text-orange-700',
        'critical' => 'bg-red-50 text-red-700',
    ];
@endphp

@section('content')
    <div x-data="{ consoleOpen: false }">

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-bold text-gray-800">📋 Query Log</h1>
        <button
            type="button"
            @click="consoleOpen = true"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 transition"
        >⌨ SQL Console</button>
    </div>

    {{-- Tab navigation --}}
    <div class="flex gap-1 mb-4 border-b border-gray-200">
        @foreach (['write' => '✏️ Write Queries', 'read' => '👁 Read Queries'] as $tabKey => $tabLabel)
            <a
                href="{{ request()->fullUrlWithQuery(['tab' => $tabKey]) }}"
                class="px-4 py-2 text-sm font-medium rounded-t-lg transition
                    {{ ($tab ?? 'write') === $tabKey
                        ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold bg-white'
                        : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}"
            >{{ $tabLabel }}</a>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap items-center gap-3 mb-6 bg-white rounded-xl border border-gray-100 shadow px-4 py-3">
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="connection" value="{{ $currentConnection }}">

        <select name="status" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="">All statuses</option>
            @foreach (['pending','approved','executed','rejected','rolled_back','blocked'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
            @endforeach
        </select>

        <select name="query_type" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="">All types</option>
            @foreach (['read','write','ddl','unknown'] as $t)
                <option value="{{ $t }}" {{ request('query_type') === $t ? 'selected' : '' }}>{{ strtoupper($t) }}</option>
            @endforeach
        </select>

        <input
            type="text"
            name="keyword"
            value="{{ request('keyword', request('search')) }}"
            placeholder="Search name, SQL or description…"
            class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-56 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        >

        <input
            type="date"
            name="date_from"
            value="{{ request('date_from') }}"
            title="Date from"
            class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        >

        <input
            type="date"
            name="date_to"
            value="{{ request('date_to') }}"
            title="Date to"
            class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        >

        @if ($isAdmin)
            <div class="relative">
                <input
                    type="text"
                    name="submitted_by"
                    value="{{ request('submitted_by') }}"
                    placeholder="Filter by email…"
                    list="submitters-list"
                    autocomplete="off"
                    class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-52 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                @if (!empty($submitters))
                    <datalist id="submitters-list">
                        @foreach ($submitters as $email)
                            <option value="{{ $email }}">
                        @endforeach
                    </datalist>
                @endif
            </div>
        @endif

        <button type="submit" class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-1.5 transition">Filter</button>
        <a href="{{ route('db-governor.queries', ['token' => $token, 'connection' => $currentConnection]) }}" class="text-sm text-gray-400 hover:text-gray-600">Clear</a>
    </form>

    {{-- Queries table --}}
    <div class="rounded-2xl bg-white shadow border border-gray-100 overflow-x-auto" x-data="{ modal: null }">

        @if ($queries->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-400">No queries found.</div>
        @else
            <table class="min-w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-2.5">Name</th>
                        <th class="px-4 py-2.5">Risk</th>
                        <th class="px-4 py-2.5">Status</th>
                        @if ($isAdmin)
                            <th class="px-4 py-2.5">Submitted by</th>
                        @endif
                        <th class="px-4 py-2.5">Snapshot</th>
                        <th class="px-4 py-2.5">Date</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($queries as $query)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 max-w-xs">
                                <p class="font-medium text-gray-800 truncate">{{ $query->name ?? '—' }}</p>
                                <p class="text-xs text-gray-400 font-mono truncate">{{ substr($query->id, 0, 8) }}…</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase {{ $riskColors[$query->risk_level] ?? 'bg-gray-50 text-gray-600' }}">
                                    {{ $query->risk_level }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold uppercase ring-1 ring-inset {{ $statusColors[$query->status] ?? 'bg-gray-50 text-gray-600 ring-gray-200' }}">
                                    {{ strtoupper(str_replace('_', ' ', $query->status)) }}
                                </span>
                            </td>
                            @if ($isAdmin)
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $query->submitted_by }}</td>
                            @endif
                            <td class="px-4 py-3 text-xs text-gray-500">
                                @if ($query->snapshot_table)
                                    <a href="{{ route('db-governor.table.show', ['token' => $token, 'connection' => $currentConnection, 'table' => $query->snapshot_table]) }}"
                                       class="text-indigo-600 hover:text-indigo-800 hover:underline font-mono">
                                        🗄 {{ $query->snapshot_table }}
                                    </a>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400">{{ $query->created_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <button
                                    type="button"
                                    @click="modal = {{ json_encode($query->toArray()) }}"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                >View</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-gray-100">
                {{ $queries->withQueryString()->links() }}
            </div>
        @endif

        {{-- Detail / Action Modal --}}
        <div
            x-show="modal !== null"
            x-transition
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @click="modal = null"
            @keydown.escape.window="modal = null"
            style="display: none;"
        >
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh]" @click.stop>
                <template x-if="modal">
                    <div class="flex flex-col min-h-0">

                        {{-- Modal header --}}
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                            <h3 class="text-base font-semibold text-gray-800" x-text="modal.name || 'Query Detail'"></h3>
                            <button type="button" @click="modal = null" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                        </div>

                        {{-- Scrollable body --}}
                        <div class="overflow-y-auto p-6 space-y-5 flex-1">

                            {{-- Status / Type / Risk badges --}}
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 text-xs font-semibold uppercase" x-text="modal.query_type"></span>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase ring-1 ring-inset bg-gray-50 text-gray-700 ring-gray-200" x-text="modal.status.replace(/_/g, ' ').toUpperCase()"></span>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase bg-gray-50 text-gray-600" x-text="'Risk: ' + modal.risk_level"></span>
                            </div>

                            {{-- SQL --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">SQL</p>
                                <pre class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs font-mono text-gray-700 overflow-x-auto whitespace-pre-wrap" x-text="modal.sql_raw"></pre>
                            </div>

                            {{-- Submission details --}}
                            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 space-y-1.5 text-xs text-gray-600">
                                <p class="font-semibold text-gray-500 uppercase tracking-wider mb-2">Submission</p>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                                    <span class="text-gray-400">ID</span>
                                    <span class="font-mono break-all" x-text="modal.id"></span>
                                    <span class="text-gray-400">Connection</span>
                                    <span x-text="modal.connection || '—'"></span>
                                    <span class="text-gray-400">Submitted by</span>
                                    <span x-text="modal.submitted_by || '—'"></span>
                                    <template x-if="modal.submitted_ip">
                                        <span class="text-gray-400">IP</span>
                                    </template>
                                    <template x-if="modal.submitted_ip">
                                        <span x-text="modal.submitted_ip"></span>
                                    </template>
                                    <span class="text-gray-400">Submitted at</span>
                                    <span x-text="modal.created_at || '—'"></span>
                                    <span class="text-gray-400">Risk level</span>
                                    <span x-text="modal.risk_level || '—'"></span>
                                    <template x-if="modal.estimated_rows !== null && modal.estimated_rows !== undefined">
                                        <span class="text-gray-400">Est. rows</span>
                                    </template>
                                    <template x-if="modal.estimated_rows !== null && modal.estimated_rows !== undefined">
                                        <span x-text="'~' + modal.estimated_rows"></span>
                                    </template>
                                </div>
                                <template x-if="modal.description">
                                    <p class="pt-1"><span class="text-gray-400">Reason: </span><span x-text="modal.description"></span></p>
                                </template>
                                <template x-if="modal.risk_note">
                                    <p><span class="text-gray-400">Risk note: </span><span x-text="modal.risk_note"></span></p>
                                </template>
                            </div>

                            {{-- Risk flags --}}
                            <template x-if="modal.risk_flags && modal.risk_flags.length">
                                <div class="rounded-lg border border-orange-100 bg-orange-50 p-3">
                                    <p class="text-xs font-semibold text-orange-600 uppercase tracking-wider mb-1">Risk Flags</p>
                                    <ul class="space-y-1">
                                        <template x-for="flag in modal.risk_flags" :key="flag">
                                            <li class="text-xs text-orange-700 flex gap-1"><span>⚠</span><span x-text="flag"></span></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                            {{-- Review details --}}
                            <template x-if="modal.reviewed_by">
                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-500 uppercase tracking-wider mb-2">Review</p>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                                        <span class="text-gray-400">Reviewed by</span>
                                        <span x-text="modal.reviewed_by"></span>
                                        <span class="text-gray-400">Reviewed at</span>
                                        <span x-text="modal.reviewed_at || '—'"></span>
                                        <template x-if="modal.review_note">
                                            <span class="text-gray-400">Note</span>
                                        </template>
                                        <template x-if="modal.review_note">
                                            <span x-text="modal.review_note"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Execution details --}}
                            <template x-if="modal.executed_by">
                                <div class="rounded-lg border border-gray-100 bg-blue-50 p-4 text-xs text-gray-600">
                                    <p class="font-semibold text-blue-600 uppercase tracking-wider mb-2">Execution</p>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                                        <span class="text-gray-400">Executed by</span>
                                        <span x-text="modal.executed_by"></span>
                                        <span class="text-gray-400">Executed at</span>
                                        <span x-text="modal.executed_at || '—'"></span>
                                        <template x-if="modal.rows_affected !== null && modal.rows_affected !== undefined">
                                            <span class="text-gray-400">Rows affected</span>
                                        </template>
                                        <template x-if="modal.rows_affected !== null && modal.rows_affected !== undefined">
                                            <span x-text="modal.rows_affected"></span>
                                        </template>
                                        <template x-if="modal.execution_time_ms !== null && modal.execution_time_ms !== undefined">
                                            <span class="text-gray-400">Duration</span>
                                        </template>
                                        <template x-if="modal.execution_time_ms !== null && modal.execution_time_ms !== undefined">
                                            <span x-text="modal.execution_time_ms + ' ms'"></span>
                                        </template>
                                        <template x-if="modal.snapshot_table">
                                            <span class="text-gray-400">Snapshot table</span>
                                        </template>
                                        <template x-if="modal.snapshot_table">
                                            <span x-text="modal.snapshot_table"></span>
                                        </template>
                                        <template x-if="modal.snapshot_primary_key">
                                            <span class="text-gray-400">Snapshot PK</span>
                                        </template>
                                        <template x-if="modal.snapshot_primary_key">
                                            <span x-text="modal.snapshot_primary_key"></span>
                                        </template>
                                        <template x-if="modal.snapshot_strategy">
                                            <span class="text-gray-400">Snapshot strategy</span>
                                        </template>
                                        <template x-if="modal.snapshot_strategy">
                                            <span x-text="modal.snapshot_strategy"></span>
                                        </template>
                                        <template x-if="modal.snapshot_size_bytes">
                                            <span class="text-gray-400">Snapshot size</span>
                                        </template>
                                        <template x-if="modal.snapshot_size_bytes">
                                            <span x-text="(modal.snapshot_size_bytes / 1024).toFixed(1) + ' KB'"></span>
                                        </template>
                                    </div>
                                    <template x-if="modal.execution_error">
                                        <p class="mt-2 text-red-600"><span class="font-medium">Error: </span><span x-text="modal.execution_error"></span></p>
                                    </template>
                                    <template x-if="modal.snapshot_data">
                                        <div class="mt-3">
                                            <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider mb-1">Snapshot Data
                                                <template x-if="modal.snapshot_table">
                                                    <span class="normal-case font-normal text-blue-400 ml-1">(<span x-text="modal.snapshot_table"></span>)</span>
                                                </template>
                                            </p>
                                            <pre class="rounded-lg bg-white border border-blue-100 p-3 text-xs font-mono text-gray-700 overflow-x-auto max-h-48 whitespace-pre-wrap" x-text="typeof modal.snapshot_data === 'string' ? modal.snapshot_data : JSON.stringify(modal.snapshot_data, null, 2)"></pre>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Rollback details --}}
                            <template x-if="modal.rolled_back_by">
                                <div class="rounded-lg border border-purple-100 bg-purple-50 p-4 text-xs text-gray-600">
                                    <p class="font-semibold text-purple-600 uppercase tracking-wider mb-2">Rollback</p>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                                        <span class="text-gray-400">Rolled back by</span>
                                        <span x-text="modal.rolled_back_by"></span>
                                        <span class="text-gray-400">Rolled back at</span>
                                        <span x-text="modal.rolled_back_at || '—'"></span>
                                    </div>
                                    <template x-if="modal.rollback_sql">
                                        <div class="mt-3">
                                            <p class="text-xs font-semibold text-purple-600 uppercase tracking-wider mb-1">Rollback SQL</p>
                                            <pre class="rounded-lg bg-purple-100 border border-purple-200 p-3 text-xs font-mono text-purple-800 overflow-x-auto whitespace-pre-wrap" x-text="modal.rollback_sql"></pre>
                                        </div>
                                    </template>
                                    <template x-if="modal.rollback_error">
                                        <p class="mt-2 text-red-600"><span class="font-medium">Error: </span><span x-text="modal.rollback_error"></span></p>
                                    </template>
                                </div>
                            </template>

                            @if ($guardRole === 'admin')
                                {{-- Approve / Reject --}}
                                <template x-if="modal.status === 'pending'">
                                    <div class="space-y-3 border-t border-gray-100 pt-4">
                                        <form
                                            :action="`{{ $tokenBaseUrl ?? '' }}/` + modal.connection + '/queries/' + modal.id + '/approve'"
                                            method="POST"
                                        >
                                            @csrf
                                            <div class="mb-2">
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Approval note (optional)</label>
                                                <input type="text" name="note" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Why is this safe?">
                                            </div>
                                            <button type="submit" class="rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-1.5 transition">Approve</button>
                                        </form>

                                        <form
                                            :action="`{{ $tokenBaseUrl ?? '' }}/` + modal.connection + '/queries/' + modal.id + '/reject'"
                                            method="POST"
                                        >
                                            @csrf
                                            <div class="mb-2">
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Rejection reason</label>
                                                <input type="text" name="note" required class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Why is this being rejected?">
                                            </div>
                                            <button type="submit" class="rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-1.5 transition">Reject</button>
                                        </form>
                                    </div>
                                </template>

                                {{-- Execute --}}
                                <template x-if="modal.status === 'approved'">
                                    <div class="border-t border-gray-100 pt-4">
                                        <form
                                            :action="`{{ $tokenBaseUrl ?? '' }}/` + modal.connection + '/queries/' + modal.id + '/execute'"
                                            method="POST"
                                        >
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-1.5 transition">Execute</button>
                                        </form>
                                    </div>
                                </template>

                                {{-- Rollback --}}
                                <template x-if="modal.status === 'executed' && modal.snapshot_data">
                                    <div class="border-t border-gray-100 pt-4">
                                        <form
                                            :action="`{{ $tokenBaseUrl ?? '' }}/` + modal.connection + '/queries/' + modal.id + '/rollback'"
                                            method="POST"
                                        >
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-4 py-1.5 transition">Rollback</button>
                                        </form>
                                    </div>
                                </template>
                            @endif

                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- SQL Console Modal --}}
    <div
        x-show="consoleOpen"
        x-transition
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="consoleOpen = false"
        @keydown.escape.window="consoleOpen = false"
        style="display: none;"
        data-endpoint="db-governor.sql.execute"
    >
        <div class="w-full max-w-3xl rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh]" @click.stop x-data="sqlConsole()">

            {{-- Console header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-800">⌨ SQL Console</h3>
                <button type="button" @click="consoleOpen = false" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
            </div>

            {{-- Tab navigation --}}
            <div class="flex gap-1 px-6 pt-4 border-b border-gray-100 flex-shrink-0">
                <button
                    type="button"
                    @click="consoleTab = 'builder'"
                    :class="consoleTab === 'builder' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm -mb-px transition"
                >Query Builder</button>
                <button
                    type="button"
                    @click="consoleTab = 'raw'"
                    :class="consoleTab === 'raw' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm -mb-px transition"
                >Raw SQL</button>
            </div>

            {{-- Scrollable body --}}
            <div class="overflow-y-auto p-6 flex-1 space-y-4">

                {{-- Raw SQL Tab --}}
                <div x-show="consoleTab === 'raw'">
                    <div class="relative" data-autocomplete="true">
                        <textarea
                            x-model="sql"
                            rows="6"
                            placeholder="SELECT * FROM users LIMIT 10;"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 p-3 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                            @input="autocomplete(sql)"
                            @keydown="acKeydown($event)"
                        ></textarea>
                        {{-- Autocomplete dropdown --}}
                        <ul
                            x-show="autocompleteList.length > 0"
                            class="absolute z-10 left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto text-sm font-mono"
                            style="display:none;"
                        >
                            <template x-for="(item, idx) in autocompleteList" :key="item">
                                <li
                                    @click="insertSuggestion(item)"
                                    :class="idx === autocompleteIndex ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
                                    class="px-4 py-1.5 cursor-pointer"
                                    x-text="item"
                                ></li>
                            </template>
                        </ul>
                    </div>

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
                                            <template x-for="(val, colIdx) in Object.values(row)" :key="colIdx">
                                                <td class="px-4 py-2 text-gray-700 truncate max-w-xs">
                                                    <template x-if="val === null">
                                                        <span class="text-gray-400 italic font-mono text-xs">NULL</span>
                                                    </template>
                                                    <template x-if="val !== null">
                                                        <span x-text="typeof val === 'object' ? JSON.stringify(val) : String(val)"></span>
                                                    </template>
                                                </td>
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
                </div>

                {{-- Query Builder Tab --}}
                <div x-show="consoleTab === 'builder'" class="space-y-4">

                    {{-- Query type selector --}}
                    <div class="flex gap-2">
                        @foreach(['SELECT','INSERT','UPDATE','DELETE'] as $qtype)
                        <button
                            type="button"
                            @click="qbType = '{{ $qtype }}'"
                            :class="qbType === '{{ $qtype }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                        >{{ $qtype }}</button>
                        @endforeach
                    </div>

                    {{-- Table selector --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Table</label>
                        <select
                            x-model="qbTable"
                            @change="loadColumns($event.target.value)"
                            class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="">— Select table —</option>
                            @foreach ($tables as $tbl)
                                <option value="{{ $tbl }}">{{ $tbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- SELECT: columns + WHERE + ORDER + LIMIT --}}
                    <template x-if="qbType === 'SELECT'">
                        <div class="space-y-3">
                            <template x-if="qbColumns.length > 0">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Columns (leave empty for *)</label>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="col in qbColumns" :key="col.name">
                                            <label class="inline-flex items-center gap-1 text-xs text-gray-700 cursor-pointer">
                                                <input type="checkbox" :value="col.name" x-model="qbSelectedColumns" class="rounded border-gray-300 text-indigo-600">
                                                <span x-text="col.name"></span>
                                                <span class="text-gray-400 font-mono text-xs" x-text="col.type ? '(' + col.type + ')' : ''"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">WHERE column</label>
                                    <input type="text" x-model="qbWhereCol" placeholder="e.g. id" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Operator</label>
                                    <select x-model="qbWhereOp" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                        <option>=</option><option>!=</option><option>&gt;</option><option>&lt;</option><option>LIKE</option><option>IS NULL</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Value</label>
                                    <input type="text" x-model="qbWhereVal" placeholder="value" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">ORDER BY</label>
                                    <input type="text" x-model="qbOrderBy" placeholder="column" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Direction</label>
                                    <select x-model="qbOrderDir" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                        <option>ASC</option><option>DESC</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">LIMIT</label>
                                    <input type="number" x-model="qbLimit" min="1" max="1000" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- INSERT: auto-populate all columns, value input only --}}
                    <template x-if="qbType === 'INSERT'">
                        <div class="space-y-3">
                            <template x-if="!qbTable">
                                <p class="text-xs text-gray-400 italic">Select a table above to load columns.</p>
                            </template>
                            <template x-if="qbTable && qbColumns.length === 0">
                                <p class="text-xs text-gray-400 italic">Loading columns…</p>
                            </template>
                            <template x-if="qbColumns.length > 0">
                                <div class="space-y-2">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">Values</label>
                                    <template x-for="(col, i) in qbColumns" :key="col.name">
                                        <div class="flex gap-3 items-center">
                                            <span class="w-36 text-xs font-mono text-gray-700 truncate flex-shrink-0" x-text="col.name + (col.type ? ' (' + col.type + ')' : '')"></span>
                                            <span class="text-gray-300 text-xs">=</span>
                                            <input type="text" :placeholder="col.type || 'value'"
                                                x-model="qbInsertRows[i].val"
                                                class="flex-1 rounded-lg border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- UPDATE: SET col (select) = val + WHERE col (select) --}}
                    <template x-if="qbType === 'UPDATE'">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">SET</label>
                                <button type="button" @click="qbSetRows.push({col:'',val:''})" class="text-xs text-indigo-600 hover:text-indigo-800">+ Add column</button>
                            </div>
                            <template x-for="(row, i) in qbSetRows" :key="i">
                                <div class="flex gap-2 items-center">
                                    <select x-model="row.col" class="flex-1 rounded-lg border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                        <option value="">— column —</option>
                                        <template x-for="col in qbColumns" :key="col.name">
                                            <option :value="col.name" x-text="col.name"></option>
                                        </template>
                                    </select>
                                    <span class="text-gray-400 text-xs">=</span>
                                    <input type="text" x-model="row.val" placeholder="value" class="flex-1 rounded-lg border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <button type="button" @click="qbSetRows.splice(i,1)" class="text-gray-400 hover:text-red-500 text-xs">✕</button>
                                </div>
                            </template>
                            <div class="grid grid-cols-3 gap-2 pt-1">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">WHERE column</label>
                                    <select x-model="qbWhereCol" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                        <option value="">— column —</option>
                                        <template x-for="col in qbColumns" :key="col.name">
                                            <option :value="col.name" x-text="col.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Operator</label>
                                    <select x-model="qbWhereOp" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                        <option>=</option><option>!=</option><option>&gt;</option><option>&lt;</option><option>LIKE</option><option>IS NULL</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Value</label>
                                    <input type="text" x-model="qbWhereVal" placeholder="value" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- DELETE: WHERE col (select) --}}
                    <template x-if="qbType === 'DELETE'">
                        <div class="space-y-3">
                            <div class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
                                ⚠ DELETE without a WHERE will remove all rows. Specify a condition below.
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">WHERE column</label>
                                    <select x-model="qbWhereCol" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                        <option value="">— column —</option>
                                        <template x-for="col in qbColumns" :key="col.name">
                                            <option :value="col.name" x-text="col.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Operator</label>
                                    <select x-model="qbWhereOp" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                        <option>=</option><option>!=</option><option>&gt;</option><option>&lt;</option><option>LIKE</option><option>IS NULL</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Value</label>
                                    <input type="text" x-model="qbWhereVal" placeholder="value" class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-full focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>
                    </template>

                    <button
                        type="button"
                        @click="generateSql()"
                        :disabled="!qbTable"
                        class="rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold px-4 py-2 transition"
                    >Generate SQL →</button>
                </div>

            </div>

            {{-- Write modal (nested inside console) --}}
            @include('db-governor::partials.write-modal')

        </div>
    </div>

    </div>
@endsection

@push('scripts')
<script>
function sqlConsole() {
    return {
        consoleTab: 'builder',
        sql: '',
        loading: false,
        result: null,
        error: null,
        writeModal: false,
        pendingWrite: null,
        qbTable: '',
        qbType: 'SELECT',
        qbColumns: [],
        qbSelectedColumns: [],
        qbWhereCol: '',
        qbWhereOp: '=',
        qbWhereVal: '',
        qbOrderBy: '',
        qbOrderDir: 'ASC',
        qbLimit: 25,
        qbInsertRows: [{col: '', val: ''}],
        qbSetRows: [{col: '', val: ''}],
        sqlKeywords: ['SELECT','INSERT','UPDATE','DELETE','FROM','WHERE','JOIN','LEFT JOIN',
                      'INNER JOIN','RIGHT JOIN','ORDER BY','GROUP BY','HAVING','LIMIT',
                      'OFFSET','AND','OR','NOT','IN','LIKE','IS NULL','IS NOT NULL',
                      'CREATE','DROP','ALTER','TRUNCATE','WITH','DISTINCT','AS','ON',
                      'SET','INTO','VALUES','REPLACE','EXPLAIN'],
        tableNames: @json($tables),
        autocompleteList: [],
        autocompleteIndex: -1,
        get columnNames() {
            return this.qbColumns.map(c => c.name);
        },
        autocomplete(value) {
            const match = value.match(/[\w.]+$/);
            const token = match ? match[0] : '';
            if (!token || token.length < 1) { this.autocompleteList = []; return; }
            const dotIdx = token.indexOf('.');
            let candidates = [];
            if (dotIdx > 0) {
                const tbl = token.slice(0, dotIdx);
                const col = token.slice(dotIdx + 1).toUpperCase();
                if (this.qbTable === tbl || this.tableNames.includes(tbl)) {
                    candidates = this.columnNames.filter(c => c.toUpperCase().startsWith(col));
                }
            } else {
                const up = token.toUpperCase();
                const kwMatches = this.sqlKeywords.filter(k => k.startsWith(up));
                const tblMatches = this.tableNames.filter(t => t.toUpperCase().startsWith(up));
                candidates = [...new Set([...kwMatches, ...tblMatches])];
            }
            this.autocompleteList  = candidates.filter(c => c !== token.toUpperCase() && c !== token);
            this.autocompleteIndex = -1;
        },
        insertSuggestion(item) {
            const before = this.sql.replace(/[\w.]+$/, '');
            this.sql = before + item;
            this.autocompleteList  = [];
            this.autocompleteIndex = -1;
        },
        acKeydown(e) {
            if (!this.autocompleteList.length) { return; }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.autocompleteIndex = Math.min(this.autocompleteIndex + 1, this.autocompleteList.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.autocompleteIndex = Math.max(this.autocompleteIndex - 1, 0);
            } else if ((e.key === 'Tab' || e.key === 'Enter') && this.autocompleteIndex >= 0) {
                e.preventDefault();
                this.insertSuggestion(this.autocompleteList[this.autocompleteIndex]);
            } else if (e.key === 'Escape') {
                this.autocompleteList = [];
            }
        },
        async loadColumns(tbl) {
            this.qbColumns = [];
            this.qbSelectedColumns = [];
            this.qbInsertRows = [];
            this.qbSetRows = [{col: '', val: ''}];
            this.qbWhereCol = '';
            if (!tbl) { return; }
            try {
                const res = await fetch('{{ route('db-governor.schema.table', ['token' => $token, 'connection' => $currentConnection, 'table' => '__TABLE__']) }}'.replace('__TABLE__', tbl));
                const data = await res.json();
                this.qbColumns = data.columns || [];
                this.qbInsertRows = this.qbColumns.map(c => ({col: c.name, val: ''}));
            } catch (e) {}
        },
        generateSql() {
            const esc = v => v.replace(/'/g, "''");
            const whereClause = () => {
                if (!this.qbWhereCol) return '';
                if (this.qbWhereOp === 'IS NULL' || this.qbWhereOp === 'IS NOT NULL') {
                    return ' WHERE ' + this.qbWhereCol + ' ' + this.qbWhereOp;
                }
                return ' WHERE ' + this.qbWhereCol + ' ' + this.qbWhereOp + " '" + esc(this.qbWhereVal) + "'";
            };

            if (this.qbType === 'SELECT') {
                const cols = this.qbSelectedColumns.length ? this.qbSelectedColumns.join(', ') : '*';
                let q = 'SELECT ' + cols + ' FROM ' + this.qbTable;
                q += whereClause();
                if (this.qbOrderBy) { q += ' ORDER BY ' + this.qbOrderBy + ' ' + this.qbOrderDir; }
                if (this.qbLimit) { q += ' LIMIT ' + this.qbLimit; }
                this.sql = q;
            } else if (this.qbType === 'INSERT') {
                const valid = this.qbInsertRows.filter(r => r.col.trim());
                const cols = valid.map(r => r.col).join(', ');
                const vals = valid.map(r => "'" + esc(r.val) + "'").join(', ');
                this.sql = 'INSERT INTO ' + this.qbTable + ' (' + cols + ') VALUES (' + vals + ')';
            } else if (this.qbType === 'UPDATE') {
                const valid = this.qbSetRows.filter(r => r.col.trim());
                const setParts = valid.map(r => r.col + " = '" + esc(r.val) + "'").join(', ');
                this.sql = 'UPDATE ' + this.qbTable + ' SET ' + setParts + whereClause();
            } else if (this.qbType === 'DELETE') {
                this.sql = 'DELETE FROM ' + this.qbTable + whereClause();
            }

            this.consoleTab = 'raw';
        },
        async run() {
            this.loading = true;
            this.result  = null;
            this.error   = null;
            const knownVerbs = ['SELECT','INSERT','UPDATE','DELETE','CREATE','DROP',
                                'ALTER','TRUNCATE','WITH','REPLACE','EXPLAIN'];
            const firstWord = this.sql.trim().split(/\s+/)[0].toUpperCase();
            if (!knownVerbs.includes(firstWord)) {
                this.error   = 'Invalid SQL: statement must begin with a recognised SQL verb (SELECT, INSERT, …).';
                this.loading = false;
                return;
            }
            try {
                const res = await fetch('{{ route('db-governor.sql.execute', ['token' => $token, 'connection' => $currentConnection]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''
                    },
                    body: JSON.stringify({ sql: this.sql }),
                });
                const data = await res.json();
                if (data.blocked) {
                    this.error = 'Query blocked: ' + (data.message ?? 'policy violation');
                } else if (data.type === 'write') {
                    this.pendingWrite = data;
                    this.writeModal   = true;
                } else if (data.error) {
                    this.error = data.error;
                } else {
                    this.result = data;
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endpush

