<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;

/**
 * Last used number per farm, document type and financial year. Only DocumentNumberService writes here.
 */
class DocumentSequence extends Model
{
    use BelongsToFarm;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'type',
        'financial_year',
        'last_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'last_number' => 'integer',
        ];
    }
}
