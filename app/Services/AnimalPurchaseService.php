<?php

namespace App\Services;

use App\Enums\AnimalSource;
use App\Enums\AnimalStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentDirection;
use App\Models\Animal;
use App\Models\AnimalPurchase;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Animal purchases (spec Phase 6): animals are created inline, purchase costs are spread over them by price. */
class AnimalPurchaseService
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    /**
     * Share of `$costs` for each price, proportional to the price; the last item absorbs the rounding.
     *
     * @param  list<string>  $prices
     * @return list<string>
     */
    public static function allocate(string $costs, array $prices): array
    {
        $total = Money::sum($prices);
        $shares = [];

        foreach (array_slice($prices, 0, -1) as $price) {
            $shares[] = Money::isZero($total) ? '0.00' : Money::round2(bcdiv(bcmul(Money::normalize($costs), Money::normalize($price), 8), $total, 8));
        }

        $shares[] = Money::sub($costs, Money::sum($shares));

        return $shares;
    }

    /**
     * @param  array<string, mixed>  $data  validated purchase fields + `animals` rows
     */
    public function create(array $data): AnimalPurchase
    {
        $date = Carbon::parse($data['purchase_date']);
        $costs = Money::add($data['transport_cost'], $data['commission'], $data['other_cost']);
        $prices = array_map(fn ($row) => Money::normalize($row['price']), $data['animals']);
        $shares = self::allocate($costs, $prices);
        $totalPrice = Money::sum($prices);

        return DB::transaction(function () use ($data, $date, $costs, $prices, $shares, $totalPrice) {
            $purchase = AnimalPurchase::create([
                'purchase_no' => $this->numbers->next(DocumentType::AnimalPurchase, $date),
                'party_id' => $data['party_id'],
                'purchase_date' => $date->toDateString(),
                'transport_cost' => $data['transport_cost'],
                'commission' => $data['commission'],
                'other_cost' => $data['other_cost'],
                'total_animal_price' => $totalPrice,
                'total_amount' => Money::add($totalPrice, $costs),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach (array_values($data['animals']) as $i => $row) {
                $animal = new Animal([
                    'tag_no' => $row['tag_no'],
                    'name' => $row['name'] ?? null,
                    'animal_type' => $row['animal_type'],
                    'breed_id' => $row['breed_id'] ?? null,
                    'gender' => $row['gender'],
                    // approx age in months → estimated date of birth
                    'date_of_birth' => isset($row['age_months']) ? $date->copy()->subMonths((int) $row['age_months'])->toDateString() : null,
                    'dob_is_estimated' => isset($row['age_months']),
                    'source' => AnimalSource::Purchased,
                    'ownership_type' => $row['ownership_type'],
                    'lifecycle_stage' => $row['lifecycle_stage'],
                    'status' => AnimalStatus::Active,
                    'lactation_number' => 0,
                ]);
                $animal->changeDate = $date->toDateString();
                $animal->save();

                $purchase->items()->create([
                    'animal_id' => $animal->id,
                    'price' => $prices[$i],
                    'allocated_cost' => $shares[$i],
                    'total_cost' => Money::add($prices[$i], $shares[$i]),
                ]);
            }

            if (($data['initial_payment'] ?? null) !== null) {
                $purchase->payments()->create([
                    'party_id' => $purchase->party_id,
                    'direction' => PaymentDirection::Paid,
                    'payment_date' => $purchase->purchase_date->toDateString(),
                    'amount' => $data['initial_payment'],
                    'payment_mode_id' => $data['initial_payment_mode_id'],
                    'reference_no' => $data['initial_reference_no'] ?? null,
                ]);
            }

            return $purchase;
        });
    }

    /**
     * Removes a wrongly entered purchase and soft-deletes its animals — only while none of them has been used
     * anywhere else (still active, no milk / health / feed / expense records).
     */
    public function delete(AnimalPurchase $purchase): void
    {
        $purchase->loadMissing('items.animal');

        if ($purchase->payments()->exists()) {
            throw new DomainException(__('This purchase has payments. Delete its payments first.'));
        }

        foreach ($purchase->items as $item) {
            $animal = $item->animal;
            $counts = array_diff_key($animal->transactionCounts(), [__('purchases') => true]);

            if ($animal->trashed() || $animal->status !== AnimalStatus::Active || $counts !== []) {
                throw new DomainException(__('This purchase cannot be deleted because animal :tag is no longer active or already has records.', ['tag' => $animal->tag_no]));
            }
        }

        DB::transaction(function () use ($purchase) {
            $animals = $purchase->items->pluck('animal');
            $purchase->items()->delete();
            $purchase->delete();
            $animals->each->delete();
        });
    }
}
