<?php

namespace App\Services;

use App\Enums\AnimalSaleStatus;
use App\Enums\AnimalSource;
use App\Enums\DeliveryStatus;
use App\Enums\FeedTransactionType;
use App\Enums\MilkSource;
use App\Enums\OwnershipType;
use App\Models\Animal;
use App\Models\AnimalSale;
use App\Reports\ReportTable;
use App\Support\Format;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estimated profit & loss per animal (spec Phase 6). Every figure is a SQL sum over the animal's records:
 * - cost   = purchase total cost (price + share of costs) or opening value, + expenses booked to the animal (treatments,
 *            vaccinations, breeding create those), + feed eaten by the animal × the item's average purchase cost;
 * - income = sale price − its share of the sale's selling costs (latest completed sale), + for own-herd animals the
 *            estimated milk value = litres per month × average realized selling rate of that milk type in that month;
 * - profit = income − cost.
 * Works on many animals at once (a fixed number of queries), so lists and the report never query per animal.
 */
class AnimalPnlService
{
    /** The breakdown of one animal (animal page). */
    public function forAnimal(Animal $animal): array
    {
        return $this->forAnimals(collect([$animal]))[$animal->id];
    }

    /**
     * @param  Collection<int, Animal>  $animals
     * @return array<int, array<string, mixed>> animal id => breakdown
     */
    public function forAnimals(Collection $animals): array
    {
        $ids = $animals->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $purchases = DB::table('animal_purchase_items as i')->join('animal_purchases as p', 'p.id', '=', 'i.animal_purchase_id')
            ->whereIn('i.animal_id', $ids)->get(['i.animal_id', 'i.total_cost', 'p.purchase_date', 'p.purchase_no', 'p.id as purchase_id'])
            ->keyBy('animal_id');
        $expenses = DB::table('expenses')->whereIn('animal_id', $ids)->whereNull('deleted_at')
            ->groupBy('animal_id')->pluck(DB::raw('SUM(amount)'), 'animal_id');
        $feed = $this->feedCost($ids);
        $sales = $this->sales($ids);
        $milk = $this->milk($animals->filter(fn (Animal $a) => $a->ownership_type === OwnershipType::OwnHerd));

        return $animals->mapWithKeys(function (Animal $a) use ($purchases, $expenses, $feed, $sales, $milk) {
            $purchase = $purchases->get($a->id);
            $base = $purchase ? (string) $purchase->total_cost
                : ($a->source === AnimalSource::Opening ? (string) ($a->opening_value ?? '0') : '0');
            $expense = Money::round2((string) ($expenses[$a->id] ?? 0));
            $feedCost = $feed[$a->id] ?? '0.00';
            $sale = $sales[$a->id] ?? null;
            $milkRow = $milk[$a->id] ?? ['litres' => '0.00', 'value' => '0.00'];

            $cost = Money::add($base, $expense, $feedCost);
            $income = Money::add($sale['income'] ?? '0', $milkRow['value']);
            $start = $purchase?->purchase_date;
            $end = $sale['date'] ?? null;

            return [$a->id => [
                'base_cost' => Money::round2($base),
                'base_label' => $purchase ? __('Purchase :no (price + share of costs)', ['no' => $purchase->purchase_no])
                    : ($a->source === AnimalSource::Opening ? __('Opening value') : __('Born on farm')),
                'purchase_id' => $purchase?->purchase_id,
                'purchase_date' => $start,
                'expenses' => $expense,
                'feed_cost' => $feedCost,
                'total_cost' => $cost,
                'sale' => $sale,
                'milk_litres' => $milkRow['litres'],
                'milk_value' => $milkRow['value'],
                'income' => $income,
                'profit' => Money::sub($income, $cost),
                'holding_days' => $start ? (int) Carbon::parse($start)->diffInDays(Carbon::parse($end ?? today())) : null,
            ]];
        })->all();
    }

