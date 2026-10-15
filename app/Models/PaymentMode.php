<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\HasFarmDefaults;
use App\Models\Concerns\TracksUser;
use Database\Factories\PaymentModeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * How money was paid or received (Cash, UPI, Bank Transfer, …).
 */
class PaymentMode extends Model
{
    /** @use HasFactory<PaymentModeFactory> */
    use BelongsToFarm, HasFactory, HasFarmDefaults, TracksUser;

    /** Seeded for every farm: code => name. */
    public const DEFAULTS = [
        'CASH' => 'Cash',
        'UPI' => 'UPI',
        'BANK' => 'Bank Transfer',
        'CHEQUE' => 'Cheque',
        'CARD' => 'Card',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'name',
        'code',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value === null ? null : strtoupper(trim($value));
    }

    /**
     * @param  Builder<PaymentMode>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
