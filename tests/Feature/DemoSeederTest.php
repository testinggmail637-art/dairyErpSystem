<?php

use App\Enums\MilkSource;
use App\Models\Animal;
use App\Models\AnimalStatusHistory;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\MilkStockMovement;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\CustomerBalanceService;
use App\Services\MilkStockService;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

it('seeds a year of demo expenses, vendors, payments and users, once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    $farm = Farm::where('code', 'MDF')->firstOrFail();
    $expenses = Expense::allFarms()->where('farm_id', $farm->id);
    $delivered = Delivery::allFarms()->where('farm_id', $farm->id)->where('status', 'delivered')->where('delivered_qty', '>', 0);

    expect(Vendor::allFarms()->where('notes', DemoSeeder::MARK)->count())->toBe(25)
        ->and($expenses->count())->toBeGreaterThan(2000)
        ->and((clone $expenses)->where('is_credit', true)->whereNull('vendor_id')->count())->toBe(0)
        ->and((clone $expenses)->where('is_credit', false)->whereNull('payment_mode_id')->count())->toBe(0)
        ->and((clone $expenses)->min('expense_date'))->toBeGreaterThanOrEqual(now()->subYear()->toDateString())
        ->and(VendorPayment::allFarms()->count())->toBeGreaterThan(100)
        ->and(User::where('email', 'accountant@dairy.test')->first()->hasRole('accountant'))->toBeTrue()
        ->and(Animal::allFarms()->where('farm_id', $farm->id)->count())->toBe(30)
        ->and(Animal::allFarms()->where('farm_id', $farm->id)->milking()->count())->toBe(20)
        ->and(Animal::allFarms()->whereNotNull('mother_id')->count())->toBe(3)
        ->and(AnimalStatusHistory::allFarms()->count())->toBe(90) // 3 starting rows per animal
        ->and(MilkProductionRecord::allFarms()->count())->toBeGreaterThan(14000) // a year, 2 shifts, ~20 animals
        ->and(MilkProductionRecord::allFarms()->max('quantity_litre'))->toBeLessThanOrEqual('60.00')
        // every entry and every delivered row has its movement: own-farm stock = milk produced − milk delivered
        ->and(MilkStockMovement::allFarms()->count())->toBe(MilkProductionRecord::allFarms()->count() + $delivered->count())
        ->and(app(MilkStockService::class)->balance(MilkSource::OwnFarm))->toBe(Money::sub(
            (string) MilkProductionRecord::allFarms()->sum('quantity_litre'), (string) $delivered->sum('delivered_qty')))
        ->and(Money::isNegative(app(MilkStockService::class)->balance(MilkSource::OwnFarm)))->toBeFalse();

    // Phase 3: customers, a year of deliveries, finalized monthly bills (current month unbilled), payments allocated
    $bills = Bill::allFarms()->where('farm_id', $farm->id);
    expect(Customer::allFarms()->where('notes', DemoSeeder::MARK)->count())->toBe(88)
        ->and($delivered->count())->toBeGreaterThan(25000)
        ->and((clone $bills)->where('status', 'finalized')->count())->toBe($bills->count())
        ->and((clone $bills)->min('bill_no'))->toStartWith('BILL/')
        ->and(Delivery::allFarms()->where('status', 'delivered')->whereNull('bill_id')->where('delivery_date', '<', now()->startOfMonth()->toDateString())->count())->toBe(0)
        ->and(PaymentAllocation::allFarms()->count())->toBeGreaterThan(500)
        // bill sum = billed deliveries; latest closing = the customer ledger balance on the bill date
        ->and(Money::round2((string) (clone $bills)->sum('total')))->toBe(Money::round2((string) Delivery::allFarms()->whereNotNull('bill_id')->sum('amount')));

    $last = (clone $bills)->latest('bill_date')->first();
    expect(app(CustomerBalanceService::class)->balanceAsOf($last->customer, $last->bill_date->toDateString()))->toBe($last->closing_balance);

    $count = $expenses->count();
    $deliveries = Delivery::allFarms()->count();
    $this->seed(DemoSeeder::class); // second run is skipped
    expect(Expense::allFarms()->count())->toBe($count)
        ->and(Delivery::allFarms()->count())->toBe($deliveries)
        ->and(MilkStockMovement::allFarms()->count())->toBe(MilkProductionRecord::allFarms()->count() + $delivered->count());
});

it('refuses to run in production', function () {
    $this->seed(DatabaseSeeder::class);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class, 'DemoSeeder is for local/testing only.');
});