    /**
     * Trading summary (spec Phase 6): every resale animal bought in the period — purchase cost, holding days, costs,
     * sale and estimated profit. Not-yet-sold animals show their costs so far.
     */
    public function tradingSummary(string $from, string $to): ReportTable
    {
        $animals = Animal::withTrashed()
            ->where('ownership_type', OwnershipType::Resale->value)
            ->whereIn('id', DB::table('animal_purchase_items as i')->join('animal_purchases as p', 'p.id', '=', 'i.animal_purchase_id')
                ->whereBetween('p.purchase_date', [$from, $to])->select('i.animal_id'))
            ->orderBy('tag_no')
            ->get(['id', 'tag_no', 'name', 'source', 'ownership_type', 'opening_value']);

        $pnl = $this->forAnimals($animals);
        $rows = $animals->map(function (Animal $a) use ($pnl) {
            $p = $pnl[$a->id];

            return [$a->label(), $p['purchase_date'], Money::round2($p['base_cost']), $p['holding_days'],
                Money::add($p['expenses'], $p['feed_cost']), $p['total_cost'], $p['sale']['date'] ?? null,
                $p['sale'] ? $p['sale']['income'] : null, $p['sale'] ? $p['profit'] : null];
        });

        $sold = $rows->filter(fn ($r) => $r[7] !== null);

        return new ReportTable(
            __('Animal trading summary (estimated)'),
            Format::date($from).' – '.Format::date($to),
            [
                ReportTable::column(__('Animal')), ReportTable::column(__('Bought'), ReportTable::DATE),
                ReportTable::column(__('Purchase cost'), ReportTable::MONEY), ReportTable::column(__('Days held'), ReportTable::NUMBER),
                ReportTable::column(__('Feed & other costs'), ReportTable::MONEY), ReportTable::column(__('Total cost'), ReportTable::MONEY),
                ReportTable::column(__('Sold'), ReportTable::DATE), ReportTable::column(__('Sale (net)'), ReportTable::MONEY),
                ReportTable::column(__('Est. profit'), ReportTable::MONEY),
            ],
            $rows->all(),
            [__('Total'), null, Money::sum($rows->pluck(2)), null, Money::sum($rows->pluck(4)), Money::sum($rows->pluck(5)), null,
                Money::sum($sold->pluck(7)), Money::sum($sold->pluck(8))],
            [
                __('Resale animals bought in the period. Feed = feed recorded for the animal × average purchase cost of that feed.'),
                __('Sale (net) = sale price − its share of the selling costs. Profit is shown once the animal is sold.'),
            ],
            emptyText: __('No resale animals were bought in this period.'),
        );
    }

