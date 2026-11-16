@extends('layouts.app')

@section('title', __('New animal purchase'))

@section('page-actions')
    <a href="{{ route('animal-purchases.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to purchases') }}</a>
@endsection

@php
    $rows = old('animals') ?: [[]];
    $typeOptions = \App\Enums\AnimalType::options();
    $genderOptions = \App\Enums\AnimalGender::options();
    $stageOptions = \App\Enums\LifecycleStage::options();
@endphp

{{-- one animal row; also used (with index __I__) as the template for "Add animal" --}}
@php
$row = function ($i, $r) use ($typeOptions, $genderOptions, $stageOptions, $ownershipOptions, $breeds, $errors) {
    $err = fn ($f) => $errors->first("animals.{$i}.{$f}");
    $v = fn ($f, $d = '') => e((string) ($r[$f] ?? $d));
    $opts = function ($options, $selected, $blank = null) {
        $html = $blank !== null ? '<option value="">'.e($blank).'</option>' : '';
        foreach ($options as $value => $label) {
            $html .= '<option value="'.e($value).'"'.((string) $selected === (string) $value ? ' selected' : '').'>'.e($label).'</option>';
        }
        return $html;
    };
    $breedOpts = '<option value="">'.e(__('Breed')).'</option>';
    foreach ($breeds as $b) {
        $breedOpts .= '<option value="'.$b->id.'" data-type="'.e($b->animal_type->value).'"'.((string) ($r['breed_id'] ?? '') === (string) $b->id ? ' selected' : '').'>'.e($b->name).'</option>';
    }
    $cell = fn ($f, $html) => '<td>'.$html.($err($f) ? '<div class="invalid-feedback d-block">'.e($err($f)).'</div>' : '').'</td>';
    $cls = fn ($f, $base) => $base.($err($f) ? ' is-invalid' : '');

    return '<tr data-row>'
        .$cell('tag_no', '<input type="text" name="animals['.$i.'][tag_no]" value="'.$v('tag_no').'" class="'.$cls('tag_no', 'form-control form-control-sm').'" maxlength="30" required aria-label="'.e(__('Tag no.')).'">')
        .$cell('animal_type', '<select name="animals['.$i.'][animal_type]" class="'.$cls('animal_type', 'form-select form-select-sm').'" data-type required>'.$opts($typeOptions, $r['animal_type'] ?? 'cow').'</select>')
        .$cell('breed_id', '<select name="animals['.$i.'][breed_id]" class="'.$cls('breed_id', 'form-select form-select-sm').'" data-breed>'.$breedOpts.'</select>')
        .$cell('gender', '<select name="animals['.$i.'][gender]" class="'.$cls('gender', 'form-select form-select-sm').'" data-gender required>'.$opts($genderOptions, $r['gender'] ?? 'female').'</select>')
        .$cell('age_months', '<input type="number" name="animals['.$i.'][age_months]" value="'.$v('age_months').'" class="'.$cls('age_months', 'form-control form-control-sm').'" min="0" max="300" step="1" aria-label="'.e(__('Age (months)')).'">')
        .$cell('lifecycle_stage', '<select name="animals['.$i.'][lifecycle_stage]" class="'.$cls('lifecycle_stage', 'form-select form-select-sm').'" data-stage required>'.$opts($stageOptions, $r['lifecycle_stage'] ?? 'milking').'</select>')
        .$cell('ownership_type', '<select name="animals['.$i.'][ownership_type]" class="'.$cls('ownership_type', 'form-select form-select-sm').'" required>'.$opts($ownershipOptions, $r['ownership_type'] ?? 'own_herd').'</select>')
        .$cell('price', '<input type="text" name="animals['.$i.'][price]" value="'.$v('price').'" class="'.$cls('price', 'form-control form-control-sm text-end text-money').'" inputmode="decimal" pattern="\d+(\.\d{1,2})?" required data-price aria-label="'.e(__('Price')).'">')
        .'<td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-row title="'.e(__('Remove')).'"><i class="bi bi-x-lg"></i></button></td>'
        .'</tr>';
};
@endphp

@section('content')
<form method="POST" action="{{ route('animal-purchases.store') }}" id="purchase-form"
      data-stages='@json($stagesByGender)'>
    @csrf
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <x-form.select name="party_id" :label="__('Seller (party)')" :options="$selectedParty" :placeholder="__('Search party')"
                                   select2 :data-ajax-url="route('parties.search', ['type' => 'seller'])" required group-class="mb-0"
                                   :help="__('Add new sellers under Trading → Parties.')" />
                </div>
                <div class="col-12 col-md-3">
                    <x-form.date name="purchase_date" :label="__('Purchase date')" :value="today()->toDateString()" max="today" required group-class="mb-0" />
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-tags me-1"></i>{{ __('Animals bought') }}</div>
        @error('animals')<div class="alert alert-danger m-3 mb-0">{{ $message }}</div>@enderror
        <div class="table-responsive">
            <table class="table align-top mb-0" style="min-width: 960px">
                <thead>
                    <tr class="small">
                        <th>{{ __('Tag no.') }} *</th>
                        <th>{{ __('Type') }} *</th>
                        <th>{{ __('Breed') }}</th>
                        <th>{{ __('Gender') }} *</th>
                        <th>{{ __('Approx. age (months)') }}</th>
                        <th>{{ __('Stage') }} *</th>
                        <th>{{ __('Ownership') }} *</th>
                        <th class="text-end">{{ __('Price') }} (₹) *</th>
                        <th></th>
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
            <span class="form-text ms-2">{{ __('Each row creates an animal with source "Purchased". Resale animals are bought to sell again.') }}</span>
        </div>
        <template data-row-template>{!! $row('__I__', []) !!}</template>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4"><x-form.money name="transport_cost" :label="__('Transport')" value="0" group-class="mb-0" data-cost /></div>
                <div class="col-12 col-md-4"><x-form.money name="commission" :label="__('Commission')" value="0" group-class="mb-0" data-cost /></div>
                <div class="col-12 col-md-4"><x-form.money name="other_cost" :label="__('Other costs')" value="0" group-class="mb-0" data-cost /></div>
            </div>
            <p class="form-text mb-0 mt-2">{{ __('These costs are shared over the animals in proportion to their price.') }}</p>
            <x-form.textarea name="notes" :label="__('Notes')" rows="2" maxlength="1000" group-class="mt-3 mb-0" />
        </div>
        <div class="card-footer d-flex flex-wrap gap-4 justify-content-end">
            <div>{{ __('Animal price') }}: <strong data-total="price">₹0.00</strong></div>
            <div>{{ __('Costs') }}: <strong data-total="costs">₹0.00</strong></div>
            <div>{{ __('Total amount') }}: <strong class="fs-5" data-total="all">₹0.00</strong></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-cash me-1"></i>{{ __('Payment now (optional)') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4"><x-form.money name="initial_payment" :label="__('Amount paid to seller')" group-class="mb-0" /></div>
                <div class="col-12 col-md-4">
                    <x-form.select name="initial_payment_mode_id" :label="__('Payment mode')" :options="$modes" :placeholder="__('Choose mode')" group-class="mb-0" />
                </div>
                <div class="col-12 col-md-4"><x-form.input name="initial_reference_no" :label="__('Reference no.')" maxlength="50" group-class="mb-0" /></div>
            </div>
            <p class="form-text mb-0 mt-2">{{ __('Leave empty to pay later. Later installments are recorded under Trading → Payments.') }}</p>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save purchase') }}</button>
        <a href="{{ route('animal-purchases.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/animal-purchase-form.js') }}"></script>
@endpush
