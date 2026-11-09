<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;

/** One row of a slab rate chart: fat (and optional SNF) range → rate per litre. */
class RateChartSlab extends Model
{
    use BelongsToFarm;

    /**
     * @var list<string>
     */
    protected $fillable = ['farm_id', 'rate_chart_id', 'fat_from', 'fat_to', 'snf_from', 'snf_to', 'rate_per_litre'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fat_from' => 'decimal:2',
            'fat_to' => 'decimal:2',
            'snf_from' => 'decimal:2',
            'snf_to' => 'decimal:2',
            'rate_per_litre' => 'decimal:2',
        ];
    }
}
