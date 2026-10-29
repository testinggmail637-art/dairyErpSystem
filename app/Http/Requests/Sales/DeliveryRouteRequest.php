<?php

namespace App\Http\Requests\Sales;

use App\Models\DeliveryRoute;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit a delivery route: name unique per farm, optional delivery user of this farm, sort order. */
class DeliveryRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('name')) ?? ''),
            'delivery_user_id' => $this->filled('delivery_user_id') ? $this->input('delivery_user_id') : null,
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : 0,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60',
                Rule::unique('routes', 'name')->where('farm_id', app(CurrentFarm::class)->id())->ignore($this->deliveryRoute()?->id)],
            'delivery_user_id' => ['nullable', 'integer', Rule::in(array_keys(DeliveryRoute::deliveryUserOptions()))],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('A route with this name already exists.'),
            'delivery_user_id.in' => __('Choose an active user of this farm who can update deliveries.'),
        ];
    }

    public function deliveryRoute(): ?DeliveryRoute
    {
        $route = $this->route('delivery_route');

        return $route instanceof DeliveryRoute ? $route : null;
    }
}
