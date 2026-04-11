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
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-bold text-gray-800">📋 Query Log</h1>
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
            name="search"
            value="{{ request('search') }}"
            placeholder="Search name or SQL…"
            class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 w-56 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        >

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
                        <th class="px-4 py-2.5">Type</th>
                        <th class="px-4 py-2.5">Risk</th>
                        <th class="px-4 py-2.5">Status</th>
                        <th class="px-4 py-2.5">Submitted by</th>
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
                                <span class="inline-flex rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 text-xs font-semibold uppercase">
                                    {{ $query->query_type }}
                                </span>
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
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $query->submitted_by }}</td>
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
            @keydown.escape.window="modal = null"
            style="display: none;"
        >
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl p-6 max-h-[90vh] overflow-y-auto" @click.stop>
                <template x-if="modal">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-800" x-text="modal.name ?? 'Query Detail'"></h3>
                            <button type="button" @click="modal = null" class="text-gray-400 hover:text-gray-600">✕</button>
                        </div>

                        {{-- Meta --}}
                        <div class="flex flex-wrap gap-2 mb-4">
                            <span class="inline-flex rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 text-xs font-semibold uppercase" x-text="modal.query_type"></span>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase ring-1 ring-inset" x-text="modal.status.replace('_', ' ').toUpperCase()"></span>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase" x-text="'Risk: ' + modal.risk_level"></span>
                        </div>

                        {{-- SQL --}}
                        <pre class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs font-mono text-gray-700 overflow-x-auto whitespace-pre-wrap mb-4" x-text="modal.sql_raw"></pre>

                        {{-- Description / risk note --}}
                        <template x-if="modal.description">
                            <p class="text-sm text-gray-600 mb-2"><span class="font-medium">Reason:</span> <span x-text="modal.description"></span></p>
                        </template>
                        <template x-if="modal.risk_note">
                            <p class="text-sm text-gray-600 mb-2"><span class="font-medium">Risk note:</span> <span x-text="modal.risk_note"></span></p>
                        </template>
                        <template x-if="modal.estimated_rows !== null">
                            <p class="text-xs text-gray-400 mb-2">~<span x-text="modal.estimated_rows"></span> rows estimated</p>
                        </template>

                        {{-- Flags --}}
                        <template x-if="modal.risk_flags && modal.risk_flags.length">
                            <ul class="mb-4 space-y-1">
                                <template x-for="flag in modal.risk_flags" :key="flag">
                                    <li class="text-xs text-orange-600 flex gap-1"><span>⚠</span><span x-text="flag"></span></li>
                                </template>
                            </ul>
                        </template>

                        @if ($guardRole === 'admin')
                            {{-- Approve --}}
                            <template x-if="modal.status === 'pending'">
                                <div class="space-y-3 mt-4 border-t border-gray-100 pt-4">
                                    <form
                                        :action="`{{ $tokenBaseUrl ?? '' }}/` + modal.connection + '/queries/' + modal.id + '/approve'"
                                        method="POST"
                                    >
                                        @csrf
                                        <div class="mb-2">
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Approval note (optional)</label>
                                            <input type="text" name="review_note" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Why is this safe?">
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
                                            <input type="text" name="rejection_reason" required class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Why is this being rejected?">
                                        </div>
                                        <button type="submit" class="rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-1.5 transition">Reject</button>
                                    </form>
                                </div>
                            </template>

                            {{-- Execute --}}
                            <template x-if="modal.status === 'approved'">
                                <div class="mt-4 border-t border-gray-100 pt-4">
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
                                <div class="mt-4 border-t border-gray-100 pt-4">
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
                </template>
            </div>
        </div>
    </div>
@endsection


