<?php

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Models\Farm;
use App\Services\DocumentNumberService;
use App\Support\CurrentFarm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->numbers = app(DocumentNumberService::class);
});

it('issues numbers in the BILL/2026-27/0001 format, one after another', function () {
    $date = Carbon::parse('2026-10-15');

    expect($this->numbers->next(DocumentType::Bill, $date))->toBe('BILL/2026-27/0001')
        ->and($this->numbers->next(DocumentType::Bill, $date))->toBe('BILL/2026-27/0002')
        ->and(DocumentSequence::first()->last_number)->toBe(2);
});

it('uses the right prefix for every document type', function () {
    $date = Carbon::parse('2026-10-15');

    expect(array_map(fn (DocumentType $type) => $this->numbers->next($type, $date), DocumentType::cases()))
        ->toBe(['BILL/2026-27/0001', 'FPAY/2026-27/0001', 'APUR/2026-27/0001', 'ASAL/2026-27/0001', 'SAL/2026-27/0001']);
});

it('starts again from 0001 in a new financial year, by document date', function () {
    $this->numbers->next(DocumentType::Bill, Carbon::parse('2027-03-31'));
    $this->numbers->next(DocumentType::Bill, Carbon::parse('2027-03-31'));

    expect($this->numbers->next(DocumentType::Bill, Carbon::parse('2027-04-01')))->toBe('BILL/2027-28/0001')
        ->and($this->numbers->next(DocumentType::Bill, Carbon::parse('2027-03-20')))->toBe('BILL/2026-27/0003');
});

it('keeps a separate sequence for each farm', function () {
    $date = Carbon::parse('2026-10-15');
    $this->numbers->next(DocumentType::Bill, $date);
    $this->numbers->next(DocumentType::Bill, $date);

    app(CurrentFarm::class)->set(Farm::factory()->create());

    expect($this->numbers->next(DocumentType::Bill, $date))->toBe('BILL/2026-27/0001');
});

it('releases the number when the saving transaction rolls back', function () {
    $date = Carbon::parse('2026-10-15');
    $this->numbers->next(DocumentType::Bill, $date);

    try {
        DB::transaction(function () use ($date) {
            $this->numbers->next(DocumentType::Bill, $date);
            throw new RuntimeException('bill save failed');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($this->numbers->next(DocumentType::Bill, $date))->toBe('BILL/2026-27/0002');
});

it('pads to four digits and grows beyond 9999', function () {
    expect(DocumentNumberService::format(DocumentType::Salary, '2026-27', 7))->toBe('SAL/2026-27/0007')
        ->and(DocumentNumberService::format(DocumentType::Salary, '2026-27', 12345))->toBe('SAL/2026-27/12345');
});

it('refuses to issue a number without a current farm', function () {
    app(CurrentFarm::class)->forget();

    app(DocumentNumberService::class)->next(DocumentType::Bill);
})->throws(LogicException::class);
