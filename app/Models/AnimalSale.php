<?php

namespace App\Models;

use App\Enums\AnimalSaleStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Animals sold to a party in one deal (spec Phase 6). Never edited or deleted — cancelled with a reason. */
class AnimalSale extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /** @var list<string> */
    protected $fillable = [
        'farm_id', 'sale_no', 'party_id', 'sale_date', 'total_price', 'selling_costs', 'net_amount', 'notes',
        'status', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'total_price' => 'decimal:2',
            'selling_costs' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'status' => AnimalSaleStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /** @return HasMany<AnimalSaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AnimalSaleItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === AnimalSaleStatus::Cancelled;
    }

    /** @return HasMany<AnimalPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(AnimalPayment::class);
    }

    /**
     * Sale number or party name contains the term.
     *
     * @param  Builder<AnimalSale>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(fn (Builder $q) => $q->where('sale_no', 'like', "%{$term}%")
                ->orWhereHas('party', fn (Builder $p) => $p->where('name', 'like', "%{$term}%")));
        }
    }
}
