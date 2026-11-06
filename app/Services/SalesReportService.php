<?php

namespace App\Services;

use App\Enums\B2bCategory;
use App\Enums\CustomerType;
use App\Enums\DeliveryStatus;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Models\Delivery;
use App\Reports\ReportTable;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Database\Query\Builder;

/**
 * Sales reports (spec 7.6 item 4): customer-wise, route-wise, product-wise and dairy plant reconciliation, from
 * delivered rows in a period. All sums in SQL on the (farm_id, delivery_date, …) index.
 */
class SalesReportService
{
    public const REPORTS = ['customers', 'routes', 'products', 'dairy-plants'];

    public function customerWise(string $from, string $to): ReportTable
    {
        $lines = $this->delivered($from, $to)
            ->join('customers', 'customers.id', '=', 'deliveries.customer_id')
            ->groupBy('customers.id', 'customers.code', 'customers.name', 'customers.customer_type')
            ->select(['customers.code', 'customers.name', 'customers.customer_type'])
            ->selectRaw('COUNT(*) AS cnt, SUM(deliveries.delivered_qty) AS qty, SUM(deliveries.amount) AS amount')
            ->orderByDesc('amount')->orderBy('customers.name')
            ->get();

        return new ReportTable(
            __('Customer-wise sales'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Code')),
                ReportTable::column(__('Customer')),
                ReportTable::column(__('Type')),
                ReportTable::column(__('Deliveries'), ReportTable::NUMBER),
                ReportTable::column(__('Litres'), ReportTable::QUANTITY),
                ReportTable::column(__('Amount'), ReportTable::MONEY),
                ReportTable::column(__('Avg rate / L'), ReportTable::MONEY),
            ],
            $lines->map(fn ($l) => [$l->code, $l->name, CustomerType::from($l->customer_type)->label(), (int) $l->cnt,
                Money::round2((string) $l->qty), Money::round2((string) $l->amount), $this->avg($l->amount, $l->qty)])->all(),
            [__('Total (:count)', ['count' => $lines->count()]), '', '', (int) $lines->sum('cnt'),
                Money::sum($lines->pluck('qty')), Money::sum($lines->pluck('amount')), $this->avg(Money::sum($lines->pluck('amount')), Money::sum($lines->pluck('qty')))],
            [__('Delivered milk only (billed or not). Amount = litres × the rate on each delivery.')],
            emptyText: __('No delivered milk in this period.'),
        );
    }

    public function routeWise(string $from, string $to): ReportTable
    {
        $lines = $this->delivered($from, $to)
            ->leftJoin('routes', 'routes.id', '=', 'deliveries.route_id')
            ->groupBy('deliveries.route_id', 'routes.name', 'routes.sort_order')
            ->select(['routes.name'])
            ->selectRaw('COUNT(DISTINCT deliveries.customer_id) AS customers')
            ->selectRaw('SUM(CASE WHEN deliveries.shift = ? THEN deliveries.delivered_qty ELSE 0 END) AS morning', [MilkShift::Morning->value])
            ->selectRaw('SUM(CASE WHEN deliveries.shift = ? THEN deliveries.delivered_qty ELSE 0 END) AS evening', [MilkShift::Evening->value])
            ->selectRaw('SUM(deliveries.delivered_qty) AS qty, SUM(deliveries.amount) AS amount')
            ->orderByRaw('routes.sort_order IS NULL, routes.sort_order, routes.name')
            ->get();

        return new ReportTable(
            __('Route-wise sales'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Route')),
                ReportTable::column(__('Customers'), ReportTable::NUMBER),
                ReportTable::column(__('Morning (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Evening (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Total (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Amount'), ReportTable::MONEY),
            ],
            $lines->map(fn ($l) => [$l->name ?? __('No route (one-off / B2B)'), (int) $l->customers, Money::round2((string) $l->morning),
                Money::round2((string) $l->evening), Money::round2((string) $l->qty), Money::round2((string) $l->amount)])->all(),
            [__('Total'), '', Money::sum($lines->pluck('morning')), Money::sum($lines->pluck('evening')),
                Money::sum($lines->pluck('qty')), Money::sum($lines->pluck('amount'))],
            [__('Route = the customer\'s route on the day of delivery.')],
            emptyText: __('No delivered milk in this period.'),
        );
    }

