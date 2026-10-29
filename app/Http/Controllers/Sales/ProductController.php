<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sales → Products (spec Section 6, Phase 3): raw cow / buffalo milk with a default rate per litre. Only a few rows,
 * so no filters (paged anyway). No delete — a product is deactivated so customer rates, deliveries and bills keep it.
 */
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        return view('sales.products.index', [
            'products' => Product::query()->orderByDesc('is_active')->orderByRaw("FIELD(milk_type, 'cow', 'buffalo')")->orderBy('name')->paginate(50),
            'canManage' => $request->user()->can('customers.manage'),
        ]);
    }

    public function create(): View
    {
        return view('sales.products.form', ['product' => new Product(['is_active' => true])]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        return redirect()->route('products.index')
            ->with('success', __('Product ":name" added.', ['name' => $product->name]));
    }

    public function edit(Product $product): View
    {
        return view('sales.products.form', ['product' => $product]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index')
            ->with('success', __('Product ":name" updated.', ['name' => $product->name]));
    }
}
