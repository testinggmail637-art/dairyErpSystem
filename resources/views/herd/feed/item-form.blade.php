@extends('layouts.app')

@section('title', $item->exists ? __('Edit feed item') : __('Add feed item'))

@section('page-actions')
    <a href="{{ route('feed.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Feed stock') }}</a>
    @if ($item->exists)
        <x-confirm-form :action="route('feed.items.destroy', $item)" :label="__('Delete')" :message="__('Delete :name?', ['name' => $item->name])" />
    @endif
@endsection

@section('content')
<div class="card" style="max-width: 560px;">
    <div class="card-body">
        <form method="POST" action="{{ $item->exists ? route('feed.items.update', $item) : route('feed.items.store') }}" autocomplete="off">
            @csrf
            @if ($item->exists) @method('PUT') @endif
            <x-form.input name="name" :label="__('Name')" :value="$item->name" maxlength="100" required :placeholder="__('e.g. Cattle feed (Godrej), Bhusa')" />
            <div class="row g-2">
                <div class="col-6">
                    <x-form.select name="unit" :label="__('Unit')" :options="\App\Enums\FeedUnit::options()" :selected="$item->unit?->value" required />
                </div>
                <div class="col-6">
                    <x-form.quantity name="reorder_level" :label="__('Reorder level')" :value="$item->reorder_level" :unit="$item->unit?->label() ?? ''"
                                     :help="__('Shown as low (and reminded) at or below this stock. 0 = no alert.')" />
                </div>
            </div>
            <x-form.switch name="is_active" :label="__('Active')" :checked="$item->is_active" />
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
        </form>
    </div>
</div>
@endsection
