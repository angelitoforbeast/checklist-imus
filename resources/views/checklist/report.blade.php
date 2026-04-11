<x-layout title="Daily Report">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Daily Report</h1>
            <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }}</p>
        </div>
        <div class="flex items-center space-x-3">
            {{-- Progress Ring --}}
            <div class="relative w-14 h-14">
                <svg class="w-14 h-14 -rotate-90" viewBox="0 0 56 56">
                    <circle cx="28" cy="28" r="24" fill="none" stroke="#e5e7eb" stroke-width="4"/>
                    <circle cx="28" cy="28" r="24" fill="none" stroke="{{ $progressPercent >= 100 ? '#22c55e' : '#3b82f6' }}" stroke-width="4"
                        stroke-dasharray="{{ 2 * 3.14159 * 24 }}" stroke-dashoffset="{{ 2 * 3.14159 * 24 * (1 - $progressPercent / 100) }}" stroke-linecap="round"/>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-xs font-bold {{ $progressPercent >= 100 ? 'text-green-600' : 'text-blue-600' }}">{{ $progressPercent }}%</span>
            </div>
            <span class="text-sm text-gray-500">{{ $completedTasks }}/{{ $totalTasks }} tasks</span>
        </div>
    </div>

    <div class="flex items-center space-x-2 mb-6 text-sm">
        <a href="/checklist/report?date={{ \Carbon\Carbon::parse($date)->subDay()->toDateString() }}" class="px-3 py-1.5 bg-white border rounded-lg hover:bg-gray-50">&larr; Prev</a>
        <input type="date" value="{{ $date }}" onchange="window.location='/checklist/report?date='+this.value"
            class="px-3 py-1.5 border rounded-lg text-sm">
        <a href="/checklist/report?date={{ \Carbon\Carbon::parse($date)->addDay()->toDateString() }}" class="px-3 py-1.5 bg-white border rounded-lg hover:bg-gray-50">Next &rarr;</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Task</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Notes</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Files</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Submitted By</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">AI Analysis</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($tasks as $task)
                @php $submission = $submissions->get($task->id); @endphp
                <tr class="{{ $task->trashed() ? 'opacity-50' : '' }}">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-800">{{ $task->title }}</div>
                        @if($task->description)
                            <div class="text-xs text-gray-400">{{ $task->description }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($submission)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Done</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Missing</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $submission->notes ?? '-' }}</td>
                    <td class="px-4 py-3">
                        @if($submission && $submission->files->count())
                            <div class="flex flex-wrap gap-1">
                                @foreach($submission->files as $file)
                                    <img src="{{ asset('storage/' . $file->file_path) }}" alt=""
                                        class="w-10 h-10 object-cover rounded border cursor-pointer"
                                        onclick="window.open(this.src, '_blank')">
                                @endforeach
                            </div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($submission)
                            <div class="text-xs">
                                @foreach($submission->logs as $log)
                                    <div>{{ ucfirst($log->action) }} by <span class="font-medium">{{ $log->user->name }}</span> {{ $log->created_at->format('g:ia') }}</div>
                                @endforeach
                            </div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($submission)
                        <div x-data="analysisWidget({{ $submission->id }}, {{ json_encode($submission->analysisLogs->first()) }})" class="space-y-2">
                            {{-- Latest result --}}
                            <div x-show="latestResult" class="text-xs text-gray-600 max-w-xs">
                                <div x-text="latestResult ? latestResult.substring(0, 150) + (latestResult.length > 150 ? '...' : '') : ''" class="leading-relaxed"></div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <button @click="analyze()" :disabled="loading" class="text-xs px-2 py-1 bg-purple-50 text-purple-600 rounded hover:bg-purple-100 disabled:opacity-50">
                                    <span x-text="loading ? 'Analyzing...' : (latestResult ? 'Re-analyze' : 'AI Analyze')"></span>
                                </button>
                                <button x-show="logCount > 0" @click="showLogs = !showLogs" class="text-xs text-gray-400 hover:text-gray-600">
                                    <span x-text="logCount + ' analyses'"></span>
                                </button>
                            </div>

                            {{-- Analysis history --}}
                            <div x-show="showLogs" x-transition class="mt-2 space-y-2 max-h-60 overflow-y-auto">
                                <template x-for="log in logs" :key="log.id">
                                    <div class="bg-gray-50 rounded p-2 text-xs">
                                        <div class="flex justify-between text-gray-400 mb-1">
                                            <span x-text="log.user?.name ?? 'Unknown'"></span>
                                            <span x-text="new Date(log.created_at).toLocaleString()"></span>
                                        </div>
                                        <div x-text="log.analysis_result" class="text-gray-600 leading-relaxed"></div>
                                        <button @click="log.showPrompt = !log.showPrompt" class="text-purple-500 mt-1">show prompt used</button>
                                        <div x-show="log.showPrompt" class="mt-1 p-1 bg-purple-50 rounded text-purple-700" x-text="log.prompt_used"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <script>
    function analysisWidget(submissionId, latestLog) {
        return {
            submissionId: submissionId,
            latestResult: latestLog ? latestLog.analysis_result : null,
            loading: false,
            showLogs: false,
            logs: [],
            logCount: latestLog ? 1 : 0,

            async analyze() {
                this.loading = true;
                try {
                    const res = await fetch(`/checklist/submission/${this.submissionId}/analyze`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.latestResult = data.result;
                        this.logCount++;
                        this.logs = [];
                        this.showLogs = false;
                    }
                } catch (e) {
                    alert('Analysis failed: ' + e.message);
                }
                this.loading = false;
            },

            async fetchLogs() {
                if (this.logs.length > 0) return;
                const res = await fetch(`/checklist/submission/${this.submissionId}/analysis-logs`);
                this.logs = (await res.json()).map(l => ({...l, showPrompt: false}));
                this.logCount = this.logs.length;
            },

            init() {
                this.$watch('showLogs', (val) => { if (val) this.fetchLogs(); });
            }
        }
    }
    </script>
</x-layout>
