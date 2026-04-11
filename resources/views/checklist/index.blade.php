<x-layout title="Checklist">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Daily Checklist</h1>
        <div class="flex items-center space-x-2 text-sm">
            <a href="/checklist?date={{ \Carbon\Carbon::parse($date)->subDay()->toDateString() }}" class="px-3 py-1.5 bg-white border rounded-lg hover:bg-gray-50">&larr; Prev</a>
            <input type="date" value="{{ $date }}" onchange="window.location='/checklist?date='+this.value"
                class="px-3 py-1.5 border rounded-lg text-sm">
            <a href="/checklist?date={{ \Carbon\Carbon::parse($date)->addDay()->toDateString() }}" class="px-3 py-1.5 bg-white border rounded-lg hover:bg-gray-50">Next &rarr;</a>
        </div>
    </div>

    <div class="space-y-4">
        @forelse($tasks as $task)
        @php $submission = $submissions->get($task->id); @endphp
        <div class="bg-white rounded-xl shadow-sm border p-5" x-data="{ open: {{ $submission ? 'false' : 'true' }}, showFiles: false }">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-2">
                        @if($submission)
                            <span class="w-6 h-6 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs font-bold">&#10003;</span>
                        @else
                            <span class="w-6 h-6 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-xs">&#9675;</span>
                        @endif
                        <h3 class="font-semibold text-gray-800">{{ $task->title }}</h3>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $task->type === 'photo' ? 'bg-purple-100 text-purple-600' : ($task->type === 'note' ? 'bg-yellow-100 text-yellow-600' : ($task->type === 'both' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600')) }}">{{ $task->type }}</span>
                    </div>
                    @if($task->description)
                        <p class="text-sm text-gray-500 mt-1 ml-8">{{ $task->description }}</p>
                    @endif
                </div>
                <button @click="open = !open" class="text-sm text-blue-600 hover:text-blue-800">
                    <span x-text="open ? 'Close' : '{{ $submission ? 'Edit' : 'Submit' }}'"></span>
                </button>
            </div>

            {{-- Show existing submission info --}}
            @if($submission)
            <div class="mt-3 ml-8 text-sm text-gray-600 space-y-2">
                @if($submission->notes)
                    <p><span class="font-medium">Notes:</span> {{ $submission->notes }}</p>
                @endif
                @if($submission->files->count())
                    <div class="flex flex-wrap gap-2 mt-2">
                        @foreach($submission->files as $file)
                        <div class="relative group">
                            <img src="{{ asset('storage/' . $file->file_path) }}" alt="{{ $file->original_name }}"
                                class="w-20 h-20 object-cover rounded-lg border cursor-pointer"
                                onclick="window.open(this.src, '_blank')">
                            <form method="POST" action="/checklist/files/{{ $file->id }}" class="absolute -top-2 -right-2 hidden group-hover:block">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center" onclick="return confirm('Delete this file?')">&#10005;</button>
                            </form>
                        </div>
                        @endforeach
                    </div>
                @endif
                {{-- Submission logs --}}
                @if($submission->logs->count())
                    <div class="text-xs text-gray-400 mt-2">
                        @foreach($submission->logs as $log)
                            <span>{{ ucfirst($log->action) }} by {{ $log->user->name }} {{ $log->created_at->diffForHumans() }}</span>
                            @if(!$loop->last) <span class="mx-1">|</span> @endif
                        @endforeach
                    </div>
                @endif
            </div>
            @endif

            {{-- Submit/Edit form --}}
            <div x-show="open" x-transition class="mt-4 ml-8">
                <form method="POST" action="/checklist/{{ $task->id }}/submit" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">

                    @if(in_array($task->type, ['note', 'any', 'both']))
                    <div>
                        <textarea name="notes" rows="2" placeholder="Add notes..."
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">{{ $submission->notes ?? '' }}</textarea>
                    </div>
                    @endif

                    @if(in_array($task->type, ['photo', 'any', 'both']))
                    <div>
                        <input type="file" name="files[]" multiple accept="image/*"
                            class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100">
                    </div>
                    @endif

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">
                            {{ $submission ? 'Update' : 'Submit' }}
                        </button>
                        @if($submission)
                        <button type="button" @click="open = false" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200 transition">Cancel</button>
                        @endif
                    </div>
                </form>

                @if($submission)
                <form method="POST" action="/checklist/submission/{{ $submission->id }}" class="mt-2">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-500 hover:text-red-700" onclick="return confirm('Delete entire submission?')">Delete submission</button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-gray-400">
            <p>No tasks yet. Ask an admin to create tasks.</p>
        </div>
        @endforelse
    </div>
</x-layout>
