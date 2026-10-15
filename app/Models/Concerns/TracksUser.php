<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fills created_by / updated_by from the logged-in user (spec Section 4.3).
 * Without a logged-in user (seeders, console) the columns are left as they are.
 *
 * @mixin Model
 */
trait TracksUser
{
    public static function bootTracksUser(): void
    {
        static::creating(function (Model $model) {
            $userId = auth()->id();

            if ($userId === null) {
                return;
            }

            if ($model->getAttribute('created_by') === null) {
                $model->setAttribute('created_by', $userId);
            }

            if ($model->getAttribute('updated_by') === null) {
                $model->setAttribute('updated_by', $userId);
            }
        });

        static::updating(function (Model $model) {
            if (auth()->id() !== null) {
                $model->setAttribute('updated_by', auth()->id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
