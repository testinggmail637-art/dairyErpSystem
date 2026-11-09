@extends('layouts.app')

@section('title', __('Statement of :code', ['code' => $statement->farmer->code]))

@section('page-actions')
    <a href="{{ route('farmer-payments.show', $statement->farmer_payment_cycle_id) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ $statement->cycle->cycle_no }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-7 col-xl-5">
        <div class="card">
            <div class="card-body">
                <p><span class="font-monospace">{{ $statement->farmer->code }}</span> <strong>{{ $statement->farmer->name }}</strong><br>
                    <span class="small text-body-secondary">{{ fmt_date($statement->cycle->from_date) }} – {{ fmt_date($statement->cycle->to_date) }} ·
                        {{ fmt_qty($statement->total_qty) }} L · {{ __('gross') }} {{ inr($statement->gross_amount) }}</span></p>

                <form method="POST" action="{{ route('farmer-statements.update', $statement) }}">
                    @csrf
                    @method('PUT')
                    <x-form.money name="bonus" :label="__('Bonus')" :value="$statement->bonus" />
                    <x-form.money name="advance_deduction" :label="__('Advance deduction')" :value="$statement->advance_deduction"
                                  :help="__('Advance still to recover: :amount', ['amount' => inr($outstandingAdvance)])" />
                    <x-form.money name="other_deduction" :label="__('Other deduction')" :value="$statement->other_deduction" />
                    <x-form.input name="deduction_note" :label="__('What the other deduction is for')" :value="$statement->deduction_note" maxlength="255" :placeholder="__('e.g. Cattle feed bag')" />
                    <p class="small text-body-secondary">{{ __('Net = gross + bonus − advance − other. It cannot go below ₹0.') }}</p>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
