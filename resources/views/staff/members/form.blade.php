@extends('layouts.app')

@section('title', $member->exists ? __('Edit staff member') : __('Add staff member'))

@section('page-actions')
    <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to staff') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $member->exists ? route('staff.update', $member) : route('staff.store') }}">
                    @csrf
                    @if ($member->exists) @method('PUT') @endif

                    <x-form.input name="name" :label="__('Name')" :value="$member->name" maxlength="150" required autofocus />

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.input name="designation" :label="__('Designation')" :value="$member->designation" maxlength="100" required
                                          :placeholder="__('e.g. Milker, Helper, Driver')" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.input name="phone" type="tel" :label="__('Mobile number')" :value="$member->phone" maxlength="16" inputmode="numeric" />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.money name="monthly_salary" :label="__('Monthly salary')" :value="$member->monthly_salary" required />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.select name="user_id" :label="__('Login user (optional)')" :options="$users" :selected="$member->user_id"
                                           :placeholder="__('Not a login user')" :help="__('Link when this worker also signs in to the app.')" />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.date name="joining_date" :label="__('Joining date')" :value="$member->joining_date?->toDateString()" required />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.date name="leaving_date" :label="__('Leaving date')" :value="$member->leaving_date?->toDateString()" />
                        </div>
                    </div>

                    <x-form.switch name="is_active" :label="__('Active (shown in attendance and salary runs)')" :checked="$member->is_active" />

                    @if ($member->exists)
                        <x-form.reason :placeholder="__('e.g. Salary raised from April')" />
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
