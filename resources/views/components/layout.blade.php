<!doctype html>
<html lang="en" class="h-full bg-gray-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <title>{{ $title ?? ($heading ?? 'Checklist Imus') }}</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    [x-cloak] { display: none !important; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    /* Safe area for mobile notch */
    body { padding-top: env(safe-area-inset-top); }
    /* Smooth transitions */
    * { -webkit-tap-highlight-color: transparent; }
  </style>
</head>
<body class="h-full">

<div class="min-h-full" x-data="{ mobileMenu: false }">
  {{-- Top Navigation --}}
  <nav class="bg-gray-800 fixed top-0 inset-x-0 z-50">
    <div class="w-full px-4 sm:px-6 lg:px-8">
      <div class="flex h-14 sm:h-16 items-center justify-between">

        {{-- LEFT: LOGO + NAV --}}
        <div class="flex items-center flex-1 min-w-0">
          <div class="shrink-0">
            <a href="{{ route('checklist.index') }}" class="text-white font-bold text-base sm:text-lg">
              <i class="fa-solid fa-clipboard-check mr-1 text-blue-400"></i>Checklist
            </a>
          </div>

          {{-- Desktop Nav --}}
          <div class="hidden md:flex md:flex-1 overflow-visible">
            <div class="ml-6 flex-1 overflow-visible">
              <div class="no-scrollbar flex items-center gap-1 whitespace-nowrap overflow-x-auto">
                @if(Auth::check())
                  <a href="{{ route('checklist.index') }}"
                     class="rounded-md px-3 py-2 text-sm font-medium {{ request()->is('checklist') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }} transition">
                    <i class="fa-solid fa-clipboard-check mr-1"></i> Checklist
                  </a>
                  @if(Auth::user()->isAdmin())
                    <a href="{{ route('checklist.report') }}"
                       class="rounded-md px-3 py-2 text-sm font-medium {{ request()->is('checklist/report*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }} transition">
                      <i class="fa-solid fa-chart-bar mr-1"></i> Report
                    </a>
                    <a href="{{ route('checklist.manage') }}"
                       class="rounded-md px-3 py-2 text-sm font-medium {{ request()->is('checklist/manage*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }} transition">
                      <i class="fa-solid fa-gear mr-1"></i> Manage Tasks
                    </a>
                    <a href="{{ route('admin.roles') }}"
                       class="rounded-md px-3 py-2 text-sm font-medium {{ request()->is('admin/roles*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }} transition">
                      <i class="fa-solid fa-users-gear mr-1"></i> Roles & Users
                    </a>
                  @endif
                @endif
              </div>
            </div>
          </div>
        </div>

        {{-- RIGHT: PROFILE + LOGOUT (Desktop) --}}
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

        {{-- MOBILE: User avatar + Hamburger --}}
        <div class="md:hidden flex items-center gap-2">
          @if(Auth::check())
            <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center text-white font-bold text-xs">
              {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
          @endif
          <button @click="mobileMenu = !mobileMenu" class="text-gray-400 hover:text-white p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path x-show="!mobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
              <path x-show="mobileMenu" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

      </div>
    </div>

    {{-- Mobile Menu Dropdown --}}
    <div x-show="mobileMenu" @click.away="mobileMenu = false" x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2"
         x-cloak
         class="md:hidden bg-gray-800 border-t border-gray-700 px-4 py-3 space-y-1">
      @if(Auth::check())
        {{-- User info --}}
        <div class="flex items-center gap-3 pb-3 border-b border-gray-700">
          <div class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center text-white font-bold text-sm">
            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
          </div>
          <div>
            <p class="text-white text-sm font-semibold">{{ Auth::user()->name }}</p>
            <p class="text-xs text-gray-400">{{ Auth::user()->role?->name ?? 'No Role' }}</p>
          </div>
        </div>

        {{-- Nav links --}}
        <a href="{{ route('checklist.index') }}" @click="mobileMenu = false"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm {{ request()->is('checklist') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }} transition">
          <i class="fa-solid fa-clipboard-check w-5 text-center"></i> Checklist
        </a>
        @if(Auth::user()->isAdmin())
          <a href="{{ route('checklist.report') }}" @click="mobileMenu = false"
             class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm {{ request()->is('checklist/report*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }} transition">
            <i class="fa-solid fa-chart-bar w-5 text-center"></i> Report
          </a>
          <a href="{{ route('checklist.manage') }}" @click="mobileMenu = false"
             class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm {{ request()->is('checklist/manage*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }} transition">
            <i class="fa-solid fa-gear w-5 text-center"></i> Manage Tasks
          </a>
          <a href="{{ route('admin.roles') }}" @click="mobileMenu = false"
             class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm {{ request()->is('admin/roles*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }} transition">
            <i class="fa-solid fa-users-gear w-5 text-center"></i> Roles & Users
          </a>
        @endif

        {{-- Logout --}}
        <div class="pt-2 border-t border-gray-700">
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-red-400 hover:bg-red-900/30 hover:text-red-300 transition w-full text-left">
              <i class="fa-solid fa-right-from-bracket w-5 text-center"></i> Logout
            </button>
          </form>
        </div>
      @endif
    </div>
  </nav>

  {{-- Page heading (hidden for checklist and admin pages — they have their own header) --}}
  @unless(request()->is('checklist') || request()->is('checklist/*') || request()->is('admin/*'))
  <header class="bg-white shadow-sm mt-14 sm:mt-16">
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
