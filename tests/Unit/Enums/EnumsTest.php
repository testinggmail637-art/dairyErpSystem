<?php

use App\Enums\MilkSource;
use App\Enums\MilkType;

it('gives value => label options for dropdowns', function () {
    expect(MilkType::options())->toBe(['cow' => 'Cow', 'buffalo' => 'Buffalo'])
        ->and(MilkSource::options())->toBe(['own_farm' => 'Own Farm', 'collection' => 'Collection Center'])
        ->and(MilkSource::values())->toBe(['own_farm', 'collection']);
});

it('labels each case', function () {
    expect(MilkType::Buffalo->label())->toBe('Buffalo')
        ->and(MilkSource::from('own_farm'))->toBe(MilkSource::OwnFarm);
});