    public function productWise(string $from, string $to): ReportTable
    {
        $lines = $this->delivered($from, $to)
            ->join('products', 'products.id', '=', 'deliveries.product_id')
            ->groupBy('products.id', 'products.name', 'deliveries.stock_source')
            ->select(['products.name', 'deliveries.stock_source'])
            ->selectRaw('COUNT(DISTINCT deliveries.customer_id) AS customers, SUM(deliveries.delivered_qty) AS qty, SUM(deliveries.amount) AS amount')
            ->orderBy('products.name')->orderByRaw('deliveries.stock_source = ? DESC', [MilkSource::OwnFarm->value])
            ->get();

        return new ReportTable(
            __('Product-wise sales'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Product')),
                ReportTable::column(__('Milk from')),
                ReportTable::column(__('Customers'), ReportTable::NUMBER),
                ReportTable::column(__('Litres'), ReportTable::QUANTITY),
                ReportTable::column(__('Amount'), ReportTable::MONEY),
                ReportTable::column(__('Avg rate / L'), ReportTable::MONEY),
            ],
            $lines->map(fn ($l) => [$l->name, MilkSource::from($l->stock_source)->label(), (int) $l->customers,
                Money::round2((string) $l->qty), Money::round2((string) $l->amount), $this->avg($l->amount, $l->qty)])->all(),
            [__('Total'), '', '', Money::sum($lines->pluck('qty')), Money::sum($lines->pluck('amount')),
                $this->avg(Money::sum($lines->pluck('amount')), Money::sum($lines->pluck('qty')))],
            emptyText: __('No delivered milk in this period.'),
        );
    }

    /** Each dispatch to a dairy plant: our litres / fat / SNF vs the buyer's, difference in litres and %. */
    public function dairyPlantReconciliation(string $from, string $to): ReportTable
    {
        $lines = $this->delivered($from, $to)
            ->join('customers', 'customers.id', '=', 'deliveries.customer_id')
            ->where('customers.b2b_category', B2bCategory::DairyPlant->value)
            ->select(['deliveries.delivery_date', 'deliveries.shift', 'customers.name', 'deliveries.delivered_qty', 'deliveries.buyer_measured_qty',
                'deliveries.fat', 'deliveries.buyer_fat', 'deliveries.snf', 'deliveries.buyer_snf'])
            ->orderBy('deliveries.delivery_date')->orderBy('deliveries.shift')->orderBy('customers.name')
            ->get();

        $diff = fn ($l) => $l->buyer_measured_qty === null ? null : Money::sub((string) $l->buyer_measured_qty, (string) $l->delivered_qty);
        $pct = fn (?string $d, string $base) => $d === null || Money::isZero($base) ? null : Money::round2(Money::div(Money::mul($d, '100'), $base));

        $measured = $lines->whereNotNull('buyer_measured_qty');
        $ourOfMeasured = Money::sum($measured->pluck('delivered_qty'));
        $totalDiff = Money::sub(Money::sum($measured->pluck('buyer_measured_qty')), $ourOfMeasured);

        return new ReportTable(
            __('Dairy plant reconciliation'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Date'), ReportTable::DATE),
                ReportTable::column(__('Shift')),
                ReportTable::column(__('Dairy plant')),
                ReportTable::column(__('Our L'), ReportTable::QUANTITY),
                ReportTable::column(__('Their L'), ReportTable::QUANTITY),
                ReportTable::column(__('Diff L'), ReportTable::QUANTITY),
                ReportTable::column(__('Diff %'), ReportTable::PERCENT),
                ReportTable::column(__('Our fat'), ReportTable::QUANTITY),
                ReportTable::column(__('Their fat'), ReportTable::QUANTITY),
                ReportTable::column(__('Our SNF'), ReportTable::QUANTITY),
                ReportTable::column(__('Their SNF'), ReportTable::QUANTITY),
            ],
            $lines->map(fn ($l) => [$l->delivery_date, MilkShift::from($l->shift)->label(), $l->name,
                Money::round2((string) $l->delivered_qty), $l->buyer_measured_qty, $diff($l), $pct($diff($l), (string) $l->delivered_qty),
                $l->fat, $l->buyer_fat, $l->snf, $l->buyer_snf])->all(),
            [__('Total'), '', '', Money::sum($lines->pluck('delivered_qty')), Money::sum($measured->pluck('buyer_measured_qty')),
                $totalDiff, $pct($totalDiff, $ourOfMeasured), null, null, null, null],
            [__('Difference = their litres − our litres (minus = they measured less). Totals of "their" and the difference cover only dispatches with the plant\'s reading; add readings from the delivery grid.')],
            emptyText: __('No dispatches to dairy plants in this period.'),
        );
    }

    /** The farm's delivered rows in the period (current-farm scope via the model). */
    private function delivered(string $from, string $to): Builder
    {
        return Delivery::query()->toBase()
            ->where('deliveries.status', DeliveryStatus::Delivered->value)
            ->whereBetween('deliveries.delivery_date', [$from, $to]);
    }

    private function avg(mixed $amount, mixed $qty): string
    {
        return Money::isZero($qty) ? '0.00' : Money::round2(Money::div((string) $amount, (string) $qty));
    }

    private function period(string $from, string $to): string
    {
        return Format::date($from).' – '.Format::date($to);
    }
}
