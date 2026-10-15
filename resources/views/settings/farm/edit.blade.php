@extends('layouts.app')

@section('title', __('Farm settings'))

@php
    // key => [label, help, type, suffix]
    $groups = [
        __('Milk') => [
            'milk_drop_alert_percent' => [__('Milk drop alert'), __('Alert when an animal gives this much less than its 7-day average.'), 'number', '%'],
            'allow_negative_milk_stock' => [__('Allow negative milk stock'), __('If off, sales/deliveries larger than the available stock are blocked.'), 'switch', null],
        ],
        __('Collection center & billing') => [
            'farmer_payment_cycle_days' => [__('Farmer payment cycle'), __('Farmers are paid every N days.'), 'number', __('days')],
            'bill_due_days' => [__('Bill due after'), __('Customer bill due date = bill date + N days.'), 'number', __('days')],
            'delivery_sheet_generate_time' => [__('Delivery sheet generated at'), __('Daily time (IST) for creating the next delivery sheet.'), 'time', null],
        ],
        __('Breeding & health') => [
            'cow_gestation_days' => [__('Cow gestation'), __('Used to calculate the expected calving date.'), 'number', __('days')],
            'buffalo_gestation_days' => [__('Buffalo gestation'), __('Used to calculate the expected calving date.'), 'number', __('days')],
            'vaccination_reminder_days' => [__('Vaccination reminder'), __('Remind this many days before a vaccination is due.'), 'number', __('days')],
            'calving_reminder_days' => [__('Calving reminder'), __('Remind this many days before the expected calving date.'), 'number', __('days')],
        ],
        __('Staff') => [
            'staff_same_day_edit_only' => [__('Staff can edit own entries on the same day only'), __('Users without the edit permission can change their own entries only on the day they were made.'), 'switch', null],
        ],
    ];
@endphp

@section('content')
<form method="POST" action="{{ route('settings.farm.update') }}">
    @csrf
    @method('PUT')

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('Farm details') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('Farm name') }} <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $farm->name) }}" maxlength="150"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Farm code') }}</label>
                        <input type="text" value="{{ $farm->code }}" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">{{ __('Address') }}</label>
                        <textarea id="address" name="address" rows="3" maxlength="500"
                                  class="form-control @error('address') is-invalid @enderror">{{ old('address', $farm->address) }}</textarea>
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">{{ __('Phone') }}</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $farm->phone) }}" maxlength="20"
                                   class="form-control @error('phone') is-invalid @enderror">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="gstin" class="form-label">{{ __('GSTIN') }}</label>
                            <input type="text" id="gstin" name="gstin" value="{{ old('gstin', $farm->gstin) }}" maxlength="15"
                                   class="form-control text-uppercase @error('gstin') is-invalid @enderror">
                            @error('gstin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            @foreach ($groups as $groupLabel => $fields)
                <div class="card mb-3">
                    <div class="card-header fw-semibold">{{ $groupLabel }}</div>
                    <div class="card-body">
                        @foreach ($fields as $key => [$label, $help, $type, $suffix])
                            @php($field = "settings.{$key}")
                            @php($value = old($field, $settings[$key]))
                            <div class="{{ $loop->last ? '' : 'mb-3' }}">
                                @if ($type === 'switch')
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="settings[{{ $key }}]" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="{{ $key }}"
                                               name="settings[{{ $key }}]" value="1" @checked((bool) $value)>
                                        <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                                    </div>
                                @else
                                    <label for="{{ $key }}" class="form-label">{{ $label }}</label>
                                    <div class="input-group app-input-narrow">
                                        <input type="{{ $type }}" id="{{ $key }}" name="settings[{{ $key }}]" value="{{ $value }}"
                                               @if ($type === 'number') inputmode="numeric" step="1" @endif
                                               class="form-control @error($field) is-invalid @enderror" required>
                                        @if ($suffix)<span class="input-group-text">{{ $suffix }}</span>@endif
                                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                @endif
                                <div class="form-text">{{ $help }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save settings') }}</button>
</form>
@endsection
