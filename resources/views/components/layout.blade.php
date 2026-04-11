<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Checklist Imus' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">
    @auth
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-14">
                <div class="flex items-center space-x-6">
                    <a href="/checklist" class="text-lg font-bold text-blue-600">Checklist Imus</a>
                    <a href="/checklist" class="text-sm {{ request()->is('checklist') && !request()->is('checklist/*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">Checklist</a>
                    <a href="/checklist/report" class="text-sm {{ request()->is('checklist/report*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">Report</a>
                    @if(auth()->user()->isAdmin())
                    <a href="/checklist/manage" class="text-sm {{ request()->is('checklist/manage*') ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">Manage Tasks</a>
                    @endif
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">{{ auth()->user()->name }} <span class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ auth()->user()->role }}</span></span>
                    <form method="POST" action="/logout" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-red-500 hover:text-red-700">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
    @endauth

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
            {{ session('success') }}
        </div>
        @endif

        @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
