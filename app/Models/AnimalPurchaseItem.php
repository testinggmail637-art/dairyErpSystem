<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One animal of a purchase: its price, its share of the purchase costs and the total (snapshot). */
class AnimalPurchaseItem extends Model
{
    /** @var list<string> */
    protected $fillable = ['animal_purchase_id', 'animal_id', 'price', 'allocated_cost', 'total_cost'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'allocated_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<AnimalPurchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(AnimalPurchase::class, 'animal_purchase_id');
    }

    /** @return BelongsTo<Animal, $this> */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class)->withTrashed();
    }
}
