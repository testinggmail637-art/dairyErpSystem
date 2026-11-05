<?php

namespace App\Http\Requests\Sales;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Pause a subscription for a date range (both days included); pauses of one subscription never overlap. */
class SubscriptionPauseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('subscriptions.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Subscription $subscription */
            $subscription = $this->route('subscription');

            if ($validator->errors()->isEmpty()
                && app(SubscriptionService::class)->pauseOverlaps($subscription, $this->input('from_date'), $this->input('to_date'))) {
                $validator->errors()->add('from_date', __('These dates overlap another pause of this subscription.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['to_date.after_or_equal' => __('The last day cannot be before the first day.')];
    }
}
