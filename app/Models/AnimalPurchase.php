<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Animals bought from a party in one deal; costs are spread over the animals by price (spec Phase 6). */
class AnimalPurchase extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /** @var list<string> */
    protected $fillable = [
        'farm_id', 'purchase_no', 'party_id', 'purchase_date', 'transport_cost', 'commission', 'other_cost',
        'total_animal_price', 'total_amount', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'transport_cost' => 'decimal:2',
            'commission' => 'decimal:2',
            'other_cost' => 'decimal:2',
            'total_animal_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /** @return HasMany<AnimalPurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AnimalPurchaseItem::class);
    }

    /** @return HasMany<AnimalPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(AnimalPayment::class);
    }

    /**
     * Purchase number or party name contains the term.
     *
     * @param  Builder<AnimalPurchase>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(fn (Builder $q) => $q->where('purchase_no', 'like', "%{$term}%")
                ->orWhereHas('party', fn (Builder $p) => $p->where('name', 'like', "%{$term}%")));
        }
    }
}
