<x-layout>
  <x-slot name="heading">Daily Report</x-slot>
  <x-slot name="title">Daily Report</x-slot>

  @php
    $allImageUrls = [];
    foreach($tasks as $task) {
        $sub = $submissionsByTask->get($task->id);
        if (!$sub) continue;
        foreach($sub->files->filter(fn($f) => $f->isImage()) as $f) {
            $allImageUrls[] = Storage::url($f->file_path);
        }
        if ($sub->files->count() === 0 && $sub->file_path) {
            $allImageUrls[] = Storage::url($sub->file_path);
        }
    }
  @endphp

  <div class="min-h-screen bg-gray-50 mt-16">

    {{-- ===== STICKY HEADER ===== --}}
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-screen-2xl mx-auto px-4 py-0">
        <div class="flex items-stretch gap-0 divide-x divide-gray-100">

          {{-- Prev day --}}
          <a href="{{ route('checklist.report', ['date' => $prevDate, 'role' => $roleFilter]) }}"
             class="flex items-center px-4 py-3.5 text-gray-400 hover:text-gray-700 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </a>

          {{-- Date + picker --}}
          <div class="flex-1 flex items-center justify-center gap-3 px-4 py-3">
            <div class="text-center">
              <p class="font-bold text-gray-800 text-sm leading-tight">{{ $dateObj->format('l, F j, Y') }}</p>
              @if($isToday)
                <span class="inline-block text-xs bg-green-100 text-green-700 font-semibold px-2 py-0.5 rounded-full leading-none mt-0.5">Today</span>
              @else
                <form method="GET" action="{{ route('checklist.report') }}" class="inline">
                  @if($roleFilter)<input type="hidden" name="role" value="{{ $roleFilter }}">@endif
                  <input type="date" name="date" value="{{ $dateObj->toDateString() }}"
                         onchange="this.form.submit()"
                         max="{{ now()->toDateString() }}"
                         class="text-xs text-blue-500 border-0 bg-transparent cursor-pointer focus:outline-none mt-0.5">
                </form>
              @endif
            </div>
          </div>

          {{-- Next day --}}
          <a href="{{ route('checklist.report', ['date' => $nextDate, 'role' => $roleFilter]) }}"
             class="flex items-center px-4 py-3.5 transition {{ $isToday ? 'text-gray-200 pointer-events-none' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </a>

          {{-- Role filter --}}
          <div class="flex items-center gap-2 px-4 py-3">
            <form method="GET" action="{{ route('checklist.report') }}" class="flex items-center gap-2">
              <input type="hidden" name="date" value="{{ $dateObj->toDateString() }}">
              <select name="role" onchange="this.form.submit()"
                      class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-300 cursor-pointer">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                  <option value="{{ $role->id }}" {{ $roleFilter == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
              </select>
            </form>
          </div>

          {{-- Progress + links --}}
          <div class="flex items-center gap-3 px-4 py-3">
            <div class="flex items-center gap-2">
              @php $pct = $totalTasks > 0 ? round($doneCount / $totalTasks * 100) : 0; @endphp
              <svg class="w-8 h-8 -rotate-90" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                <circle cx="18" cy="18" r="15.9" fill="none"
                        stroke="{{ $doneCount === $totalTasks && $totalTasks > 0 ? '#22c55e' : '#3b82f6' }}"
                        stroke-width="3"
                        stroke-dasharray="{{ $pct }}, 100"
                        stroke-linecap="round"/>
              </svg>
              <div class="leading-none">
                <p class="text-sm font-bold {{ $doneCount === $totalTasks && $totalTasks > 0 ? 'text-green-600' : 'text-gray-700' }}">{{ $doneCount }}/{{ $totalTasks }}</p>
                <p class="text-xs text-gray-400">tasks</p>
              </div>
            </div>
            <a href="{{ route('checklist.index') }}"
               class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition whitespace-nowrap">
              ← Checklist
            </a>
          </div>

        </div>
      </div>
    </div>

    {{-- ===== CONTENT ===== --}}
    <div class="max-w-screen-2xl mx-auto px-4 py-5 space-y-4">

      @if($totalTasks === 0)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-14 text-center">
          <p class="text-3xl mb-3">📋</p>
          <p class="text-gray-500 font-medium">No active tasks</p>
          <p class="text-sm text-gray-400 mt-1">Go to <a href="{{ route('checklist.manage') }}" class="text-blue-500 hover:underline">Manage Tasks</a> to add tasks.</p>
        </div>
      @else

        {{-- Summary strip --}}
        <div class="grid grid-cols-3 gap-3">
          <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 text-center">
            <p class="text-2xl font-bold {{ $doneCount > 0 ? 'text-green-600' : 'text-gray-300' }}">{{ $doneCount }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Completed</p>
          </div>
          <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 text-center">
            <p class="text-2xl font-bold {{ ($totalTasks - $doneCount) > 0 ? 'text-amber-500' : 'text-gray-300' }}">{{ $totalTasks - $doneCount }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Pending</p>
          </div>
          <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 text-center">
            <p class="text-2xl font-bold text-gray-700">{{ $totalTasks }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Total Tasks</p>
          </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="border-b border-gray-100 bg-gray-50/80 text-xs text-gray-400 uppercase tracking-wide font-semibold">
                  <th class="text-left px-4 py-3 w-8"></th>
                  <th class="text-left px-3 py-3 min-w-[160px]">Task</th>
                  <th class="text-left px-3 py-3 min-w-[130px]">Description</th>
                  <th class="text-left px-3 py-3 w-[180px]">Images</th>
                  <th class="text-left px-3 py-3 min-w-[200px]">Notes</th>
                  <th class="text-left px-3 py-3 min-w-[140px]">Submitted by</th>
                  <th class="text-left px-3 py-3 w-[110px]">AI Analysis</th>
                  <th class="text-left px-3 py-3 w-[80px]">Action</th>
                  <th class="text-left px-3 py-3 w-[120px]">Approval</th>
                </tr>
              </thead>

              @foreach($tasks as $task)
                @php
                  $sub           = $submissionsByTask->get($task->id);
                  $done          = $sub !== null && $sub->status === 'completed';
                  $reverted      = $sub !== null && $sub->status === 'pending';
                  $subFiles      = ($done || $reverted) ? $sub->files : collect();
                  $imageFiles    = $subFiles->filter(fn($f) => $f->isImage());
                  $otherFiles    = $subFiles->filter(fn($f) => !$f->isImage());
                  // Analysis
                  $analyzeUrl    = $done ? route('checklist.analyze', $sub) : '';
                  $logsUrl       = $done ? route('checklist.analysis-logs', $sub) : '';
                  $savedAnalysis = $done ? $sub->latestAnalysis : null;
                  $analysisCount = $done ? ($sub->analysis_logs_count ?? 0) : 0;
                  // Approval
                  $approvalUrl   = $done ? route('checklist.approval-check', $sub) : '';
                  $approvalLogsUrl = $done ? route('checklist.approval-logs', $sub) : '';
                  $savedApproval = $done ? $sub->latestApproval : null;
                  $approvalCount = $done ? ($sub->approval_logs_count ?? 0) : 0;
                @endphp

                <tbody
                  x-data="{
                    {{-- Analysis state --}}
                    analyzeUrl: '{{ $analyzeUrl }}',
                    logsUrl: '{{ $logsUrl }}',
                    analyzing: false,
                    analysis: {!! $savedAnalysis ? \Illuminate\Support\Js::from($savedAnalysis->analysis_result) : 'null' !!},
                    promptUsed: {!! $savedAnalysis ? \Illuminate\Support\Js::from($savedAnalysis->prompt_used) : 'null' !!},
                    analyzedBy: {!! $savedAnalysis ? \Illuminate\Support\Js::from($savedAnalysis->user?->name ?? 'Unknown') : 'null' !!},
                    analyzedAt: {!! $savedAnalysis ? \Illuminate\Support\Js::from($savedAnalysis->created_at->format('M j, g:i A')) : 'null' !!},
                    analysisCount: {{ $analysisCount }},
                    analysisError: null,
                    showAnalysis: {{ $savedAnalysis ? 'true' : 'false' }},
                    showPrompt: false,
                    showHistory: false,
                    historyLogs: [],
                    historyLoading: false,

                    {{-- Approval state --}}
                    approvalUrl: '{{ $approvalUrl }}',
                    approvalLogsUrl: '{{ $approvalLogsUrl }}',
                    approving: false,
                    verdict: {!! $savedApproval ? \Illuminate\Support\Js::from($savedApproval->verdict) : 'null' !!},
                    approvalText: {!! $savedApproval ? \Illuminate\Support\Js::from($savedApproval->analysis_result) : 'null' !!},
                    approvalPrompt: {!! $savedApproval ? \Illuminate\Support\Js::from($savedApproval->prompt_used) : 'null' !!},
                    approvedBy: {!! $savedApproval ? \Illuminate\Support\Js::from($savedApproval->user?->name ?? 'Unknown') : 'null' !!},
                    approvedAt: {!! $savedApproval ? \Illuminate\Support\Js::from($savedApproval->created_at->format('M j, g:i A')) : 'null' !!},
                    approvalCount: {{ $approvalCount }},
                    approvalError: null,
                    showApproval: {{ $savedApproval ? 'true' : 'false' }},
                    showApprovalPrompt: false,
                    showApprovalHistory: false,
                    approvalHistoryLogs: [],
                    approvalHistoryLoading: false,

                    async analyze() {
                      this.analyzing    = true;
                      this.showAnalysis = true;
                      this.analysis     = null;
                      this.analysisError = null;
                      this.showHistory  = false;
                      try {
                        const res = await fetch(this.analyzeUrl, {
                          method: 'POST',
                          headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                          }
                        });
                        if (!res.ok) {
                          this.analysisError = 'Server error (' + res.status + '). Please try again.';
                          this.analyzing = false; return;
                        }
                        const data = await res.json();
                        this.analysis      = data.analysis    ?? null;
                        this.promptUsed    = data.prompt_used ?? null;
                        this.analyzedBy    = data.analyzed_by ?? null;
                        this.analyzedAt    = data.analyzed_at ?? null;
                        this.analysisError = data.error       ?? null;
                        this.analysisCount += 1;
                        this.historyLogs   = [];
                      } catch(e) {
                        this.analysisError = 'Request failed: ' + e.message;
                      }
                      this.analyzing = false;
                    },
                    async toggleHistory() {
                      this.showHistory = !this.showHistory;
                      if (this.showHistory && this.historyLogs.length === 0) {
                        this.historyLoading = true;
                        try {
                          const res  = await fetch(this.logsUrl);
                          const data = await res.json();
                          this.historyLogs = data.logs ?? [];
                        } catch(e) {}
                        this.historyLoading = false;
                      }
                    },

                    async approvalCheck() {
                      this.approving     = true;
                      this.showApproval  = true;
                      this.approvalText  = null;
                      this.verdict       = null;
                      this.approvalError = null;
                      this.showApprovalHistory = false;
                      try {
                        const res = await fetch(this.approvalUrl, {
                          method: 'POST',
                          headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                          }
                        });
                        if (!res.ok) {
                          this.approvalError = 'Server error (' + res.status + '). Please try again.';
                          this.approving = false; return;
                        }
                        const data = await res.json();
                        this.verdict       = data.verdict      ?? null;
                        this.approvalText  = data.analysis     ?? null;
                        this.approvalPrompt= data.prompt_used  ?? null;
                        this.approvedBy    = data.checked_by   ?? null;
                        this.approvedAt    = data.checked_at   ?? null;
                        this.approvalError = data.error        ?? null;
                        this.approvalCount += 1;
                        this.approvalHistoryLogs = [];
                      } catch(e) {
                        this.approvalError = 'Request failed: ' + e.message;
                      }
                      this.approving = false;
                    },
                    async toggleApprovalHistory() {
                      this.showApprovalHistory = !this.showApprovalHistory;
                      if (this.showApprovalHistory && this.approvalHistoryLogs.length === 0) {
                        this.approvalHistoryLoading = true;
                        try {
                          const res  = await fetch(this.approvalLogsUrl);
                          const data = await res.json();
                          this.approvalHistoryLogs = data.logs ?? [];
                        } catch(e) {}
                        this.approvalHistoryLoading = false;
                      }
                    }
                  }"
                >

                  {{-- DATA ROW --}}
                  <tr class="border-b border-gray-50 hover:bg-gray-50/40 transition-colors">

                    {{-- Status --}}
                    <td class="px-4 py-3 align-middle">
                      <div class="w-2.5 h-2.5 rounded-full mx-auto {{ $done ? 'bg-green-400' : ($reverted ? 'bg-amber-400' : 'bg-gray-200') }}"></div>
                      @if($reverted)
                        <p class="text-[9px] text-amber-500 font-semibold text-center mt-0.5">REVERTED</p>
                      @endif
                    </td>

                    {{-- Task --}}
                    <td class="px-3 py-3 align-top">
                      <div class="flex items-center gap-1.5 flex-wrap">
                        <p class="font-semibold text-gray-800 leading-snug">{{ $task->title }}</p>
                        @if($task->deleted_at ?? false)
                          <span class="text-xs px-1.5 py-0.5 rounded-full bg-red-50 text-red-400 leading-none">deleted</span>
                        @elseif(!$task->is_active)
                          <span class="text-xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-400 leading-none">inactive</span>
                        @endif
                      </div>
                      @if($task->assignedUsers->count())
                        <p class="text-xs text-indigo-400 mt-0.5">→ {{ $task->assignedUsers->pluck('name')->implode(', ') }}</p>
                      @endif
                      <span class="text-xs px-1.5 py-0.5 rounded-full mt-1 inline-block
                        {{ in_array($task->type, ['photo','photo_note']) ? 'bg-blue-50 text-blue-500' : ($task->type === 'note' ? 'bg-amber-50 text-amber-500' : ($task->type === 'both' ? 'bg-purple-50 text-purple-500' : 'bg-gray-100 text-gray-400')) }}">
                        {{ in_array($task->type, ['photo','photo_note']) ? '📸' : ($task->type === 'note' ? '📝' : ($task->type === 'both' ? '📸📝' : '📎')) }}
                        {{ $task->type === 'photo_note' ? 'Photo + Note opt.' : ($task->type === 'both' ? 'Photo + Note' : ucfirst($task->type)) }}
                      </span>
                    </td>

                    {{-- Description --}}
                    <td class="px-3 py-3 text-gray-400 text-sm align-top">
                      {{ $task->description ?: '—' }}
                    </td>

                    {{-- Images --}}
                    <td class="px-3 py-3 align-top">
                      @if($imageFiles->count() > 0)
                        <div class="flex flex-wrap gap-1">
                          @foreach($imageFiles as $f)
                            <img src="{{ Storage::url($f->file_path) }}"
                                 @click="$dispatch('open-lightbox', '{{ Storage::url($f->file_path) }}')"
                                 class="w-14 h-14 object-cover rounded-lg border border-gray-100 hover:opacity-80 transition shadow-sm cursor-zoom-in"
                                 alt="{{ $f->file_original_name }}">
                          @endforeach
                        </div>
                        @foreach($otherFiles as $f)
                          <a href="{{ Storage::url($f->file_path) }}" target="_blank"
                             class="text-xs text-blue-500 hover:underline flex items-center gap-1 mt-1">
                            📎 <span class="truncate max-w-[90px]">{{ $f->file_original_name }}</span>
                          </a>
                        @endforeach
                      @elseif($done && $sub->file_path)
                        <img src="{{ Storage::url($sub->file_path) }}"
                             @click="$dispatch('open-lightbox', '{{ Storage::url($sub->file_path) }}')"
                             class="w-14 h-14 object-cover rounded-lg border border-gray-100 hover:opacity-80 transition cursor-zoom-in">
                      @else
                        <span class="text-gray-200">—</span>
                      @endif
                    </td>

                    {{-- Notes --}}
                    <td class="px-3 py-3 align-top">
                      @if($done && $sub->notes)
                        <p class="text-sm text-gray-600 leading-relaxed line-clamp-3">{{ $sub->notes }}</p>
                      @else
                        <span class="text-gray-200">—</span>
                      @endif
                    </td>

                    {{-- Submitted by --}}
                    <td class="px-3 py-3 align-top">
                      @if($done)
                        @php
                          $editLogs  = $sub->logs->where('action', 'updated');
                          $lastEdit  = $editLogs->first();
                          $editCount = $editLogs->count();
                        @endphp
                        <div class="flex items-center gap-2">
                          <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 flex-shrink-0">
                            {{ strtoupper(substr($sub->user->name ?? '?', 0, 1)) }}
                          </div>
                          <div>
                            <p class="text-xs font-semibold text-gray-700 leading-none">{{ $sub->user->name ?? 'Unknown' }}</p>
                            <p class="text-xs text-gray-400 leading-none mt-0.5">{{ $sub->created_at->format('h:i A') }}</p>
                          </div>
                        </div>
                        @if($lastEdit)
                          <div class="flex items-center gap-1.5 mt-1.5 pt-1.5 border-t border-gray-100">
                            <div class="w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center text-xs font-bold text-amber-600 flex-shrink-0">
                              {{ strtoupper(substr($lastEdit->user->name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                              <p class="text-xs text-amber-600 leading-none">edited by {{ $lastEdit->user->name ?? 'Unknown' }}</p>
                              <p class="text-xs text-gray-400 leading-none mt-0.5">
                                {{ \Carbon\Carbon::parse($lastEdit->created_at)->format('h:i A') }}
                                {{ $editCount > 1 ? '&middot; '.$editCount.' edits' : '' }}
                              </p>
                            </div>
                          </div>
                        @endif
                      @else
                        <span class="text-gray-200">—</span>
                      @endif
                    </td>

                    {{-- AI Analysis button --}}
                    <td class="px-3 py-3 align-middle">
                      @if($done)
                        <button @click="analyze()"
                                :disabled="analyzing"
                                :class="analyzing ? 'opacity-60 cursor-not-allowed' : 'hover:bg-purple-700'"
                                class="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg bg-purple-600 text-white transition font-medium whitespace-nowrap">
                          <span x-show="!analyzing" x-text="analysisCount > 0 ? '↻ Re-analyze' : '✦ Analyze'"></span>
                          <span x-show="analyzing" class="flex items-center gap-1">
                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Analyzing…
                          </span>
                        </button>
                        <button x-show="analysisCount > 0"
                                @click="toggleHistory()"
                                class="mt-1 text-xs text-purple-400 hover:text-purple-600 block whitespace-nowrap"
                                x-text="showHistory ? 'hide history' : analysisCount + (analysisCount === 1 ? ' analysis' : ' analyses')">
                        </button>
                      @else
                        <span class="text-gray-200 text-xs">—</span>
                      @endif
                    </td>

                    {{-- Revert to Pending (admin only) --}}
                    <td class="px-3 py-3 align-middle">
                      @if($done && Auth::user()->isAdmin())
                        <form method="POST" action="{{ route('checklist.revert-submission', $sub) }}"
                              onsubmit="return confirm('Revert this task to pending? Data will be kept but user needs to re-submit.')">
                          @csrf
                          <button type="submit"
                                  class="flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition font-medium whitespace-nowrap">
                            ↩ Revert
                          </button>
                        </form>
                      @else
                        <span class="text-gray-200 text-xs">—</span>
                      @endif
                    </td>

                    {{-- Approval button --}}
                    <td class="px-3 py-3 align-middle">
                      @if($done)
                        {{-- Verdict badge --}}
                        <template x-if="verdict === 'approved'">
                          <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-green-100 text-green-700 font-semibold mb-1">✓ Approved</span>
                        </template>
                        <template x-if="verdict === 'not_approved'">
                          <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-red-100 text-red-700 font-semibold mb-1">✗ Not Approved</span>
                        </template>

                        <button @click="approvalCheck()"
                                :disabled="approving"
                                :class="approving ? 'opacity-60 cursor-not-allowed' : 'hover:bg-emerald-700'"
                                class="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white transition font-medium whitespace-nowrap">
                          <span x-show="!approving" x-text="approvalCount > 0 ? '↻ Re-check' : '☑ Check'"></span>
                          <span x-show="approving" class="flex items-center gap-1">
                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Checking…
                          </span>
                        </button>
                        <button x-show="approvalCount > 0"
                                @click="toggleApprovalHistory()"
                                class="mt-1 text-xs text-emerald-400 hover:text-emerald-600 block whitespace-nowrap"
                                x-text="showApprovalHistory ? 'hide history' : approvalCount + (approvalCount === 1 ? ' check' : ' checks')">
                        </button>
                      @else
                        <span class="text-gray-200 text-xs">—</span>
                      @endif
                    </td>

                  </tr>

                  {{-- AI Analysis result row --}}
                  @if($done)
                    <tr x-show="showAnalysis || analyzing" x-transition class="border-b border-purple-100 bg-purple-50/30">
                      <td colspan="9" class="px-6 py-4">
                        {{-- Loading --}}
                        <template x-if="analyzing">
                          <div class="flex items-center gap-2 text-sm text-purple-500">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Analyzing submission with AI…
                          </div>
                        </template>
                        {{-- Result --}}
                        <template x-if="!analyzing && analysis">
                          <div class="space-y-2">
                            <div class="flex gap-3">
                              <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 flex-shrink-0 mt-0.5 text-sm">✦</div>
                              <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                  <p class="text-xs font-semibold text-purple-600">AI Analysis</p>
                                  <template x-if="analyzedBy">
                                    <span class="text-xs text-gray-400" x-text="'by ' + analyzedBy + (analyzedAt ? ' · ' + analyzedAt : '')"></span>
                                  </template>
                                </div>
                                <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line" x-text="analysis"></p>
                                {{-- Prompt used toggle --}}
                                <template x-if="promptUsed">
                                  <div class="mt-2">
                                    <button @click="showPrompt = !showPrompt"
                                            class="text-xs text-gray-400 hover:text-gray-600 underline underline-offset-2"
                                            x-text="showPrompt ? 'hide prompt' : 'show prompt used'">
                                    </button>
                                    <template x-if="showPrompt">
                                      <pre class="mt-1.5 text-xs text-gray-500 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 whitespace-pre-wrap leading-relaxed" x-text="promptUsed"></pre>
                                    </template>
                                  </div>
                                </template>
                              </div>
                            </div>
                          </div>
                        </template>
                        {{-- Error --}}
                        <template x-if="!analyzing && analysisError">
                          <div class="flex items-center gap-2 text-sm text-red-500">
                            <span>⚠</span>
                            <span x-text="analysisError"></span>
                          </div>
                        </template>
                      </td>
                    </tr>

                    {{-- Analysis History row --}}
                    <tr x-show="showHistory" x-transition class="border-b border-purple-100 bg-purple-50/10">
                      <td colspan="9" class="px-6 py-4">
                        <template x-if="historyLoading">
                          <div class="flex items-center gap-2 text-xs text-gray-400">
                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Loading history…
                          </div>
                        </template>
                        <template x-if="!historyLoading && historyLogs.length > 0">
                          <div class="space-y-3">
                            <p class="text-xs font-semibold text-purple-500 uppercase tracking-wide">Analysis History</p>
                            <template x-for="(log, i) in historyLogs" :key="log.id">
                              <div class="flex gap-3 pb-3" :class="i < historyLogs.length - 1 ? 'border-b border-purple-100' : ''">
                                <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center text-purple-500 flex-shrink-0 mt-0.5 text-xs">✦</div>
                                <div class="flex-1 min-w-0">
                                  <div class="flex items-center gap-2 flex-wrap mb-0.5">
                                    <span class="text-xs text-gray-500 font-medium" x-text="'#' + (historyLogs.length - i) + ' — ' + log.user"></span>
                                    <span class="text-xs text-gray-400" x-text="log.created_at"></span>
                                  </div>
                                  <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line" x-text="log.analysis"></p>
                                </div>
                              </div>
                            </template>
                          </div>
                        </template>
                      </td>
                    </tr>

                    {{-- Approval result row --}}
                    <tr x-show="showApproval || approving" x-transition class="border-b border-emerald-100 bg-emerald-50/30">
                      <td colspan="9" class="px-6 py-4">
                        {{-- Loading --}}
                        <template x-if="approving">
                          <div class="flex items-center gap-2 text-sm text-emerald-500">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Running approval check…
                          </div>
                        </template>
                        {{-- Result --}}
                        <template x-if="!approving && approvalText">
                          <div class="space-y-2">
                            <div class="flex gap-3">
                              <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 text-sm"
                                   :class="verdict === 'approved' ? 'bg-green-100 text-green-600' : (verdict === 'not_approved' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600')"
                                   x-text="verdict === 'approved' ? '✓' : (verdict === 'not_approved' ? '✗' : '?')">
                              </div>
                              <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                  <p class="text-xs font-semibold"
                                     :class="verdict === 'approved' ? 'text-green-600' : (verdict === 'not_approved' ? 'text-red-600' : 'text-gray-600')"
                                     x-text="verdict === 'approved' ? 'APPROVED' : (verdict === 'not_approved' ? 'NOT APPROVED' : 'UNKNOWN')">
                                  </p>
                                  <template x-if="approvedBy">
                                    <span class="text-xs text-gray-400" x-text="'by ' + approvedBy + (approvedAt ? ' · ' + approvedAt : '')"></span>
                                  </template>
                                </div>
                                <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line" x-text="approvalText"></p>
                                {{-- Prompt used toggle --}}
                                <template x-if="approvalPrompt">
                                  <div class="mt-2">
                                    <button @click="showApprovalPrompt = !showApprovalPrompt"
                                            class="text-xs text-gray-400 hover:text-gray-600 underline underline-offset-2"
                                            x-text="showApprovalPrompt ? 'hide prompt' : 'show prompt used'">
                                    </button>
                                    <template x-if="showApprovalPrompt">
                                      <pre class="mt-1.5 text-xs text-gray-500 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 whitespace-pre-wrap leading-relaxed" x-text="approvalPrompt"></pre>
                                    </template>
                                  </div>
                                </template>
                              </div>
                            </div>
                          </div>
                        </template>
                        {{-- Error --}}
                        <template x-if="!approving && approvalError">
                          <div class="flex items-center gap-2 text-sm text-red-500">
                            <span>⚠</span>
                            <span x-text="approvalError"></span>
                          </div>
                        </template>
                      </td>
                    </tr>

                    {{-- Approval History row --}}
                    <tr x-show="showApprovalHistory" x-transition class="border-b border-emerald-100 bg-emerald-50/10">
                      <td colspan="9" class="px-6 py-4">
                        <template x-if="approvalHistoryLoading">
                          <div class="flex items-center gap-2 text-xs text-gray-400">
                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Loading approval history…
                          </div>
                        </template>
                        <template x-if="!approvalHistoryLoading && approvalHistoryLogs.length > 0">
                          <div class="space-y-3">
                            <p class="text-xs font-semibold text-emerald-500 uppercase tracking-wide">Approval History</p>
                            <template x-for="(log, i) in approvalHistoryLogs" :key="log.id">
                              <div class="flex gap-3 pb-3" :class="i < approvalHistoryLogs.length - 1 ? 'border-b border-emerald-100' : ''">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 text-xs"
                                     :class="log.verdict === 'approved' ? 'bg-green-100 text-green-600' : (log.verdict === 'not_approved' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600')"
                                     x-text="log.verdict === 'approved' ? '✓' : (log.verdict === 'not_approved' ? '✗' : '?')">
                                </div>
                                <div class="flex-1 min-w-0">
                                  <div class="flex items-center gap-2 flex-wrap mb-0.5">
                                    <span class="text-xs font-semibold"
                                          :class="log.verdict === 'approved' ? 'text-green-600' : (log.verdict === 'not_approved' ? 'text-red-600' : 'text-gray-600')"
                                          x-text="log.verdict === 'approved' ? 'APPROVED' : (log.verdict === 'not_approved' ? 'NOT APPROVED' : 'UNKNOWN')">
                                    </span>
                                    <span class="text-xs text-gray-500 font-medium" x-text="'#' + (approvalHistoryLogs.length - i) + ' — ' + log.user"></span>
                                    <span class="text-xs text-gray-400" x-text="log.created_at"></span>
                                  </div>
                                  <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line" x-text="log.analysis"></p>
                                </div>
                              </div>
                            </template>
                          </div>
                        </template>
                      </td>
                    </tr>
                  @endif

                  {{-- Admin Comment/Chat Row --}}
                  @if(Auth::user()->isAdmin())
                    <tr class="border-b border-blue-100 bg-blue-50/20">
                      <td colspan="9" class="px-6 py-3">
                        {{-- Existing comments --}}
                        @php $taskComments = $commentsByTask->get($task->id, collect()); @endphp
                        @if($taskComments->count() > 0)
                          <div class="space-y-2 mb-3">
                            <p class="text-xs font-semibold text-blue-500 uppercase tracking-wide">💬 Admin Comments</p>
                            @foreach($taskComments as $comment)
                              <div class="flex items-start gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-white text-[10px] font-bold" style="background-color:#1877F2">{{ strtoupper(substr($comment->user->name ?? 'A', 0, 1)) }}</div>
                                <div>
                                  <div class="bg-white border border-blue-200 rounded-2xl rounded-tl-md px-3 py-2">
                                    <p class="text-sm text-gray-800">{{ $comment->message }}</p>
                                  </div>
                                  <p class="text-[10px] text-gray-400 mt-0.5 ml-1">{{ $comment->user->name ?? 'Admin' }} · {{ $comment->created_at->format('g:i A') }}</p>
                                </div>
                              </div>
                            @endforeach
                          </div>
                        @endif
                        {{-- Comment input --}}
                        <form method="POST" action="{{ route('checklist.send-comment', $task) }}" class="flex items-center gap-2">
                          @csrf
                          <input type="hidden" name="date" value="{{ $dateObj->toDateString() }}">
                          <div class="flex-1">
                            <input type="text" name="message" placeholder="Type a comment for this task..." required
                                   class="w-full text-sm border border-gray-200 rounded-full px-4 py-2 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 bg-white">
                          </div>
                          <button type="submit"
                                  class="w-8 h-8 rounded-full flex items-center justify-center text-white flex-shrink-0 hover:opacity-90 transition" style="background-color:#1877F2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                          </button>
                        </form>
                      </td>
                    </tr>
                  @endif

                </tbody>
              @endforeach

            </table>
          </div>

          @if($doneCount === $totalTasks && $totalTasks > 0)
            <div class="px-6 py-3 border-t border-gray-100 bg-green-50/50 text-center">
              <p class="text-sm text-green-600 font-semibold">🎉 All tasks completed for {{ $dateObj->format('F j') }}!</p>
            </div>
          @elseif($doneCount === 0)
            <div class="px-6 py-4 border-t border-gray-100 text-center">
              <p class="text-sm text-gray-400 italic">No submissions yet for {{ $dateObj->format('F j') }}.</p>
            </div>
          @endif
        </div>

      @endif
    </div>
  </div>

  {{-- ===== LIGHTBOX ===== --}}
  <div x-data="{
           lightbox: false,
           images: {{ json_encode($allImageUrls) }},
           currentIndex: 0,
           get lightSrc() { return this.images[this.currentIndex] ?? ''; },
           open(src) {
               const idx = this.images.indexOf(src);
               this.currentIndex = idx >= 0 ? idx : 0;
               this.lightbox = true;
           },
           prev() { if (this.currentIndex > 0) this.currentIndex--; },
           next() { if (this.currentIndex < this.images.length - 1) this.currentIndex++; }
       }"
       @open-lightbox.window="open($event.detail)"
       @keydown.escape.window="lightbox = false"
       @keydown.arrow-left.window="if (lightbox) prev()"
       @keydown.arrow-right.window="if (lightbox) next()"
       x-show="lightbox"
       x-transition.opacity
       @click="lightbox = false"
       class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
       style="display:none">

    <button @click="lightbox = false"
            class="absolute top-4 right-4 w-9 h-9 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-lg transition z-10">✕</button>

    <template x-if="images.length > 1">
      <div class="absolute top-4 left-1/2 -translate-x-1/2 bg-black/50 text-white text-xs px-3 py-1 rounded-full z-10"
           x-text="(currentIndex + 1) + ' / ' + images.length"></div>
    </template>

    <template x-if="images.length > 1">
      <button @click.stop="prev()"
              :class="currentIndex === 0 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
              class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-xl transition z-10">‹</button>
    </template>

    <img :src="lightSrc"
         class="max-w-full max-h-full rounded-xl shadow-2xl object-contain"
         @click.stop>

    <template x-if="images.length > 1">
      <button @click.stop="next()"
              :class="currentIndex === images.length - 1 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
              class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-xl transition z-10">›</button>
    </template>
  </div>

</x-layout>
