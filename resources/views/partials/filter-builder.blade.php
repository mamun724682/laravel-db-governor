<div
    x-data="{
        groups: {{ Js::from($filterGroups) }},
        columns: {{ Js::from($columns) }},
        addAnd(groupIdx) {
            this.groups[groupIdx].filters.push({ col: '', op: '=', val: '' });
        },
        addOr() {
            this.groups.push({ filters: [{ col: '', op: '=', val: '' }] });
        },
        removeFilter(groupIdx, filterIdx) {
            this.groups[groupIdx].filters.splice(filterIdx, 1);
            if (this.groups[groupIdx].filters.length === 0) {
                this.groups.splice(groupIdx, 1);
            }
        },
        colTypeByName(name) {
            const col = this.columns.find(c => c.name === name);
            return col ? (col.type || '') : '';
        },
        inputTypeFor(type) {
            const t = (type || '').toLowerCase();
            if (/timestamp|datetime/.test(t)) return 'datetime-local';
            if (/\bdate\b/.test(t)) return 'date';
            if (/\btime\b/.test(t)) return 'time';
            return 'text';
        },
        isJsonCol(type) {
            return /json/i.test(type || '');
        },
    }"
    class="rounded-xl bg-white border border-gray-100 shadow-sm p-4 mb-6"
>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            Filter
        </h3>
        <button
            type="button"
            @click="addOr()"
            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded px-2 py-1 transition"
        >
            <span class="text-base leading-none">+</span> OR group
        </button>
    </div>

    <form method="GET" id="filter-form">
        {{-- Preserve sort/dir across filter submissions --}}
        @foreach (request()->only(['sort', 'dir']) as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach

        <div class="space-y-3">
            <template x-for="(group, gi) in groups" :key="gi">
                <div>
                    {{-- OR separator between groups --}}
                    <template x-if="gi > 0">
                        <div class="flex items-center gap-2 my-3">
                            <hr class="flex-1 border-dashed border-gray-200">
                            <span class="text-[10px] font-bold tracking-widest text-gray-400 uppercase bg-white px-1">OR</span>
                            <hr class="flex-1 border-dashed border-gray-200">
                        </div>
                    </template>

                    {{-- Group card --}}
                    <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-3 space-y-2">
                        <template x-for="(f, fi) in group.filters" :key="fi">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">

                                {{-- AND badge --}}
                                <div class="flex-shrink-0 w-10">
                                    <template x-if="fi > 0">
                                        <span class="inline-flex items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-[10px] font-bold uppercase px-2 py-0.5 tracking-wide">AND</span>
                                    </template>
                                </div>

                                {{-- Column --}}
                                <select
                                    x-model="f.col"
                                    :name="`f[${gi}][${fi}][col]`"
                                    class="w-full sm:flex-1 rounded-lg border border-gray-200 bg-white text-xs px-2.5 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent text-gray-700"
                                >
                                    <option value="">— column —</option>
                                    @foreach ($columns as $col)
                                        <option value="{{ $col['name'] }}">{{ $col['name'] }}</option>
                                    @endforeach
                                </select>

                                {{-- Operator --}}
                                <select
                                    x-model="f.op"
                                    :name="`f[${gi}][${fi}][op]`"
                                    class="w-full sm:w-32 rounded-lg border border-gray-200 bg-white text-xs px-2.5 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent text-gray-700"
                                >
                                    @foreach (['=' => '=', '!=' => '≠', 'LIKE' => 'LIKE', 'NOT LIKE' => 'NOT LIKE', '>' => '>', '<' => '<', '>=' => '≥', '<=' => '≤', 'IS NULL' => 'IS NULL', 'IS NOT NULL' => 'IS NOT NULL', 'IN' => 'IN'] as $op => $label)
                                        <option value="{{ $op }}">{{ $label }}</option>
                                    @endforeach
                                </select>

                                {{-- Value --}}
                                <div class="w-full sm:flex-1">
                                    <template x-if="!['IS NULL','IS NOT NULL'].includes(f.op) && isJsonCol(colTypeByName(f.col))">
                                        <textarea
                                            x-model="f.val"
                                            :name="`f[${gi}][${fi}][val]`"
                                            placeholder="JSON value"
                                            rows="2"
                                            class="w-full rounded-lg border border-gray-200 bg-white text-xs px-2.5 py-2 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent resize-y"
                                        ></textarea>
                                    </template>
                                    <template x-if="!['IS NULL','IS NOT NULL'].includes(f.op) && !isJsonCol(colTypeByName(f.col))">
                                        <input
                                            :type="inputTypeFor(colTypeByName(f.col))"
                                            x-model="f.val"
                                            :name="`f[${gi}][${fi}][val]`"
                                            placeholder="value"
                                            class="w-full rounded-lg border border-gray-200 bg-white text-xs px-2.5 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent"
                                        >
                                    </template>
                                    <template x-if="['IS NULL','IS NOT NULL'].includes(f.op)">
                                        <input type="hidden" :name="`f[${gi}][${fi}][val]`" value="">
                                    </template>
                                </div>

                                {{-- Remove --}}
                                <button
                                    type="button"
                                    @click="removeFilter(gi, fi)"
                                    class="self-end sm:self-auto flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition text-base"
                                    title="Remove"
                                >&times;</button>
                            </div>
                        </template>

                        {{-- + AND --}}
                        <button
                            type="button"
                            @click="addAnd(gi)"
                            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-500 hover:text-indigo-700 hover:bg-indigo-50 rounded px-2 py-1 transition mt-1"
                        >
                            <span class="text-base leading-none">+</span> AND
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 mt-4 pt-3 border-t border-gray-100">
            <button
                type="submit"
                form="filter-form"
                class="rounded-lg bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-semibold px-5 py-2 transition shadow-sm"
            >Apply</button>
            <a
                href="{{ url()->current() }}"
                class="text-xs text-gray-400 hover:text-gray-600 font-medium transition"
            >Clear</a>
        </div>
    </form>
</div>

