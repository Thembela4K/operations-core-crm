@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage application-level options that should stay outside the public repository.</p>
    </div>

    <form method="POST" action="{{ route('settings.update') }}" class="panel mt-6 max-w-3xl">
        @csrf
        @method('PUT')
        <label>
            <span class="label">SPPRA Tender Website URL</span>
            <input class="input" type="url" name="sppra_url" value="{{ old('sppra_url', $sppraUrl) }}" placeholder="Configured privately in the database">
        </label>
        <div class="mt-6">
            <button class="btn-primary" type="submit">Save Settings</button>
        </div>
    </form>
@endsection
