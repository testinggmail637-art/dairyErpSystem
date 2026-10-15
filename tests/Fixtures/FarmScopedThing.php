<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only business model on a TEMPORARY table (no implicit commit, so RefreshDatabase still rolls back).
 */
class FarmScopedThing extends Model
{
    use BelongsToFarm, TracksUser;

    protected $table = 'farm_scoped_things';

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create('farm_scoped_things', function (Blueprint $table) {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('farm_id');
            $table->string('name');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }
}
