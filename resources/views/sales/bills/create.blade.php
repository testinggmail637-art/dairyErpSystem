@extends('layouts.app')

@section('title', __('Generate bills'))

@section('page-actions')
    <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Bills') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('bills.store') }}">
                    @csrf

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="scope" id="scope-all" value="all" @checked(old('scope', $selectedCustomer ? 'one' : 'all') === 'all')>
                            <label class="form-check-label" for="scope-all">{{ __('All customers with unbilled milk in the period') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="scope" id="scope-one" value="one" @checked(old('scope', $selectedCustomer ? 'one' : 'all') === 'one')>
                            <label class="form-check-label" for="scope-one">{{ __('One customer') }}</label>
                        </div>
                    </div>

                    <div id="customer-group">
                        <x-form.select name="customer_id" :label="__('Customer')" :options="$selectedCustomer" :selected="array_key_first($selectedCustomer)"
                                       :placeholder="__('Search by name, code or phone')" select2 :data-ajax-url="route('customers.search')" />
                    </div>

                    <div class="row g-3">
                        <div class="col-6 col-md-4"><x-form.date name="period_from" :label="__('From')" :value="$from" required /></div>
                        <div class="col-6 col-md-4"><x-form.date name="period_to" :label="__('To')" :value="$to" max="today" required /></div>
                        <div class="col-12 col-md-4"><x-form.date name="bill_date" :label="__('Bill date')" :value="$to" required
                                                                   :help="__('Usually the last day of the period.')" /></div>
                    </div>

                    <p class="form-text">{{ __('Each bill starts as a draft with every delivered, not yet billed delivery of the period. Check the drafts, then finalize them. A customer cannot have two bills for the same days.') }}</p>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-receipt me-1"></i>{{ __('Make draft bills') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        const toggle = () => $('#customer-group').toggleClass('d-none', !$('#scope-one').is(':checked'));
        $('input[name="scope"]').on('change', toggle);
        toggle();
    });
</script>
@endpush
