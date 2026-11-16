<?php

namespace App\Models;

use App\Enums\PartyType;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An animal trader we buy animals from and / or sell animals to (spec Phase 6).
 * Opening balance: + = they owe us, − = we owe them. Purchases, sales and payments arrive in Tasks 6.2–6.4.
 */
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use BelongsToFarm, HasFactory, SoftDeletes, TracksUser;

    /** @var list<string> */
    protected $fillable = ['farm_id', 'name', 'phone', 'village', 'address', 'party_type', 'opening_balance', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'party_type' => PartyType::class,
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Name or village contains the term, or phone matches.
     *
     * @param  Builder<Party>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%")->orWhere('village', 'like', "%{$term}%");

            if (strlen($digits) >= 3) {
                $q->orWhere('phone', 'like', '%'.(User::normalizePhone($digits) ?: $digits).'%');
            }
        });
    }
}
