<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Tender Proposal & Quotation Assignment Tracker') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell antialiased">
    @auth
        <header class="app-header">
            <div class="app-container">
                <div class="header-top">
                    <a href="{{ route('dashboard') }}" class="brand-lockup">
                        <span class="brand-logo">
                            @if(file_exists(public_path('images/app-logo.png')))
                                <img src="{{ asset('images/app-logo.png') }}" alt="App logo">
                            @else
                                <span>TP</span>
                            @endif
                        </span>
                        <span class="min-w-0">
                            <span class="brand-title">Tender Proposal & Quotation Assignment Tracker</span>
                            <span class="brand-meta">Tender & Quotation Desk</span>
                        </span>
                    </a>

                    <div class="user-cluster">
                        @php
                            $unreadTenderCount = $layoutUnreadTenderAssignments ?? 0;
                            $unreadQuotationCount = $layoutUnreadQuotationAssignments ?? 0;
                        @endphp
                        <div class="user-panel">
                            <span class="user-name">{{ auth()->user()->name }}</span>
                            <span class="user-meta">
                                {{ \App\Models\User::ROLES[auth()->user()->role] ?? auth()->user()->role }}
                                @if(auth()->user()->department)
                                    | {{ auth()->user()->department->name }}
                                @endif
                            </span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn-secondary" type="submit">Log out</button>
                        </form>
                    </div>
                </div>

                <nav class="primary-nav" aria-label="Primary navigation">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="nav-link {{ request()->routeIs('tender-proposals.*') ? 'nav-link-active' : '' }}" href="{{ $layoutNextUnreadTenderUrl ?? route('tender-proposals.index') }}">
                        Tender Proposals
                        @if($unreadTenderCount > 0)
                            <span class="nav-badge" aria-label="{{ $unreadTenderCount }} unread tender proposals">{{ $unreadTenderCount }}</span>
                        @endif
                    </a>
                    <a class="nav-link {{ request()->routeIs('quotations.*') ? 'nav-link-active' : '' }}" href="{{ $layoutNextUnreadQuotationUrl ?? route('quotations.index') }}">
                        Quotations
                        @if($unreadQuotationCount > 0)
                            <span class="nav-badge" aria-label="{{ $unreadQuotationCount }} unread quotations">{{ $unreadQuotationCount }}</span>
                        @endif
                    </a>
                    @if(auth()->user()->canManage())
                        <a class="nav-link {{ request()->routeIs('assignments.*') ? 'nav-link-active' : '' }}" href="{{ route('assignments.index') }}">Assignments</a>
                    @endif
                    <a class="nav-link {{ request()->routeIs('submissions.*') ? 'nav-link-active' : '' }}" href="{{ route('submissions.index') }}">Submissions</a>
                    <a class="nav-link {{ request()->routeIs('reminders.*') ? 'nav-link-active' : '' }}" href="{{ route('reminders.index') }}">Reminders</a>
                    @if(auth()->user()->isSuperAdmin())
                        <a class="nav-link {{ request()->routeIs('users.*') ? 'nav-link-active' : '' }}" href="{{ route('users.index') }}">Users</a>
                        <a class="nav-link {{ request()->routeIs('departments.*') ? 'nav-link-active' : '' }}" href="{{ route('departments.index') }}">Departments</a>
                        <a class="nav-link {{ request()->routeIs('settings.*') ? 'nav-link-active' : '' }}" href="{{ route('settings.index') }}">Settings</a>
                    @endif
                </nav>
            </div>
        </header>
    @endauth

    <main class="app-container py-8">
        @foreach (['success' => 'border-sky-200 bg-sky-50 text-sky-900', 'warning' => 'border-amber-200 bg-amber-50 text-amber-800'] as $key => $classes)
            @if(session($key))
                <div class="flash-message mb-4 rounded-md border px-4 py-3 text-sm {{ $classes }}" data-auto-dismiss>{{ session($key) }}</div>
            @endif
        @endforeach

        @if($errors->any())
            <div class="flash-message mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" data-auto-dismiss>
                {{ $errors->first() }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
