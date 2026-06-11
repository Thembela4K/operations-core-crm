@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="page-title">Edit Client Follow-up</h1><p class="page-subtitle">{{ $activity->subject }}</p></div><a class="btn-secondary" href="{{ route('client-activities.index') }}">Back</a></div>
    <form class="panel mt-6" method="POST" action="{{ route('client-activities.update', $activity) }}">@csrf @method('PUT') @include('client_activities.form')<div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save Follow-up</button></div></form>
@endsection
