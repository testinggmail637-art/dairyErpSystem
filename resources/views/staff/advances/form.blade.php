@extends('layouts.app')

@section('title', __('Give advance'))

@section('page-actions')
    <a href="{{ route('staff-advances.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to advances') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('staff-advances.store') }}">
                    @csrf
                    <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>

                    <x-form.select name="staff_member_id" :label="__('Staff member')" :options="$staffOptions" :selected="$selected"
                                   :placeholder="__('Choose staff member')" select2 required />

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.date name="advance_date" :label="__('Date')" :value="today()->toDateString()" max="today" required />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.money name="amount" :label="__('Amount')" required />
                        </div>
                    </div>

                    <x-form.select name="payment_mode_id" :label="__('Payment mode')" :options="$modes" :placeholder="__('Choose mode')" required />
                    <x-form.textarea name="note" :label="__('Note')" rows="2" maxlength="500" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save advance') }}</button>
                        <a href="{{ route('staff-advances.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
