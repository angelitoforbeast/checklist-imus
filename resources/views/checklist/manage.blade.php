<x-layout title="Manage Tasks">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">Manage Tasks</h1>
    </div>

    {{-- Add New Task --}}
    <div class="bg-white rounded-xl shadow-sm border p-5 mb-6" x-data="{ open: false }">
        <button @click="open = !open" class="text-sm font-medium text-blue-600 hover:text-blue-800">
            + Add New Task
        </button>
        <div x-show="open" x-transition class="mt-4">
            <form method="POST" action="/checklist/tasks" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type *</label>
                        <select name="type" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="any">Any</option>
                            <option value="photo">Photo</option>
                            <option value="note">Note</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" name="description" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">AI Prompt (custom instructions for AI analysis)</label>
                    <textarea name="ai_prompt" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g., Check if the floor is clean and mopped properly..."></textarea>
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">Create Task</button>
            </form>
        </div>
    </div>

    {{-- Task List --}}
    <div class="space-y-3" x-data="taskReorder()" x-ref="taskList">
        @foreach($tasks as $task)
        <div class="bg-white rounded-xl shadow-sm border p-5 task-item" data-id="{{ $task->id }}"
             x-data="{ editing: false }">
            <div class="flex items-start justify-between">
                <div class="flex items-center space-x-3 flex-1">
                    <div class="cursor-grab text-gray-300 hover:text-gray-500 drag-handle" title="Drag to reorder">
                        &#9776;
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center space-x-2">
                            <h3 class="font-semibold text-gray-800">{{ $task->title }}</h3>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $task->type === 'photo' ? 'bg-purple-100 text-purple-600' : ($task->type === 'note' ? 'bg-yellow-100 text-yellow-600' : ($task->type === 'both' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600')) }}">{{ $task->type }}</span>
                            @if(!$task->is_active)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-600">Inactive</span>
                            @endif
                        </div>
                        @if($task->description)
                            <p class="text-sm text-gray-500 mt-0.5">{{ $task->description }}</p>
                        @endif
                        @if($task->ai_prompt)
                            <p class="text-xs text-purple-500 mt-0.5">AI: {{ Str::limit($task->ai_prompt, 80) }}</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="editing = !editing" class="text-sm text-blue-600 hover:text-blue-800">Edit</button>
                    <form method="POST" action="/checklist/tasks/{{ $task->id }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm text-red-500 hover:text-red-700" onclick="return confirm('Delete this task?')">Delete</button>
                    </form>
                </div>
            </div>

            {{-- Edit form --}}
            <div x-show="editing" x-transition class="mt-4 border-t pt-4">
                <form method="POST" action="/checklist/tasks/{{ $task->id }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input type="text" name="title" value="{{ $task->title }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="type" class="w-full px-3 py-2 border rounded-lg text-sm">
                                <option value="any" {{ $task->type === 'any' ? 'selected' : '' }}>Any</option>
                                <option value="photo" {{ $task->type === 'photo' ? 'selected' : '' }}>Photo</option>
                                <option value="note" {{ $task->type === 'note' ? 'selected' : '' }}>Note</option>
                                <option value="both" {{ $task->type === 'both' ? 'selected' : '' }}>Both</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input type="text" name="description" value="{{ $task->description }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">AI Prompt</label>
                        <textarea name="ai_prompt" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm">{{ $task->ai_prompt }}</textarea>
                    </div>
                    <div class="flex items-center space-x-3">
                        <label class="flex items-center space-x-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ $task->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Save</button>
                        <button type="button" @click="editing = false" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <script>
    function taskReorder() {
        return {
            init() {
                // Simple drag-and-drop reorder using HTML5 drag API
                const list = this.$refs.taskList;
                let dragItem = null;

                list.querySelectorAll('.task-item').forEach(item => {
                    const handle = item.querySelector('.drag-handle');
                    handle.addEventListener('mousedown', () => {
                        item.draggable = true;
                    });
                    item.addEventListener('dragstart', (e) => {
                        dragItem = item;
                        item.classList.add('opacity-50');
                    });
                    item.addEventListener('dragend', () => {
                        item.draggable = false;
                        item.classList.remove('opacity-50');
                        this.saveOrder();
                    });
                    item.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        if (dragItem && dragItem !== item) {
                            const rect = item.getBoundingClientRect();
                            const mid = rect.top + rect.height / 2;
                            if (e.clientY < mid) {
                                list.insertBefore(dragItem, item);
                            } else {
                                list.insertBefore(dragItem, item.nextSibling);
                            }
                        }
                    });
                });
            },

            async saveOrder() {
                const items = this.$refs.taskList.querySelectorAll('.task-item');
                const order = Array.from(items).map(i => parseInt(i.dataset.id));
                await fetch('/checklist/tasks/reorder', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    },
                    body: JSON.stringify({ order }),
                });
            }
        }
    }
    </script>
</x-layout>
