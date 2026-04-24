{{-- Write-query modal: populated by Alpine.js when SQL Console returns a write query --}}
<div
    x-show="writeModal"
    x-transition
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
    @keydown.escape.window="writeModal = false"
    style="display: none;"
>
    <div class="w-full max-w-lg mx-4 rounded-2xl bg-white shadow-xl p-6" @click.stop>

        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-800">Submit Write Query for Approval</h3>
            <button type="button" @click="writeModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        {{-- Risk summary --}}
        <template x-if="pendingWrite">
            <div class="mb-4">
                <div class="mb-2 flex items-center gap-2">
                    <span class="text-xs font-medium text-gray-500">Risk level:</span>
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="{
                            'bg-yellow-50 text-yellow-700': pendingWrite.risk_level === 'medium',
                            'bg-orange-50 text-orange-700': pendingWrite.risk_level === 'high',
                            'bg-red-50 text-red-700':    pendingWrite.risk_level === 'critical',
                            'bg-green-50 text-green-700': pendingWrite.risk_level === 'low',
                        }"
                        x-text="pendingWrite.risk_level"
                    ></span>
                    <template x-if="pendingWrite.estimated_rows !== null">
                        <span class="text-xs text-gray-400">
                            ~<span x-text="pendingWrite.estimated_rows"></span> rows affected
                        </span>
                    </template>
                </div>

                <pre class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs font-mono text-gray-700 overflow-x-auto whitespace-pre-wrap" x-text="pendingWrite.sql"></pre>

                <template x-if="pendingWrite.flags && pendingWrite.flags.length">
                    <ul class="mt-2 space-y-1">
                        <template x-for="flag in pendingWrite.flags" :key="flag">
                            <li class="text-xs text-orange-600 flex items-start gap-1"><span>⚠</span><span x-text="flag"></span></li>
                        </template>
                    </ul>
                </template>
            </div>
        </template>

        <form method="POST" :action="`{{ $baseUrl }}/` + (pendingWrite ? pendingWrite.connection : '') + '/queries'">
            @csrf
            <input type="hidden" name="sql" x-bind:value="pendingWrite ? pendingWrite.sql : ''">

            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Brief description of this change">
            </div>

            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason / Description</label>
                <textarea name="description" rows="3"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    placeholder="Why is this change needed?"></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Risk Note</label>
                <input type="text" name="risk_note"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Any additional context for the reviewer">
            </div>

            <div class="flex items-center justify-end gap-3">
                <button type="button" @click="writeModal = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                <button type="submit" class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 transition">
                    Submit for Approval
                </button>
            </div>
        </form>
    </div>
</div>

