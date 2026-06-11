@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="page-title">New Client Follow-up</h1><p class="page-subtitle">Capture the next client action.</p></div><a class="btn-secondary" href="{{ route('client-activities.index') }}">Back</a></div>
    <form class="panel mt-6" method="POST" action="{{ route('client-activities.store') }}">@csrf @include('client_activities.form')<div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save Follow-up</button></div></form>
@endsection
