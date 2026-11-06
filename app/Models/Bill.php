<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\MilkShift;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use App\Support\Money;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer bill (spec Section 6, Phase 3): draft → finalized (numbered, balances snapshotted, PDF, locked)
 * or cancelled (deliveries released).
 */
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use Auditable, BelongsToFarm, HasFactory, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'bill_no',
        'customer_id',
        'period_from',
        'period_to',
        'bill_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total',
        'previous_balance',
        'payments_in_period',
        'adjustments_in_period',
        'closing_balance',
        'status',
        'finalized_at',
        'finalized_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'pdf_path',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'bill_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'previous_balance' => 'decimal:2',
            'payments_in_period' => 'decimal:2',
            'adjustments_in_period' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'status' => BillStatus::class,
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<BillItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === BillStatus::Draft;
    }

    /** Draft number for screens: bill_no once finalized, "Draft #12" before. */
    public function label(): string
    {
        return $this->bill_no ?? __('Draft #:id', ['id' => $this->id]);
    }

    /**
     * Day-wise table for the bill (spec: date, morning qty, evening qty, rate, amount), one row per date + product +
     * rate, from the (already loaded) items.
     *
     * @return list<array{date: string, product: string, morning: string, evening: string, rate: string, amount: string}>
     */
    public function dayRows(): array
    {
        $rows = [];

        foreach ($this->items->sortBy([['item_date', 'asc'], ['product_id', 'asc']]) as $item) {
            $key = $item->item_date->toDateString().'|'.$item->product_id.'|'.$item->rate;
            $rows[$key] ??= ['date' => $item->item_date->toDateString(), 'product' => $item->product->name,
                'morning' => '0.00', 'evening' => '0.00', 'rate' => $item->rate, 'amount' => '0.00'];

            $col = $item->shift === MilkShift::Evening ? 'evening' : 'morning';
            $rows[$key][$col] = Money::add($rows[$key][$col], $item->quantity);
            $rows[$key]['amount'] = Money::add($rows[$key]['amount'], $item->amount);
        }

        return array_values($rows);
    }
}
