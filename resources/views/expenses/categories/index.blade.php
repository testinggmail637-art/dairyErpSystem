@extends('layouts.app')

@section('title', __('Expense categories'))

@php($canEdit = auth()->user()->can('expenses.edit'))

@section('page-actions')
    @if ($canEdit)
        <a href="{{ route('expense-categories.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add category') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom d-flex flex-wrap gap-2 align-items-center">
        <input type="search" id="category-filter" class="form-control form-control-sm app-input-narrow"
               placeholder="{{ __('Find a category or subcategory') }}" aria-label="{{ __('Search') }}">
        <span class="small text-body-secondary ms-auto">
            {{ trans_choice(':count category|:count categories', $categories->count()) }} ·
            {{ trans_choice(':count subcategory|:count subcategories', $categories->sum(fn ($c) => $c->children->count())) }}
        </span>
    </div>

    <ul class="list-group list-group-flush" id="category-tree">
        @forelse ($categories as $category)
            <li class="list-group-item p-0" data-category>
                <div class="d-flex align-items-center gap-2 px-3 py-2 app-tree-parent">
                    <i class="bi bi-folder2-open text-primary"></i>
                    <span class="fw-semibold" data-name>{{ $category->name }}</span>
                    @if ($category->code)<code class="small">{{ $category->code }}</code>@endif
                    @unless ($category->is_active)<span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless
                    <span class="badge rounded-pill text-bg-light border">{{ $category->children->count() }}</span>

                    @if ($canEdit)
                        <div class="ms-auto d-flex gap-1">
                            <a href="{{ route('expense-categories.create', ['parent' => $category->id]) }}" class="btn btn-outline-primary btn-sm"
                               title="{{ __('Add subcategory') }}"><i class="bi bi-plus-lg"></i><span class="d-none d-md-inline ms-1">{{ __('Subcategory') }}</span></a>
                            <a href="{{ route('expense-categories.edit', $category) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @if ($category->children->isEmpty())
                                <x-confirm-form :action="route('expense-categories.destroy', $category)"
                                                :message="__('Delete the category :name?', ['name' => $category->name])" />
                            @endif
                        </div>
                    @endif
                </div>

                @if ($category->children->isNotEmpty())
                    <ul class="list-group list-group-flush border-top">
                        @foreach ($category->children as $sub)
                            <li class="list-group-item d-flex align-items-center gap-2 ps-5 py-1" data-sub>
                                <i class="bi bi-dot"></i>
                                <span data-name class="{{ $sub->is_active ? '' : 'text-body-secondary text-decoration-line-through' }}">{{ $sub->name }}</span>
                                @if ($sub->code)<code class="small">{{ $sub->code }}</code>@endif
                                @unless ($sub->is_active)<span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless
                                @if ($canEdit)
                                    <div class="ms-auto d-flex gap-1">
                                        <a href="{{ route('expense-categories.edit', $sub) }}" class="btn btn-link btn-sm py-0" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                        <x-confirm-form :action="route('expense-categories.destroy', $sub)" button-class="btn btn-link btn-sm py-0 text-danger"
                                                        :message="__('Delete the subcategory :name?', ['name' => $sub->name])" />
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @empty
            <li class="list-group-item text-center text-body-secondary py-5">
                <i class="bi bi-folder2 fs-2 d-block mb-2"></i>
                {{ __('No expense categories yet.') }}
                @if ($canEdit)
                    <div class="mt-2"><a href="{{ route('expense-categories.create') }}" class="btn btn-primary btn-sm">{{ __('Add first category') }}</a></div>
                @endif
            </li>
        @endforelse
    </ul>
    <div class="card-body py-2 small text-body-secondary d-none" id="category-filter-empty">{{ __('Nothing matches your search.') }}</div>
</div>
<p class="form-text mt-2">{{ __('Used categories cannot be deleted — deactivate them to hide them from new expenses. Old expenses keep their category.') }}</p>
@endsection

@push('scripts')
<script>
    // Quick filter: keep categories whose name or any subcategory matches; the tree is small, so this runs in the browser.
    $('#category-filter').on('input', function () {
        const term = this.value.trim().toLowerCase();
        let shown = 0;
        $('#category-tree [data-category]').each(function () {
            const $cat = $(this);
            const catMatch = $cat.find('> div [data-name]').text().toLowerCase().includes(term);
            let subMatch = false;
            $cat.find('[data-sub]').each(function () {
                const match = !term || catMatch || $(this).find('[data-name]').text().toLowerCase().includes(term);
                $(this).toggleClass('d-none', !match);
                subMatch = subMatch || match;
            });
            const visible = !term || catMatch || subMatch;
            $cat.toggleClass('d-none', !visible);
            shown += visible ? 1 : 0;
        });
        $('#category-filter-empty').toggleClass('d-none', shown > 0);
    });
</script>
@endpush