    /**
     * Feed eaten per animal × each item's average purchase cost (all purchases of the item).
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function feedCost(array $ids): array
    {
        $eaten = DB::table('feed_transactions')->whereIn('animal_id', $ids)->where('type', FeedTransactionType::Consumption->value)
            ->groupBy('animal_id', 'feed_item_id')->get(['animal_id', 'feed_item_id', DB::raw('SUM(quantity) AS qty')]);

        if ($eaten->isEmpty()) {
            return [];
        }

        $avg = DB::table('feed_transactions')->whereIn('feed_item_id', $eaten->pluck('feed_item_id')->unique()->all())
            ->where('type', FeedTransactionType::Purchase->value)->groupBy('feed_item_id')
            ->get(['feed_item_id', DB::raw('SUM(amount) AS amount'), DB::raw('SUM(quantity) AS qty')])
            ->filter(fn ($r) => Money::compare((string) $r->qty, '0') > 0)
            ->mapWithKeys(fn ($r) => [$r->feed_item_id => bcdiv((string) $r->amount, (string) $r->qty, 6)]);

        return $eaten->groupBy('animal_id')->map(fn ($rows) => Money::sum($rows->map(
            fn ($r) => Money::round2(bcmul((string) $r->qty, (string) ($avg[$r->feed_item_id] ?? '0'), 6))
        )))->all();
    }

    /**
     * Latest completed sale of each animal: its price less its share of the sale's selling costs (by price, last item
     * absorbs rounding — same split as purchase costs).
     *
     * @param  list<int>  $ids
     * @return array<int, array{id: int, no: string, date: string, price: string, selling_share: string, income: string}>
     */
    private function sales(array $ids): array
    {
        $sales = AnimalSale::query()->where('status', AnimalSaleStatus::Completed->value)
            ->whereHas('items', fn ($q) => $q->whereIn('animal_id', $ids))
            ->with(['items' => fn ($q) => $q->orderBy('id')])
            ->orderBy('sale_date')->orderBy('id')
            ->get(['id', 'sale_no', 'sale_date', 'selling_costs']);

        $out = [];
        foreach ($sales as $sale) {
            $shares = AnimalPurchaseService::allocate(Money::normalize($sale->selling_costs), $sale->items->pluck('price')->map(fn ($p) => (string) $p)->all());

            foreach ($sale->items as $i => $item) {
                if (in_array($item->animal_id, $ids, true)) {
                    $out[$item->animal_id] = [ // later sales overwrite earlier ones
                        'id' => $sale->id, 'no' => $sale->sale_no, 'date' => $sale->sale_date->toDateString(),
                        'price' => Money::round2($item->price), 'selling_share' => $shares[$i],
                        'income' => Money::sub($item->price, $shares[$i]),
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Own-herd milk: litres per animal and month × the average realized rate of own-farm deliveries of that milk type in
     * that month; a month without deliveries uses the type's average over all months.
     *
     * @param  Collection<int, Animal>  $animals
     * @return array<int, array{litres: string, value: string}>
     */
    private function milk(Collection $animals): array
    {
        if ($animals->isEmpty()) {
            return [];
        }

        $litres = DB::table('milk_production_records')->whereIn('animal_id', $animals->pluck('id')->all())
            ->groupByRaw("animal_id, DATE_FORMAT(production_date, '%Y-%m')")
            ->get([DB::raw('animal_id'), DB::raw("DATE_FORMAT(production_date, '%Y-%m') AS month"), DB::raw('SUM(quantity_litre) AS litres')]);

        if ($litres->isEmpty()) {
            return [];
        }

        $rates = DB::table('deliveries as d')->join('products as p', 'p.id', '=', 'd.product_id')
            ->where('d.status', DeliveryStatus::Delivered->value)->where('d.stock_source', MilkSource::OwnFarm->value)
            ->where('d.delivered_qty', '>', 0)
            ->groupByRaw("p.milk_type, DATE_FORMAT(d.delivery_date, '%Y-%m')")
            ->get([DB::raw('p.milk_type'), DB::raw("DATE_FORMAT(d.delivery_date, '%Y-%m') AS month"),
                DB::raw('SUM(d.amount) AS amount'), DB::raw('SUM(d.delivered_qty) AS qty')]);
        $monthRate = $rates->mapWithKeys(fn ($r) => ["{$r->milk_type}|{$r->month}" => bcdiv((string) $r->amount, (string) $r->qty, 6)]);
        $typeRate = $rates->groupBy('milk_type')->map(fn ($rs) => bcdiv(Money::sum($rs->pluck('amount')), Money::sum($rs->pluck('qty')), 6));

        $types = $animals->mapWithKeys(fn (Animal $a) => [$a->id => $a->animal_type->value]); // milk type = animal type (cow / buffalo)

        return $litres->groupBy('animal_id')->map(function ($rows, $animalId) use ($types, $monthRate, $typeRate) {
            $type = $types[$animalId];

            return [
                'litres' => Money::sum($rows->pluck('litres')),
                'value' => Money::sum($rows->map(fn ($r) => Money::round2(bcmul((string) $r->litres,
                    (string) ($monthRate["{$type}|{$r->month}"] ?? $typeRate[$type] ?? '0'), 6)))),
            ];
        })->all();
    }
}
