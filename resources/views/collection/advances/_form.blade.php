{{-- Give an advance; on a farmer page the farmer is fixed ($farmerId), else chosen by search. --}}
<form method="POST" action="{{ route('farmer-advances.store') }}" autocomplete="off">
    @csrf
    <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>
    @if ($farmerId)
        <input type="hidden" name="farmer_id" value="{{ $farmerId }}">
    @else
        <x-form.select name="farmer_id" :label="__('Farmer')" :options="$farmerOptions" :selected="old('farmer_id')"
                       :placeholder="__('Search code or name')" select2 :data-ajax-url="route('farmers.search')" required />
    @endif
    <div class="row g-2">
        <div class="col-6"><x-form.date name="advance_date" :label="__('Date')" :value="today()" max="today" required /></div>
        <div class="col-6"><x-form.money name="amount" :label="__('Amount')" required /></div>
        <div class="col-6"><x-form.select name="payment_mode_id" :label="__('Paid by')" :options="$paymentModes" :placeholder="__('Choose')" required /></div>
        <div class="col-6"><x-form.input name="reference_no" :label="__('Reference')" maxlength="50" /></div>
    </div>
    <x-form.input name="note" :label="__('Note')" maxlength="500" :placeholder="__('e.g. For cattle feed')" />
    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save advance') }}</button>
    <div class="form-text">{{ __('Deducted from the farmer\'s next payment statement.') }}</div>
</form>
