<?php

use App\Enums\MilkSource;
use App\Models\Animal;
use App\Models\AnimalStatusHistory;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\MilkStockMovement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\MilkStockService;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

it('seeds a year of demo expenses, vendors, payments and users, once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    $farm = Farm::where('code', 'MDF')->firstOrFail();
    $expenses = Expense::allFarms()->where('farm_id', $farm->id);

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
        // every entry has its production movement, so the own-farm stock equals the milk produced
        ->and(MilkStockMovement::allFarms()->count())->toBe(MilkProductionRecord::allFarms()->count())
        ->and(app(MilkStockService::class)->balance(MilkSource::OwnFarm))->toBe(Money::round2((string) MilkProductionRecord::allFarms()->sum('quantity_litre')));

    $count = $expenses->count();
    $this->seed(DemoSeeder::class); // second run is skipped
    expect(Expense::allFarms()->count())->toBe($count)
        ->and(MilkStockMovement::allFarms()->count())->toBe(MilkProductionRecord::allFarms()->count());
});

it('refuses to run in production', function () {
    $this->seed(DatabaseSeeder::class);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class, 'DemoSeeder is for local/testing only.');
});
