<?php

namespace App\Services;

use App\Enums\AnimalSaleStatus;
use App\Enums\AnimalStatus;
use App\Enums\DocumentType;
use App\Models\Animal;
use App\Models\AnimalSale;
use App\Support\AuditReason;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Animal sales (spec Phase 6): only active animals; saving marks them sold, cancelling makes them active again. */
class AnimalSaleService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly AnimalService $animals,
        private readonly AuditReason $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated sale fields + `animals` rows (animal_id, price)
     */
    public function create(array $data): AnimalSale
    {
        $date = Carbon::parse($data['sale_date']);
        $prices = array_map(fn ($row) => Money::normalize($row['price']), $data['animals']);
        $total = Money::sum($prices);

        return DB::transaction(function () use ($data, $date, $prices, $total) {
            // lock the animals so two sales cannot sell the same animal at once
            $animals = Animal::query()->whereKey(array_column($data['animals'], 'animal_id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($data['animals'] as $row) {
                $animal = $animals->get($row['animal_id']);

                if (! $animal || $animal->status !== AnimalStatus::Active) {
                    throw new DomainException(__('Animal :tag is not active and cannot be sold.', ['tag' => $animal?->tag_no ?? $row['animal_id']]));
                }
            }

            $sale = AnimalSale::create([
                'sale_no' => $this->numbers->next(DocumentType::AnimalSale, $date),
                'party_id' => $data['party_id'],
                'sale_date' => $date->toDateString(),
                'total_price' => $total,
                'selling_costs' => $data['selling_costs'],
                'net_amount' => Money::sub($total, $data['selling_costs']),
                'notes' => $data['notes'] ?? null,
                'status' => AnimalSaleStatus::Completed,
            ]);

            $this->reason->set(__('Sold — :no', ['no' => $sale->sale_no]));

            foreach (array_values($data['animals']) as $i => $row) {
                $sale->items()->create(['animal_id' => $row['animal_id'], 'price' => $prices[$i]]);
                $this->animals->change($animals->get($row['animal_id']), 'status', AnimalStatus::Sold->value, $date->toDateString());
            }

            return $sale;
        });
    }

    /**
     * Cancels a sale (reason from the request): its animals become active again, dated today. Refused when an animal's
     * status has changed since the sale (e.g. marked deceased) — that change must be undone first.
     */
    public function cancel(AnimalSale $sale): void
    {
        if ($sale->isCancelled()) {
            throw new DomainException(__('This sale is already cancelled.'));
        }

        DB::transaction(function () use ($sale) {
            $items = $sale->items()->with('animal')->get();

            foreach ($items as $item) {
                if ($item->animal->trashed() || $item->animal->status !== AnimalStatus::Sold) {
                    throw new DomainException(__('Animal :tag is no longer marked sold, so this sale cannot be cancelled.', ['tag' => $item->animal->tag_no]));
                }
            }

            $sale->update([
                'status' => AnimalSaleStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $this->reason->get(),
            ]);

            foreach ($items as $item) {
                $this->animals->change($item->animal, 'status', AnimalStatus::Active->value, today()->toDateString());
            }
        });
    }
}
