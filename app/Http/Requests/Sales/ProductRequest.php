<?php

namespace App\Http\Requests\Sales;

use App\Enums\MilkType;
use App\Models\Product;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit a product: name and code unique per farm, milk type, default rate per litre (> 0, 2 decimals). */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('name')) ?? ''),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'default_rate' => str_replace([',', ' ', '₹'], '', trim((string) $this->input('default_rate'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unique = fn (string $column) => Rule::unique('products', $column)
            ->where('farm_id', app(CurrentFarm::class)->id())
            ->ignore($this->product()?->id);

        return [
            'name' => ['required', 'string', 'max:60', $unique('name')],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/', $unique('code')],
            'milk_type' => ['required', Rule::enum(MilkType::class)],
            'default_rate' => ['required', 'decimal:0,2', 'gt:0', 'max:9999.99'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('A product with this name already exists.'),
            'code.unique' => __('A product with this code already exists.'),
            'code.regex' => __('Use letters, digits and - only, e.g. COW or A2-COW.'),
            'default_rate.decimal' => __('Enter the rate in rupees, up to 2 decimals, e.g. 62.50.'),
        ];
    }

    public function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}
