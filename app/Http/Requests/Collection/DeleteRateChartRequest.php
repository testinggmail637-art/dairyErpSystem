<?php

namespace App\Http\Requests\Collection;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Deleting a rate chart needs a reason for the audit log (spec 4.4). */
class DeleteRateChartRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('rate_charts.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
