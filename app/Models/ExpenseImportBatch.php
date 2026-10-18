<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** One uploaded expense spreadsheet (spec 7.2). */
class ExpenseImportBatch extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'original_filename',
        'stored_path',
        'total_rows',
        'valid_rows',
        'error_rows',
        'warning_rows',
        'imported_rows',
        'status',
        'error_file_path',
        'imported_at',
        'reverted_at',
        'reverted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'imported_at' => 'datetime',
            'reverted_at' => 'datetime',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'error_rows' => 'integer',
            'warning_rows' => 'integer',
            'imported_rows' => 'integer',
        ];
    }

    /**
     * @return HasMany<ExpenseImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ExpenseImportRow::class, 'import_batch_id');
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'import_batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function isPreviewed(): bool
    {
        return $this->status === ImportBatchStatus::Previewed;
    }

    public function isImported(): bool
    {
        return $this->status === ImportBatchStatus::Imported;
    }

    /** Last moment the import may be reverted: imported_at + config('dairy.imports.revert_days') days. */
    public function revertDeadline(): ?Carbon
    {
        return $this->imported_at?->copy()->addDays((int) config('dairy.imports.revert_days'));
    }

    /** Imported and still inside the revert window (the "not edited" check runs when reverting). */
    public function canBeReverted(): bool
    {
        return $this->isImported() && (bool) $this->revertDeadline()?->isFuture();
    }
}
