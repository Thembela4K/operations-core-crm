@extends('layouts.app')

@section('content')
@php
    $profile = $profile ?? $subject->profileOrCreate();
    $isSelf = auth()->id() === $subject->id;
    $action = $isSelf
        ? route('profile.information.update')
        : route('user-information.update', $subject);
@endphp

<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">{{ $isSelf ? 'My Information' : $subject->name.' — Information' }}</h1>
        <p class="page-subtitle">
            {{ $subject->name }}
            @if($subject->employee_no) | {{ $subject->employee_no }} @endif
            | {{ \App\Models\User::ROLES[$subject->role] ?? $subject->role }}
            @if($subject->department) | {{ $subject->department->name }} @endif
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if($canManageAll)
            <a class="btn-secondary" href="{{ route('user-information.index') }}">All Staff Information</a>
        @endif
        <a class="btn-secondary" href="{{ route('profile.password') }}">Change Password</a>
    </div>
</div>

<form class="mt-6 space-y-6" method="POST" action="{{ $action }}">
    @csrf
    @method('PUT')

    <section class="panel">
        <h2 class="section-title">Contact Details</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <label><span class="label">Phone</span><input class="input" name="phone" value="{{ old('phone', $profile->phone) }}" placeholder="(+268) ..."></label>
            <label><span class="label">Alternate Phone</span><input class="input" name="alternate_phone" value="{{ old('alternate_phone', $profile->alternate_phone) }}"></label>
            <label><span class="label">Personal Email</span><input class="input" type="email" name="personal_email" value="{{ old('personal_email', $profile->personal_email) }}"></label>
        </div>
    </section>

    <section class="panel">
        <h2 class="section-title">Personal Details</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <label><span class="label">National ID / Passport</span><input class="input" name="national_id" value="{{ old('national_id', $profile->national_id) }}"></label>
            <label><span class="label">Date of Birth</span><input class="input" type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($profile->date_of_birth)->format('Y-m-d')) }}"></label>
            <label>
                <span class="label">Gender</span>
                <select class="input" name="gender">
                    <option value="">Not stated</option>
                    @foreach(\App\Models\UserProfile::GENDERS as $option)
                        <option value="{{ $option }}" @selected(old('gender', $profile->gender) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label><span class="label">Nationality</span><input class="input" name="nationality" value="{{ old('nationality', $profile->nationality) }}"></label>
            <label>
                <span class="label">Marital Status</span>
                <select class="input" name="marital_status">
                    <option value="">Not stated</option>
                    @foreach(\App\Models\UserProfile::MARITAL_STATUSES as $option)
                        <option value="{{ $option }}" @selected(old('marital_status', $profile->marital_status) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="panel">
        <h2 class="section-title">Addresses</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="md:col-span-2"><span class="label">Residential Address</span><textarea class="input min-h-20" name="residential_address">{{ old('residential_address', $profile->residential_address) }}</textarea></label>
            <label class="md:col-span-2"><span class="label">Postal Address</span><textarea class="input min-h-20" name="postal_address">{{ old('postal_address', $profile->postal_address) }}</textarea></label>
            <label><span class="label">City / Town</span><input class="input" name="city" value="{{ old('city', $profile->city) }}"></label>
            <label><span class="label">Region</span><input class="input" name="region" value="{{ old('region', $profile->region) }}"></label>
        </div>
    </section>

    <section class="panel">
        <h2 class="section-title">Emergency Contact</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <label><span class="label">Name</span><input class="input" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile->emergency_contact_name) }}"></label>
            <label><span class="label">Phone</span><input class="input" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $profile->emergency_contact_phone) }}"></label>
            <label><span class="label">Relationship</span><input class="input" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $profile->emergency_contact_relationship) }}"></label>
        </div>
    </section>

    <section class="panel">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="section-title">Banking Information</h2>
            <span class="text-xs text-neutral-500">Visible to you, the Business Admin and Directors.</span>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <label><span class="label">Bank</span><input class="input" name="bank_name" value="{{ old('bank_name', $profile->bank_name) }}"></label>
            <label><span class="label">Account Name</span><input class="input" name="bank_account_name" value="{{ old('bank_account_name', $profile->bank_account_name) }}"></label>
            <label><span class="label">Account Number</span><input class="input" name="bank_account_number" value="{{ old('bank_account_number', $profile->bank_account_number) }}"></label>
            <label><span class="label">Branch</span><input class="input" name="bank_branch" value="{{ old('bank_branch', $profile->bank_branch) }}"></label>
            <label><span class="label">Branch Code</span><input class="input" name="bank_branch_code" value="{{ old('bank_branch_code', $profile->bank_branch_code) }}"></label>
            <label>
                <span class="label">Account Type</span>
                <select class="input" name="bank_account_type">
                    <option value="">Not stated</option>
                    @foreach(\App\Models\UserProfile::BANK_ACCOUNT_TYPES as $option)
                        <option value="{{ $option }}" @selected(old('bank_account_type', $profile->bank_account_type) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    @if($canManageAll && ! $isSelf)
        <section class="panel">
            <h2 class="section-title">Administrator Notes</h2>
            <label class="mt-4 block"><span class="label">Notes</span><textarea class="input min-h-20" name="notes">{{ old('notes', $profile->notes) }}</textarea></label>
        </section>
    @endif

    <div class="flex justify-end gap-3">
        <a class="btn-secondary" href="{{ $isSelf ? route('dashboard') : route('user-information.index') }}">Cancel</a>
        <button class="btn-primary" type="submit">Save Information</button>
    </div>
</form>

@endsection
