<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One spreadsheet row of an import batch, with its errors (blocking) and warnings (not blocking). */
class ExpenseImportRow extends Model
{
    use BelongsToFarm;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'import_batch_id',
        'row_number',
        'payload',
        'errors',
        'warnings',
        'is_valid',
        'expense_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'is_valid' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ExpenseImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ExpenseImportBatch::class, 'import_batch_id');
    }

    /** Messages for one column, e.g. errorsFor('category'). */
    public function errorsFor(string $column): array
    {
        return $this->errors[$column] ?? [];
    }
}
