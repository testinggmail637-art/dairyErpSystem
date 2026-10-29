@extends('layouts.app')

@section('title', __('Products'))

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>{{ __('Add product') }}
        </a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Milk') }}</th>
                    <th class="text-end">{{ __('Default rate') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($canManage)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td class="fw-semibold">{{ $product->name }}</td>
                        <td class="font-monospace text-body-secondary">{{ $product->code }}</td>
                        <td>{{ $product->milk_type->label() }}</td>
                        <td class="text-end text-money">{{ inr($product->default_rate) }} <span class="text-body-secondary small">/ {{ __('litre') }}</span></td>
                        <td>
                            @if ($product->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-end">
                                <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                                </a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 6 : 5 }}" class="text-center text-body-secondary py-5">{{ __('No products yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($products->hasPages())<div class="card-footer">{{ $products->links() }}</div>@endif
</div>
<p class="form-text mt-2">
    {{ __('The default rate is used for customers without their own rate. Changing it never changes past deliveries or bills — they keep the rate of their day.') }}
    {{ __('Products are never deleted — make one inactive to stop selling it.') }}
</p>
@endsection
