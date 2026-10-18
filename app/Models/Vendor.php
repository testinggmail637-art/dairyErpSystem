<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use App\Support\Money;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A supplier (feed, medicine, diesel …). Credit expenses add to what we owe; vendor payments reduce it (Task 1.5).
 */
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use BelongsToFarm, HasFactory, SoftDeletes, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'name',
        'phone',
        'address',
        'gstin',
        'opening_balance',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<VendorPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    /**
     * Adds credit_total (unpaid expenses) and paid_total (payments) per vendor as two SQL sub-selects,
     * so a list of vendors shows balances without loading any expense or payment.
     *
     * @param  Builder<Vendor>  $query
     */
    public function scopeWithBalance(Builder $query): void
    {
        $query->withSum(['expenses as credit_total' => fn ($q) => $q->where('is_credit', true)], 'amount')
            ->withSum('payments as paid_total', 'amount');
    }

    /** What we owe now: opening + credit expenses − payments (needs withBalance()). Paid expenses do not count. */
    public function balance(): string
    {
        return Money::sub(Money::add($this->opening_balance, $this->credit_total ?? '0'), $this->paid_total ?? '0');
    }

    protected function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = User::normalizePhone($value);
    }

    /**
     * @param  Builder<Vendor>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Name starts with / contains the term, or phone matches. Name prefix uses the (farm_id, name) index.
     *
     * @param  Builder<Vendor>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%");

            if (strlen($digits) >= 3) {
                $q->orWhere('phone', 'like', '%'.(User::normalizePhone($digits) ?: $digits).'%');
            }
        });
    }
}
