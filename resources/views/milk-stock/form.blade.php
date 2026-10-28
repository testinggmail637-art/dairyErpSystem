@extends('layouts.app')

@section('title', $adjustment ? __('Milk stock adjustment') : __('Record milk usage'))

@section('page-actions')
    <a href="{{ route('milk-stock.index', ['date' => $date]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to stock') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <form method="POST" action="{{ $adjustment ? route('milk-stock.adjustments.store') : route('milk-stock.usage.store') }}" class="card" autocomplete="off">
            @csrf
            <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <x-form.select name="movement_type" :label="$adjustment ? __('Adjustment') : __('Used for')" :options="$types"
                                       :selected="array_key_first($types)" required group-class="mb-0" />
                    </div>
                    <div class="col-6">
                        <x-form.date name="movement_date" :label="__('Date')" :value="$date" max="today" required group-class="mb-0" />
                    </div>
                    <div class="col-6">
                        <x-form.select name="shift" :label="__('Shift')" :options="\App\Enums\MilkShift::options()" :placeholder="__('Not specific')" group-class="mb-0" />
                    </div>
                    <div class="col-6">
                        <x-form.select name="stock_source" :label="__('Stock')" :options="\App\Enums\MilkSource::options()" :selected="$source" required group-class="mb-0" />
                    </div>
                    <div class="col-6">
                        <x-form.select name="milk_type" :label="__('Milk type')" :options="\App\Enums\MilkType::options()" :selected="$milkType" required group-class="mb-0" />
                    </div>
                    <div class="col-12 col-md-6">
                        <x-form.quantity name="quantity" :label="__('Litres')" required group-class="mb-0" />
                    </div>
                    <div class="col-12">
                        <x-form.input name="reason" :label="__('Reason')" required maxlength="255" group-class="mb-0"
                                      :placeholder="$adjustment ? __('e.g. Recount at evening, 3 L more than the sheet') : __('e.g. Spilled while pouring')" />
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                <a href="{{ route('milk-stock.index', ['date' => $date]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                <span class="small text-body-secondary ms-md-auto">{{ __('Saved entries cannot be changed — correct a mistake with an adjustment.') }}</span>
            </div>
        </form>
    </div>
</div>
@endsection
