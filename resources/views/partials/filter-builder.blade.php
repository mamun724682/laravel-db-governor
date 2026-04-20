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
    class="rounded-xl bg-white border border-gray-100 shadow p-4 mb-6"
>
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700">Filter</h3>
        <button
            type="button"
            @click="addOr()"
            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
        >+ OR group</button>
    </div>

    <form method="GET" id="filter-form">
        {{-- Preserve sort/dir across filter submissions --}}
        @foreach (request()->only(['sort', 'dir']) as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach

        <template x-for="(group, gi) in groups" :key="gi">
            <div>
                <template x-if="gi > 0">
                    <div class="flex items-center gap-2 my-2">
                        <hr class="flex-1 border-gray-200">
                        <span class="text-xs font-bold text-gray-400 uppercase">OR</span>
                        <hr class="flex-1 border-gray-200">
                    </div>
                </template>

                <div class="space-y-2">
                    <template x-for="(f, fi) in group.filters" :key="fi">
                        <div class="flex items-start sm:items-center gap-2 flex-wrap sm:flex-nowrap">
                            <template x-if="fi > 0">
                                <span class="text-xs font-bold text-indigo-500 uppercase w-8 text-center">AND</span>
                            </template>
                            <template x-if="fi === 0">
                                <span class="w-8"></span>
                            </template>

                            {{-- Column selector --}}
                            <select
                                x-model="f.col"
                                :name="`f[${gi}][${fi}][col]`"
                                class="flex-1 min-w-[120px] rounded border border-gray-300 text-xs px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
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
                                class="rounded border border-gray-300 text-xs px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                @foreach (['=' => '=', '!=' => '≠', 'LIKE' => 'LIKE', 'NOT LIKE' => 'NOT LIKE', '>' => '>', '<' => '<', '>=' => '≥', '<=' => '≤', 'IS NULL' => 'IS NULL', 'IS NOT NULL' => 'IS NOT NULL', 'IN' => 'IN'] as $op => $label)
                                    <option value="{{ $op }}">{{ $label }}</option>
                                @endforeach
                            </select>

                                            {{-- Value --}}
                                            <template x-if="!['IS NULL','IS NOT NULL'].includes(f.op) && isJsonCol(colTypeByName(f.col))">
                                                <textarea
                                                    x-model="f.val"
                                                    :name="`f[${gi}][${fi}][val]`"
                                                    placeholder="JSON value"
                                                    rows="2"
                                                    class="rounded border border-gray-300 text-xs px-2 py-1.5 w-48 font-mono focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-y"
                                                ></textarea>
                                            </template>
                                            <template x-if="!['IS NULL','IS NOT NULL'].includes(f.op) && !isJsonCol(colTypeByName(f.col))">
                                                <input
                                                    :type="inputTypeFor(colTypeByName(f.col))"
                                                    x-model="f.val"
                                                    :name="`f[${gi}][${fi}][val]`"
                                                    placeholder="value"
                                                    class="rounded border border-gray-300 text-xs px-2 py-1.5 w-36 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                                >
                                            </template>
                                            {{-- Hidden field to keep name binding when IS NULL/IS NOT NULL --}}
                                            <template x-if="['IS NULL','IS NOT NULL'].includes(f.op)">
                                                <input type="hidden" :name="`f[${gi}][${fi}][val]`" value="">
                                            </template>

                            {{-- Remove --}}
                            <button
                                type="button"
                                @click="removeFilter(gi, fi)"
                                class="text-gray-400 hover:text-red-500 text-lg leading-none"
                            >&times;</button>
                        </div>
                    </template>
                </div>

                <button
                    type="button"
                    @click="addAnd(gi)"
                    class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                >+ AND</button>
            </div>
        </template>

        <div class="flex items-center gap-2 mt-4">
            <button
                type="submit"
                form="filter-form"
                class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-1.5 transition"
            >Apply</button>
            <a
                href="{{ url()->current() }}"
                class="text-xs text-gray-400 hover:text-gray-600"
            >Clear</a>
        </div>
    </form>
</div>

