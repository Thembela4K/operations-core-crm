@extends('layouts.app')

@section('content')
    <div class="mx-auto mt-20 max-w-md rounded-md border border-neutral-300 bg-white p-8 shadow-sm">
        @if(file_exists(public_path('images/app-logo.png')))
            <img class="mx-auto mb-5 h-16 w-auto" src="{{ asset('images/app-logo.png') }}" alt="App logo">
        @endif
        <h1 class="text-xl font-semibold">Sign in</h1>
        <p class="mt-1 text-sm text-neutral-600">Access your project and quotation workspace.</p>
        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="label">Email</span>
                <input class="input" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>
            <label class="block">
                <span class="label">Password</span>
                <input class="input" type="password" name="password" required>
            </label>
            <label class="flex items-center gap-2 text-sm text-zinc-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-zinc-300">
                Remember me
            </label>
            <button class="btn-primary w-full" type="submit">Sign in</button>
        </form>
    </div>
@endsection
