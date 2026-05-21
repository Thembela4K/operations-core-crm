@extends('layouts.app')

@section('content')
    <div class="flex min-h-[calc(100vh-4rem)] items-center justify-center py-8">
        <section class="grid w-full max-w-5xl overflow-hidden rounded-md border border-neutral-300 bg-white shadow-sm lg:grid-cols-[1fr_440px]">
            <div class="hidden bg-neutral-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
                <div>
                    @if(file_exists(public_path('images/app-logo.png')))
                        <div class="inline-flex rounded-md bg-white p-3">
                            <img class="h-14 w-auto" src="{{ asset('images/app-logo.png') }}" alt="App logo">
                        </div>
                    @endif
                    <h1 class="mt-8 max-w-md text-3xl font-semibold leading-tight">Project & Quotation Assignment Tracker</h1>
                    <p class="mt-4 max-w-md text-sm leading-6 text-neutral-300">
                        Secure access for managing assignments, deadlines, quotation status, documents, and team accountability.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 text-xs text-neutral-300">
                    <div class="rounded-md border border-white/10 bg-white/5 p-3">Projects</div>
                    <div class="rounded-md border border-white/10 bg-white/5 p-3">Quotations</div>
                    <div class="rounded-md border border-white/10 bg-white/5 p-3">Reminders</div>
                </div>
            </div>

            <div class="p-6 sm:p-10">
                <div class="mx-auto max-w-sm">
                    <div class="mb-8 lg:hidden">
                        @if(file_exists(public_path('images/app-logo.png')))
                            <img class="h-14 w-auto" src="{{ asset('images/app-logo.png') }}" alt="App logo">
                        @endif
                        <h1 class="mt-5 text-2xl font-semibold tracking-tight">Project & Quotation Assignment Tracker</h1>
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-800">Secure workspace</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-950">Sign in</h2>
                    <p class="mt-2 text-sm leading-6 text-neutral-600">Use your assigned account to access your department portal.</p>

                    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                        @csrf
                        <label class="block">
                            <span class="label">Email address</span>
                            <input class="input h-11" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                        </label>
                        <label class="block">
                            <span class="label">Password</span>
                            <input class="input h-11" type="password" name="password" autocomplete="current-password" required>
                        </label>
                        <div class="flex items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm text-neutral-600">
                                <input type="checkbox" name="remember" value="1" class="rounded border-neutral-300 text-sky-800 focus:ring-sky-700">
                                Remember me
                            </label>
                        </div>
                        <button class="btn-primary h-11 w-full" type="submit">Sign in</button>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
