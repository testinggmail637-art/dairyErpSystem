<?php

namespace App\Http\Requests\Collection;

use App\Http\Requests\Concerns\RequiresReason;
use App\Models\MilkCollection;

/** Change a collection (farmer stays): same checks as a new entry, plus a reason for the audit log (spec 4.4, 4.5). */
class CollectionUpdateRequest extends CollectionRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->collection());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['farmer_id'], $rules['client_uuid']);

        return $this->reasonRules() + $rules;
    }

    public function collection(): MilkCollection
    {
        return $this->route('collection');
    }
}
