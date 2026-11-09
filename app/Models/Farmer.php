<?php

namespace App\Models;

use App\Enums\MilkType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\FarmerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A farmer who sells milk to our collection center (spec Phase 4). Opening balance + = we owe the farmer. */
class Farmer extends Model
{
    /** @use HasFactory<FarmerFactory> */
    use Auditable, BelongsToFarm, HasFactory, SoftDeletes, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'code',
        'name',
        'father_name',
        'phone',
        'village',
        'address',
        'default_milk_type',
        'rate_chart_id',
        'bank_account_name',
        'bank_account_no',
        'ifsc',
        'upi_id',
        'opening_balance',
        'is_active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_milk_type' => MilkType::class,
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RateChart, $this> own rate chart (overrides the default chart of the milk type)
     */
    public function rateChart(): BelongsTo
    {
        return $this->belongsTo(RateChart::class)->withTrashed();
    }

    /**
     * @return HasMany<FarmerAdvance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(FarmerAdvance::class);
    }

    protected function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = User::normalizePhone($value);
    }

    /**
     * @param  Builder<Farmer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Name, village, exact code (F-7 / f-007 / 7) or phone.
     *
     * @param  Builder<Farmer>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%")->orWhere('village', 'like', "{$term}%")->orWhere('code', strtoupper($term));

            if ($digits !== '' && strlen($digits) <= 6) {
                $q->orWhere('code', self::codeFor((int) $digits));
            }
            if (strlen($digits) >= 3) {
                $q->orWhere('phone', 'like', '%'.(User::normalizePhone($digits) ?: $digits).'%');
            }
        });
    }

    public static function codeFor(int $number): string
    {
        return sprintf('F-%03d', $number);
    }
}
