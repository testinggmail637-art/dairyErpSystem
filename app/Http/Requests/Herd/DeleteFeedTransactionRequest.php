<?php

namespace App\Http\Requests\Herd;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Deleting a feed entry (a purchase also deletes its expense) needs a reason for the audit log. */
class DeleteFeedTransactionRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('feed.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
