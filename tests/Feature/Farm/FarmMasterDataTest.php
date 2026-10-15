<?php

use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FarmMasterDataSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

it('seeds the default sections and payment modes', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Section::allFarms()->orderBy('id')->pluck('name', 'code')->all())->toBe([
        'HERD' => 'Own Farm/Herd',
        'COLLECTION' => 'Collection Center',
        'DELIVERY' => 'Sales & Delivery',
        'TRADING' => 'Animal Trading',
        'OFFICE' => 'Office/General',
    ])->and(PaymentMode::allFarms()->orderBy('id')->pluck('name', 'code')->all())->toBe([
        'CASH' => 'Cash',
        'UPI' => 'UPI',
        'BANK' => 'Bank Transfer',
        'CHEQUE' => 'Cheque',
        'CARD' => 'Card',
    ]);
});

it('seeds every farm once and keeps changes made by the owner', function () {
    [$farmA, $farmB] = Farm::factory()->count(2)->create();
    $this->seed(FarmMasterDataSeeder::class);

    Section::allFarms()->where('farm_id', $farmA->id)->where('code', 'HERD')->update(['name' => 'Gaushala', 'is_active' => false]);
    $this->seed(FarmMasterDataSeeder::class);

    expect(Section::allFarms()->where('farm_id', $farmA->id)->count())->toBe(5)
        ->and(Section::allFarms()->where('farm_id', $farmB->id)->count())->toBe(5)
        ->and(PaymentMode::allFarms()->count())->toBe(10)
        ->and(Section::allFarms()->where('farm_id', $farmA->id)->where('code', 'HERD')->value('name'))->toBe('Gaushala');
});

it('keeps codes unique within a farm but allows the same code in another farm', function () {
    [$farmA, $farmB] = Farm::factory()->count(2)->create();

    app(CurrentFarm::class)->set($farmA);
    PaymentMode::create(['name' => 'Paytm', 'code' => 'paytm']);

    app(CurrentFarm::class)->set($farmB);
    $other = PaymentMode::create(['name' => 'Paytm', 'code' => 'PAYTM']);

    expect($other->code)->toBe('PAYTM')
        ->and(PaymentMode::count())->toBe(1);  // farm B only

    app(CurrentFarm::class)->set($farmA);
    PaymentMode::create(['name' => 'Paytm again', 'code' => 'PAYTM']);
})->throws(UniqueConstraintViolationException::class);

it('lists only active rows with the active scope', function () {
    $farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($farm);
    Section::seedDefaultsFor($farm);
    Section::where('code', 'TRADING')->update(['is_active' => false]);

    expect(Section::active()->pluck('code')->all())->not->toContain('TRADING')
        ->and(Section::active()->count())->toBe(4);
});
