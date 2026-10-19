@extends('layouts.app')

@section('title', $expense->exists ? __('Edit expense') : __('Add expense'))

@section('page-actions')
    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to expenses') }}</a>
@endsection

@php($isCredit = (bool) old('is_credit', $expense->is_credit))

@section('content')
<form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}"
      enctype="multipart/form-data" id="expense-form">
    @csrf
    @if ($expense->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.date name="expense_date" :label="__('Date')" :value="$expense->expense_date" max="today" required group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.money name="amount" :label="__('Amount')" :value="$expense->amount" required group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.select name="category_id" :label="__('Category')" :options="$categories" :selected="$expense->category_id"
                                           :placeholder="__('Choose category')" required group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.select name="subcategory_id" :label="__('Subcategory')" :options="$subcategories" :selected="$expense->subcategory_id"
                                           :placeholder="__('— None —')" group-class="mb-0"
                                           data-children-of="#category_id" :data-children-url="route('expense-categories.children', '__ID__')"
                                           :disabled="$subcategories->isEmpty()" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.select name="section_id" :label="__('Section')" :options="$sections" :selected="$expense->section_id"
                                           :placeholder="__('Choose section')" required group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.select name="animal_id" :label="__('Animal')" :options="$selectedAnimal" :selected="$expense->animal_id"
                                           :placeholder="__('Search by tag or name')" select2 :data-ajax-url="route('animals.search')"
                                           group-class="mb-0" :help="__('Optional — for a cost of one animal (vet bill, medicine, feed).')" />
                        </div>
                    </div>

                    <hr>

                    {{-- Paid now vs credit (unpaid): credit needs a vendor and has no payment mode. --}}
                    <div class="mb-3">
                        <div class="btn-group" role="group" aria-label="{{ __('Paid or credit') }}">
                            <input type="radio" class="btn-check" name="is_credit" id="is_credit_0" value="0" @checked(! $isCredit)>
                            <label class="btn btn-outline-primary" for="is_credit_0"><i class="bi bi-cash-coin me-1"></i>{{ __('Paid') }}</label>
                            <input type="radio" class="btn-check" name="is_credit" id="is_credit_1" value="1" @checked($isCredit)>
                            <label class="btn btn-outline-warning" for="is_credit_1"><i class="bi bi-hourglass-split me-1"></i>{{ __('Credit (pay later)') }}</label>
                        </div>
                        @error('is_credit')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.group :label="__('Vendor')" for="vendor_id" name="vendor_id" class="mb-0"
                                          :help="__('Required for credit. Leave empty and use “Paid to” for one-off payments.')">
                                <div class="input-group flex-nowrap">
                                    <select id="vendor_id" name="vendor_id" class="form-select js-select2 @error('vendor_id') is-invalid @enderror"
                                            data-ajax-url="{{ route('vendors.search') }}" data-placeholder="{{ __('Search vendor') }}">
                                        <option value=""></option>
                                        @foreach ($selectedVendor as $id => $name)
                                            <option value="{{ $id }}" selected>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @can('vendors.manage')
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#vendor-modal"
                                                title="{{ __('Add new vendor') }}"><i class="bi bi-plus-lg"></i></button>
                                    @endcan
                                </div>
                            </x-form.group>
                        </div>
                        <div class="col-12 col-md-6" data-show-when="paid-or-no-vendor">
                            <x-form.input name="paid_to_text" :label="__('Paid to')" :value="$expense->paid_to_text" maxlength="150"
                                          :placeholder="__('e.g. Ramesh (labour)')" group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6" data-show-when="paid">
                            <x-form.select name="payment_mode_id" :label="__('Payment mode')" :options="$paymentModes" :selected="$expense->payment_mode_id"
                                           :placeholder="__('Choose')" group-class="mb-0" />
                        </div>
                    </div>

                    <hr>

                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <x-form.quantity name="quantity" :label="__('Quantity')" :value="$expense->quantity" unit="" group-class="mb-0" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.select name="unit" :label="__('Unit')" :options="$units" :selected="$expense->unit?->value"
                                           :placeholder="__('—')" group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.textarea name="note" :label="__('Note')" :value="$expense->note" rows="1" maxlength="2000" group-class="mb-0" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card">
                <div class="card-header fw-semibold"><i class="bi bi-paperclip me-1"></i>{{ __('Receipt / bill') }}</div>
                <div class="card-body">
                    @if ($expense->receipt_path)
                        <p class="mb-2">
                            <a href="{{ route('expenses.receipt', $expense) }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark me-1"></i>{{ __('View current receipt') }}</a>
                        </p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="remove_receipt" value="1" id="remove_receipt">
                            <label class="form-check-label small" for="remove_receipt">{{ __('Remove the current receipt') }}</label>
                        </div>
                    @endif
                    <x-form.input name="receipt" type="file" accept="image/jpeg,image/png,application/pdf" :label="$expense->receipt_path ? __('Replace with') : null"
                                  :help="__('JPG, PNG or PDF, up to 5 MB. Photos from the phone camera are fine.')" group-class="mb-0" capture="environment" />
                </div>
            </div>
        </div>
    </div>

    @if ($expense->exists)
        <div class="card mt-3">
            <div class="card-body pb-0">
                <x-form.reason :placeholder="__('e.g. Wrong amount entered earlier')" />
            </div>
        </div>
    @endif

    <div class="mt-3 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
        @unless ($expense->exists)
            <button type="submit" name="add_another" value="1" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Save & add another') }}</button>
        @endunless
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>

@can('vendors.manage')
    {{-- Add new vendor without leaving the expense form --}}
    <div class="modal fade" id="vendor-modal" tabindex="-1" aria-labelledby="vendor-modal-title" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="vendor-quick-form" action="{{ route('vendors.quick-store') }}" data-allow-resubmit>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="vendor-modal-title">{{ __('Add new vendor') }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small d-none" data-errors></div>
                    <x-form.input name="name" id="quick_vendor_name" :label="__('Vendor name')" maxlength="150" required />
                    <x-form.input name="phone" id="quick_vendor_phone" type="tel" :label="__('Mobile number')" maxlength="16" inputmode="numeric" group-class="mb-0" />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Add vendor') }}</button>
                </div>
            </form>
        </div>
    </div>
@endcan
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/expense-form.js') }}"></script>
@endpush
