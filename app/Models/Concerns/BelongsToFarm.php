<?php

namespace App\Models\Concerns;

use App\Models\Farm;
use App\Models\Scopes\FarmScope;
use App\Support\CurrentFarm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * For every business table (spec Section 4.2): queries see only the current farm's rows,
 * and new rows get farm_id from CurrentFarm when it is not set explicitly.
 *
 * @mixin Model
 */
trait BelongsToFarm
{
    public static function bootBelongsToFarm(): void
    {
        static::addGlobalScope(new FarmScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('farm_id') !== null) {
                return;
            }

            $farmId = app(CurrentFarm::class)->id()
                ?? throw new LogicException('No current farm: cannot create '.$model::class.' without farm_id.');

            $model->setAttribute('farm_id', $farmId);
        });
    }

    /**
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Ignore the current farm, e.g. `Expense::allFarms()->where(...)` in a console command.
     *
     * @return Builder<static>
     */
    public static function allFarms(): Builder
    {
        return static::withoutGlobalScope(FarmScope::class);
    }
}
