<!doctype html>
<html lang="en" class="h-full bg-gray-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? ($heading ?? 'Checklist Imus') }}</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    [x-cloak] { display: none !important; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  </style>
</head>
<body class="h-full">

<div class="min-h-full">
  {{-- Top Navigation --}}
  <nav class="bg-gray-800 fixed top-0 inset-x-0 z-50">
    <div class="w-full px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">

        {{-- LEFT: LOGO + NAV --}}
        <div class="flex items-center flex-1 min-w-0">
          <div class="shrink-0">
            <span class="text-white font-bold text-lg">Checklist</span>
          </div>

          <div class="hidden md:flex md:flex-1 overflow-visible">
            <div class="ml-6 flex-1 overflow-visible">
              <div class="no-scrollbar flex items-center gap-2 whitespace-nowrap overflow-x-auto">
                @if(Auth::check())
                  <a href="{{ route('checklist.index') }}"
                     class="rounded-md px-3 py-2 text-sm font-medium {{ request()->is('checklist') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <i class="fa-solid fa-clipboard-check mr-1"></i> Checklist
                  </a>
                  @if(Auth::user()->isAdmin())
                    <a href="{{ route('checklist.report') }}"
                       class="rounded-md px-3 py-2 text-sm font-medium {{ request()->is('checklist/report*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                      <i class="fa-solid fa-chart-bar mr-1"></i> Report
                    </a>
                    <a href="{{ route('checklist.manage') }}"
                       class="rounded-md px-3 py-2 text-sm font-medium {{ request()->is('checklist/manage*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                      <i class="fa-solid fa-gear mr-1"></i> Manage Tasks
                    </a>
                    <a href="{{ route('admin.roles') }}"
                       class="rounded-md px-3 py-2 text-sm font-medium {{ request()->is('admin/roles*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                      <i class="fa-solid fa-users-gear mr-1"></i> Roles & Users
                    </a>
                  @endif
                @endif
              </div>
            </div>
          </div>
        </div>

        {{-- RIGHT: PROFILE + LOGOUT --}}
        <div class="hidden md:flex items-center space-x-4">
          @if(Auth::check())
            <div class="flex items-center gap-3">
              <div class="text-gray-300 text-sm text-right leading-tight">
                <div>{{ Auth::user()->name }}</div>
                <div class="text-xs text-gray-400">{{ Auth::user()->role?->name ?? 'No Role' }}</div>
              </div>
              <div class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center text-white font-bold text-sm">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
              </div>
            </div>
          @endif

          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
              class="bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded transition">
              Logout
            </button>
          </form>
        </div>

        {{-- MOBILE MENU BUTTON --}}
        <div class="md:hidden flex items-center" x-data="{ open: false }">
          <button @click="open = !open" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
              <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>

          <div x-show="open" @click.away="open = false" x-transition
               class="absolute top-16 left-0 right-0 bg-gray-800 border-t border-gray-700 px-4 py-3 space-y-2 z-50">
            @if(Auth::check())
              <a href="{{ route('checklist.index') }}" class="block text-gray-300 hover:text-white text-sm py-1.5">Checklist</a>
              @if(Auth::user()->isAdmin())
                <a href="{{ route('checklist.report') }}" class="block text-gray-300 hover:text-white text-sm py-1.5">Report</a>
                <a href="{{ route('checklist.manage') }}" class="block text-gray-300 hover:text-white text-sm py-1.5">Manage Tasks</a>
                <a href="{{ route('admin.roles') }}" class="block text-gray-300 hover:text-white text-sm py-1.5">Roles & Users</a>
              @endif
              <div class="border-t border-gray-700 pt-2 mt-2">
                <p class="text-gray-400 text-xs">{{ Auth::user()->name }} ({{ Auth::user()->role?->name ?? 'No Role' }})</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                  @csrf
                  <button type="submit" class="text-red-400 hover:text-red-300 text-sm">Logout</button>
                </form>
              </div>
            @endif
          </div>
        </div>

      </div>
    </div>
  </nav>

  {{-- Page heading (hidden for checklist and admin pages — they have their own header) --}}
  @unless(request()->is('checklist') || request()->is('checklist/*') || request()->is('admin/*'))
  <header class="bg-white shadow-sm mt-16">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      <h1 class="text-3xl font-bold tracking-tight text-gray-900">
        {{ $heading ?? 'Dashboard' }}
      </h1>
    </div>
  </header>
  @endunless

  {{-- Page content --}}
  <main>
    @if (request()->is(['checklist', 'checklist/*', 'admin/*']))
      <div class="w-full px-0">
        {{ $slot }}
      </div>
    @else
      <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        {{ $slot }}
      </div>
    @endif
  </main>
</div>
</body>
</html>
