@extends('layouts.app')

@section('title', __('New animal sale'))

@section('page-actions')
    <a href="{{ route('animal-sales.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to sales') }}</a>
@endsection

@php
    // one animal row; also used (with index __I__) as the template for "Add animal"
    $row = function ($i, $r) use ($animalLabels, $errors) {
        $err = fn ($f) => $errors->first("animals.{$i}.{$f}");
        $feedback = fn ($f) => $err($f) ? '<div class="invalid-feedback d-block">'.e($err($f)).'</div>' : '';
        $id = $r['animal_id'] ?? null;
        $option = $id && isset($animalLabels[$id]) ? '<option value="'.e($id).'" selected>'.e($animalLabels[$id]).'</option>' : '';

        return '<tr data-row>'
            .'<td><select name="animals['.$i.'][animal_id]" class="form-select js-select2'.($err('animal_id') ? ' is-invalid' : '').'" required'
            .' data-ajax-url="'.e(route('animals.search', ['active' => 1])).'" data-placeholder="'.e(__('Search active animal by tag or name')).'">'
            .'<option value=""></option>'.$option.'</select>'.$feedback('animal_id').'</td>'
            .'<td><input type="text" name="animals['.$i.'][price]" value="'.e((string) ($r['price'] ?? '')).'" class="form-control text-end text-money'.($err('price') ? ' is-invalid' : '').'"'
            .' inputmode="decimal" pattern="\d+(\.\d{1,2})?" required data-price aria-label="'.e(__('Price')).'">'.$feedback('price').'</td>'
            .'<td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-row title="'.e(__('Remove')).'"><i class="bi bi-x-lg"></i></button></td>'
            .'</tr>';
    };
@endphp

@section('content')
<form method="POST" action="{{ route('animal-sales.store') }}" id="sale-form">
    @csrf
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <x-form.select name="party_id" :label="__('Buyer (party)')" :options="$selectedParty" :placeholder="__('Search party')"
                                   select2 :data-ajax-url="route('parties.search', ['type' => 'buyer'])" required group-class="mb-0"
                                   :help="__('Add new buyers under Trading → Parties.')" />
                </div>
                <div class="col-12 col-md-3">
                    <x-form.date name="sale_date" :label="__('Sale date')" :value="today()->toDateString()" max="today" required group-class="mb-0" />
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-tags me-1"></i>{{ __('Animals sold') }}</div>
        @error('animals')<div class="alert alert-danger m-3 mb-0">{{ $message }}</div>@enderror
        <div class="table-responsive">
            <table class="table align-top mb-0" style="min-width: 560px">
                <thead>
                    <tr class="small">
                        <th>{{ __('Animal') }} *</th>
                        <th class="text-end" style="width: 14rem">{{ __('Price') }} (₹) *</th>
                        <th style="width: 3rem"></th>
                    </tr>
                </thead>
                <tbody data-rows>
                    @foreach (array_values($rows) as $i => $r)
                        {!! $row($i, (array) $r) !!}
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body pt-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-add-row><i class="bi bi-plus-lg me-1"></i>{{ __('Add animal') }}</button>
            <span class="form-text ms-2">{{ __('Only active animals can be sold. They are marked "Sold" and leave milk entry; their history stays.') }}</span>
        </div>
        <template data-row-template>{!! $row('__I__', []) !!}</template>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <x-form.money name="selling_costs" :label="__('Selling costs')" value="0" group-class="mb-0" data-cost
                                  :help="__('Commission, transport or other costs we pay for this sale.')" />
                </div>
                <div class="col-12 col-md-8">
                    <x-form.textarea name="notes" :label="__('Notes')" rows="2" maxlength="1000" group-class="mb-0" />
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-4 justify-content-end">
            <div>{{ __('Sale price') }}: <strong data-total="price">₹0.00</strong></div>
            <div>{{ __('Selling costs') }}: <strong data-total="costs">₹0.00</strong></div>
            <div>{{ __('Net amount') }}: <strong class="fs-5" data-total="net">₹0.00</strong></div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save sale') }}</button>
        <a href="{{ route('animal-sales.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/animal-sale-form.js') }}"></script>
@endpush
