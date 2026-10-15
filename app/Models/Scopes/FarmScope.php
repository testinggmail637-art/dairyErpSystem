<?php

namespace App\Models\Scopes;

use App\Support\CurrentFarm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits queries to the current farm. When no farm is resolved (console without CurrentFarm::set(),
 * guest requests) no filter is added; every app route runs EnsureFarmSelected, so HTTP requests always have one.
 */
class FarmScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $farmId = app(CurrentFarm::class)->id();

        if ($farmId !== null) {
            $builder->where($model->qualifyColumn('farm_id'), $farmId);
        }
    }
}
