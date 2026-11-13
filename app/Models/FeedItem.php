<?php

namespace App\Models;

use App\Enums\FeedUnit;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A feed the farm keeps in stock (spec Phase 5). Stock is never stored — it is summed from its transactions. */
class FeedItem extends Model
{
    use BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = ['farm_id', 'name', 'unit', 'reorder_level', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unit' => FeedUnit::class, 'reorder_level' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<FeedTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FeedTransaction::class);
    }
}
