<?php

namespace App\Models;

use App\Enums\MilkShift;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One billed delivery: a snapshot of date, shift, litres, rate and amount (spec Section 6). */
class BillItem extends Model
{
    use BelongsToFarm;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'bill_id',
        'delivery_id',
        'product_id',
        'item_date',
        'shift',
        'description',
        'quantity',
        'rate',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_date' => 'date',
            'shift' => MilkShift::class,
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
