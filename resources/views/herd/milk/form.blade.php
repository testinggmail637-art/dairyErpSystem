@extends('layouts.app')

@section('title', $record->exists ? __('Edit milk entry') : __('Single milk entry'))

@section('page-actions')
    <a href="{{ route('milk-production.entries') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to entries') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <form method="POST" action="{{ $record->exists ? route('milk-production.update', $record) : route('milk-production.store') }}" class="card" autocomplete="off">
            @csrf
            @if ($record->exists) @method('PUT') @else
                <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>
            @endif
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <x-form.date name="production_date" :label="__('Date')" :value="$record->production_date" max="today" required group-class="mb-0" />
                    </div>
                    <div class="col-6">
                        <x-form.select name="shift" :label="__('Shift')" :options="\App\Enums\MilkShift::options()" :selected="$record->shift?->value"
                                       required group-class="mb-0" />
                    </div>
                    <div class="col-12">
                        <x-form.select name="animal_id" :label="__('Animal')" :options="$selectedAnimal" :selected="$record->animal_id"
                                       :placeholder="__('Search by tag or name')" select2 required group-class="mb-0"
                                       :data-ajax-url="route('animals.search', ['milking' => 1])"
                                       :help="__('Only active milking females are listed.')" />
                    </div>
                    <div class="col-12 col-md-4">
                        <x-form.quantity name="quantity_litre" :label="__('Litres')" :value="$record->quantity_litre" required group-class="mb-0" />
                    </div>
                    <div class="col-6 col-md-4">
                        <x-form.quantity name="fat" :label="__('Fat')" unit="%" :value="$record->fat" group-class="mb-0" />
                    </div>
                    <div class="col-6 col-md-4">
                        <x-form.quantity name="snf" :label="__('SNF')" unit="%" :value="$record->snf" group-class="mb-0" />
                    </div>
                    <div class="col-12">
                        <x-form.textarea name="notes" :label="__('Notes')" :value="$record->notes" rows="2" maxlength="1000" group-class="mb-0" />
                    </div>
                    @if ($record->exists)
                        <div class="col-12">
                            <x-form.reason :placeholder="__('e.g. Wrong litres typed earlier')" group-class="mb-0" />
                        </div>
                    @endif
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                <a href="{{ route('milk-production.entries') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
