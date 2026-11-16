<?php

namespace App\Models;

use App\Enums\SalaryRunStatus;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One month's salary for all staff (spec Phase 6). */
class SalaryRun extends Model
{
    use BelongsToFarm, TracksUser;

    /** @var list<string> */
    protected $fillable = ['farm_id', 'run_no', 'month', 'status', 'finalized_at', 'finalized_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'status' => SalaryRunStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    /** @return HasMany<SalarySlip, $this> */
    public function slips(): HasMany
    {
        return $this->hasMany(SalarySlip::class);
    }

    /** @return BelongsTo<User, $this> */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isFinalized(): bool
    {
        return $this->status === SalaryRunStatus::Finalized;
    }

    public function label(): string
    {
        return $this->month->translatedFormat('F Y');
    }
}
