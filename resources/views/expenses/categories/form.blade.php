@extends('layouts.app')

@section('title', $category->exists
    ? ($category->isTopLevel() ? __('Edit category') : __('Edit subcategory'))
    : ($category->parent_id ? __('Add subcategory') : __('Add category')))

@section('page-actions')
    <a href="{{ route('expense-categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to categories') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $category->exists ? route('expense-categories.update', $category) : route('expense-categories.store') }}">
                    @csrf
                    @if ($category->exists) @method('PUT') @endif

                    <x-form.select name="parent_id" :label="__('Main category')" :options="$parents" :selected="$category->parent_id"
                                   :placeholder="__('— None: this is a main category —')"
                                   :help="__('Choose a main category to make this a subcategory.')" />

                    <x-form.input name="name" :label="__('Name')" :value="$category->name" maxlength="100" required autofocus />

                    <div class="row g-3">
                        <div class="col-6">
                            <x-form.input name="code" :label="__('Code')" :value="$category->code" maxlength="20"
                                          class="text-uppercase" :help="__('Optional, e.g. FEED.')" />
                        </div>
                        <div class="col-6">
                            <x-form.input name="sort_order" type="number" :label="__('Order')" :value="$category->sort_order ?? 0"
                                          min="0" max="9999" step="1" :help="__('Smaller numbers show first.')" />
                        </div>
                    </div>

                    <x-form.switch name="is_active" :label="__('Active (can be chosen for new expenses)')" :checked="$category->is_active" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('expense-categories.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
