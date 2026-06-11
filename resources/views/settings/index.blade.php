@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Application-level settings that should stay controlled by deployment configuration.</p>
    </div>

    <section class="panel mt-6 max-w-3xl">
        <div class="panel-heading">
            <h2 class="section-title">Managed Configuration</h2>
            <span>Read-only</span>
        </div>
        <div class="mt-4 grid gap-3">
            <div class="metric-card">
                <span>Tender Portal Shortcut</span>
                <strong>Enabled by permission</strong>
                <div class="metric-foot">The dashboard button uses the fixed public tender portal target. Users only see the button when their profile allows SPPRA access.</div>
            </div>
            <div class="metric-card">
                <span>Email and Company Details</span>
                <strong>Private environment values</strong>
                <div class="metric-foot">SMTP, sender identity, signature, and deployment values are managed in the private server environment, not inside the public repository.</div>
            </div>
        </div>
    </section>
@endsection
