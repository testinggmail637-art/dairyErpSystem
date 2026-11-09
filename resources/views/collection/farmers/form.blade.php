@extends('layouts.app')

@section('title', $farmer->exists ? __('Edit farmer :code', ['code' => $farmer->code]) : __('Add farmer'))

@section('page-actions')
    <a href="{{ $farmer->exists ? route('farmers.show', $farmer) : route('farmers.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-10 col-xl-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $farmer->exists ? route('farmers.update', $farmer) : route('farmers.store') }}">
                    @csrf
                    @if ($farmer->exists) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <x-form.input name="name" :label="__('Farmer name')" :value="$farmer->name" maxlength="150" required autofocus />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="father_name" :label="__('Father\'s name')" :value="$farmer->father_name" maxlength="150" />
                        </div>
                        <div class="col-12 col-md-3">
                            <x-form.select name="default_milk_type" :label="__('Usual milk')" :options="\App\Enums\MilkType::options()"
                                           :selected="$farmer->default_milk_type?->value" required />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-form.input name="phone" type="tel" :label="__('Mobile number')" :value="$farmer->phone" maxlength="16" inputmode="numeric" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="village" :label="__('Village')" :value="$farmer->village" maxlength="100" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="address" :label="__('Address')" :value="$farmer->address" maxlength="500" />
                        </div>
                    </div>

                    <h2 class="h6 mt-2">{{ __('Payment details') }}</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.input name="bank_account_name" :label="__('Account holder name')" :value="$farmer->bank_account_name" maxlength="150" />
                        </div>
                        <div class="col-7 col-md-3">
                            <x-form.input name="bank_account_no" :label="__('Account number')" :value="$farmer->bank_account_no" maxlength="20" inputmode="numeric" />
                        </div>
                        <div class="col-5 col-md-3">
                            <x-form.input name="ifsc" :label="__('IFSC')" :value="$farmer->ifsc" maxlength="11" class="text-uppercase" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.input name="upi_id" :label="__('UPI ID')" :value="$farmer->upi_id" maxlength="100" :placeholder="__('e.g. ramesh@upi')" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.money name="opening_balance" :label="__('Opening balance (we owe the farmer)')" :value="$farmer->opening_balance"
                                          allow-negative :help="__('What we owed the farmer when we started using the software. Use a minus amount if the farmer owes us.')" />
                        </div>
                    </div>

                    <x-form.textarea name="notes" :label="__('Notes')" :value="$farmer->notes" rows="2" maxlength="1000" />

                    <x-form.switch name="is_active" :label="__('Active')" :checked="$farmer->is_active" />

                    @if ($farmer->exists)
                        <x-form.reason :placeholder="__('e.g. New bank account')" />
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ $farmer->exists ? route('farmers.show', $farmer) : route('farmers.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
