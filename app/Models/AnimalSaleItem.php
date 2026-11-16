<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One animal of a sale and its price (snapshot). */
class AnimalSaleItem extends Model
{
    /** @var list<string> */
    protected $fillable = ['animal_sale_id', 'animal_id', 'price'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    /** @return BelongsTo<AnimalSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(AnimalSale::class, 'animal_sale_id');
    }

    /** @return BelongsTo<Animal, $this> */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class)->withTrashed();
    }
}
