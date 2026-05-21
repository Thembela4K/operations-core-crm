<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Project & Quotation Assignment Tracker') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell antialiased">
    @auth
        <header class="app-header">
            <div class="app-container flex flex-wrap items-center justify-between gap-4 py-4">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 text-base font-semibold tracking-tight">
                    @if(file_exists(public_path('images/app-logo.png')))
                        <img class="h-9 w-auto" src="{{ asset('images/app-logo.png') }}" alt="App logo">
                    @endif
                    <span class="truncate">Project & Quotation Assignment Tracker</span>
                </a>
                <nav class="flex flex-wrap items-center gap-1 text-sm">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="nav-link {{ request()->routeIs('projects.*') ? 'nav-link-active' : '' }}" href="{{ route('projects.index') }}">Projects</a>
                    <a class="nav-link {{ request()->routeIs('quotations.*') ? 'nav-link-active' : '' }}" href="{{ route('quotations.index') }}">Quotations</a>
                    @if(auth()->user()->canManage())
                        <a class="nav-link {{ request()->routeIs('assignments.*') ? 'nav-link-active' : '' }}" href="{{ route('assignments.index') }}">Assignments</a>
                    @endif
                    <a class="nav-link {{ request()->routeIs('reminders.*') ? 'nav-link-active' : '' }}" href="{{ route('reminders.index') }}">Reminders</a>
                    @if(auth()->user()->isSuperAdmin())
                        <a class="nav-link {{ request()->routeIs('users.*') ? 'nav-link-active' : '' }}" href="{{ route('users.index') }}">Users</a>
                        <a class="nav-link {{ request()->routeIs('departments.*') ? 'nav-link-active' : '' }}" href="{{ route('departments.index') }}">Departments</a>
                    @endif
                </nav>
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-neutral-500 md:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-secondary" type="submit">Log out</button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main class="app-container py-8">
        @foreach (['success' => 'border-sky-200 bg-sky-50 text-sky-900', 'warning' => 'border-amber-200 bg-amber-50 text-amber-800'] as $key => $classes)
            @if(session($key))
                <div class="mb-4 rounded-md border px-4 py-3 text-sm {{ $classes }}">{{ session($key) }}</div>
            @endif
        @endforeach

        @if($errors->any())
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
