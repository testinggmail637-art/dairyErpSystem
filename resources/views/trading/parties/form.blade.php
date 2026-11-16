@extends('layouts.app')

@section('title', $party->exists ? __('Edit party') : __('Add party'))

@section('page-actions')
    <a href="{{ route('parties.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to parties') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $party->exists ? route('parties.update', $party) : route('parties.store') }}">
                    @csrf
                    @if ($party->exists) @method('PUT') @endif

                    <x-form.input name="name" :label="__('Party name')" :value="$party->name" maxlength="150" required autofocus />

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.input name="phone" type="tel" :label="__('Mobile number')" :value="$party->phone" maxlength="16" inputmode="numeric" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.input name="village" :label="__('Village')" :value="$party->village" maxlength="100" />
                        </div>
                    </div>

                    <x-form.textarea name="address" :label="__('Address')" :value="$party->address" rows="2" maxlength="500" />

                    <x-form.select name="party_type" :label="__('Party type')" :options="\App\Enums\PartyType::options()" :selected="$party->party_type?->value"
                                   required :help="__('Seller: we buy animals from them. Buyer: we sell animals to them.')" />

                    <x-form.money name="opening_balance" :label="__('Opening balance (they owe us)')" :value="$party->opening_balance"
                                  allow-negative :help="__('What this party owed us when we started using the software. Use a minus amount if we owed them.')" />

                    <x-form.switch name="is_active" :label="__('Active (can be chosen for new purchases and sales)')" :checked="$party->is_active" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('parties.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
