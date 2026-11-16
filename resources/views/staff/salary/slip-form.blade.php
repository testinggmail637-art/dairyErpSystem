@extends('layouts.app')

@section('title', __('Salary slip · :name', ['name' => $slip->staffMember->name]))

@section('page-actions')
    <a href="{{ route('salary-runs.show', $slip->salary_run_id) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to salary run') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card mb-3">
            <div class="card-body small">
                {{ $slip->staffMember->name }} · {{ $slip->staffMember->designation }} · {{ $slip->run->label() }}<br>
                {{ __('Paid days') }}: <strong>{{ rtrim(rtrim($slip->payable_days, '0'), '.') }} / {{ $slip->days_in_month }}</strong> ·
                {{ __('Monthly salary') }}: <strong>{{ inr($slip->base_salary) }}</strong> ·
                {{ __('Earned') }}: <strong>{{ inr($slip->earned_salary) }}</strong><br>
                {{ __('Advance still to recover') }}: <strong>{{ inr($outstanding) }}</strong>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('salary-slips.update', $slip) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-12 col-md-4"><x-form.money name="bonus" :label="__('Bonus')" :value="$slip->bonus" /></div>
                        <div class="col-12 col-md-4"><x-form.money name="advance_deduction" :label="__('Advance deduction')" :value="$slip->advance_deduction" /></div>
                        <div class="col-12 col-md-4"><x-form.money name="other_deduction" :label="__('Other deduction')" :value="$slip->other_deduction" /></div>
                    </div>
                    <x-form.input name="note" :label="__('Note')" :value="$slip->note" maxlength="255" :placeholder="__('e.g. Diwali bonus, broken can')" />
                    <x-form.reason :placeholder="__('e.g. Diwali bonus approved')" />
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('salary-runs.show', $slip->salary_run_id) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
