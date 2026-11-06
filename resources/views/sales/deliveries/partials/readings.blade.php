{{-- Optional quality readings (dairy plant dispatch): ours and the buyer's. $delivery may be null (new dispatch). --}}
<fieldset class="border rounded p-3 mb-3">
    <legend class="float-none w-auto px-2 fs-6 mb-0">{{ __('Readings (optional, for dairy plants)') }}</legend>
    <div class="row g-3">
        <div class="col-6 col-md-2">
            <x-form.quantity name="fat" :label="__('Our fat')" unit="%" :value="$delivery?->fat" group-class="mb-0" />
        </div>
        <div class="col-6 col-md-2">
            <x-form.quantity name="snf" :label="__('Our SNF')" unit="%" :value="$delivery?->snf" group-class="mb-0" />
        </div>
        <div class="col-12 col-md-4">
            <x-form.quantity name="buyer_measured_qty" :label="__('Buyer\'s litres')" :value="$delivery?->buyer_measured_qty" group-class="mb-0" />
        </div>
        <div class="col-6 col-md-2">
            <x-form.quantity name="buyer_fat" :label="__('Buyer fat')" unit="%" :value="$delivery?->buyer_fat" group-class="mb-0" />
        </div>
        <div class="col-6 col-md-2">
            <x-form.quantity name="buyer_snf" :label="__('Buyer SNF')" unit="%" :value="$delivery?->buyer_snf" group-class="mb-0" />
        </div>
    </div>
    <div class="form-text">{{ __('The buyer\'s readings can be added later from the delivery grid (pencil).') }}</div>
</fieldset>
