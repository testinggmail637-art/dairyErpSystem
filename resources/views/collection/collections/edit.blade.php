@extends('layouts.app')

@section('title', __('Change collection of :code', ['code' => $collection->farmer->code]))


@section('page-actions')
    <a href="{{ route('collections.index', ['date' => $collection->collection_date->toDateString(), 'shift' => $collection->shift->value]) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-9 col-xl-7">
        <div class="card">
            <div class="card-body">
                <p class="mb-3"><span class="font-monospace">{{ $collection->farmer->code }}</span> <strong>{{ $collection->farmer->name }}</strong>
                    <span class="text-body-secondary small ms-2">{{ __('Saved at :rate / L = :amount. Saving prices it again from the chart in force on its date.', ['rate' => inr($collection->rate_per_litre), 'amount' => inr($collection->amount)]) }}</span></p>

                <form method="POST" action="{{ route('collections.update', $collection) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-6 col-md-4"><x-form.date name="collection_date" :label="__('Date')" :value="$collection->collection_date" max="today" required /></div>
                        <div class="col-6 col-md-4"><x-form.select name="shift" :label="__('Shift')" :options="\App\Enums\MilkShift::options()" :selected="$collection->shift->value" required /></div>
                        <div class="col-6 col-md-4"><x-form.select name="milk_type" :label="__('Milk')" :options="\App\Enums\MilkType::options()" :selected="$collection->milk_type->value" required /></div>
                        <div class="col-6 col-md-3"><x-form.input name="quantity_litre" :label="__('Litres')" :value="$collection->quantity_litre" inputmode="decimal" required /></div>
                        <div class="col-4 col-md-3"><x-form.input name="fat" :label="__('Fat %')" :value="$collection->fat" inputmode="decimal" /></div>
                        <div class="col-4 col-md-3"><x-form.input name="snf" :label="__('SNF %')" :value="$collection->snf" inputmode="decimal" /></div>
                        <div class="col-4 col-md-3"><x-form.input name="clr" :label="__('CLR')" :value="$collection->clr" inputmode="decimal" /></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-form.select name="quality_status" :label="__('Status')" :options="\App\Enums\QualityStatus::options()" :selected="$collection->quality_status->value" required />
                        </div>
                        <div class="col-12 col-md-8">
                            <x-form.input name="rejection_reason" :label="__('Reason (rejected milk)')" :value="$collection->rejection_reason" maxlength="255" />
                        </div>
                    </div>

                    @if (session('below_standard'))
                        <x-form.switch name="accept_below" :label="__('Accept below-standard milk at the calculated rate')" :checked="false" />
                    @endif

                    <x-form.input name="notes" :label="__('Notes')" :value="$collection->notes" maxlength="500" />
                    <x-form.reason :placeholder="__('e.g. Litres typed wrong, 12 not 21')" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('collections.index', ['date' => $collection->collection_date->toDateString(), 'shift' => $collection->shift->value]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
