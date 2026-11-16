<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A farm worker on a monthly salary (spec Phase 6). Salary changes are audit-logged with a reason. */
class StaffMember extends Model
{
    /** @use HasFactory<StaffMemberFactory> */
    use Auditable, BelongsToFarm, HasFactory, TracksUser;

    /** @var list<string> */
    protected $fillable = ['farm_id', 'user_id', 'name', 'phone', 'designation', 'monthly_salary', 'joining_date', 'leaving_date', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'joining_date' => 'date',
            'leaving_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Name or designation contains the term, or phone matches.
     *
     * @param  Builder<StaffMember>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%")->orWhere('designation', 'like', "%{$term}%");

            if (strlen($digits) >= 3) {
                $q->orWhere('phone', 'like', '%'.(User::normalizePhone($digits) ?: $digits).'%');
            }
        });
    }
}
