<?php

use App\Enums\CycleStatus;
use App\Enums\FarmerPaymentStatus;
use App\Enums\RoleName;
use App\Exports\FarmerBankSheetExport;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmerAdvance;
use App\Models\FarmerPayment;
use App\Models\FarmerPaymentCycle;
use App\Models\MilkCollection;
use App\Models\PaymentMode;
use App\Models\RateChart;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\FarmerAdvanceService;
use App\Services\FarmerPaymentService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->travelTo('2026-11-12 10:00');
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($this->accountant);
    RateChart::factory()->create(['farm_id' => $this->farm->id]); // cow fixed ₹40
    $this->cash = PaymentMode::where('code', 'CASH')->value('id');
    $this->farmer = Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-001', 'name' => 'Asha Pawar']);
    $this->other = Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-002', 'name' => 'Bala More']);

    // Asha: 10 days × 20 L × ₹40 = ₹8,000 (1–10 Nov); Bala: 5 L on 3 Nov = ₹200; one rejected entry
    $collections = app(CollectionService::class);
    foreach (range(1, 10) as $day) {
        $collections->create(['collection_date' => sprintf('2026-11-%02d', $day), 'shift' => 'morning', 'farmer_id' => $this->farmer->id,
            'milk_type' => 'cow', 'quantity_litre' => '20', 'fat' => '4.0']);
    }
    $collections->create(['collection_date' => '2026-11-03', 'shift' => 'evening', 'farmer_id' => $this->other->id, 'milk_type' => 'cow', 'quantity_litre' => '5']);
    $collections->create(['collection_date' => '2026-11-04', 'shift' => 'evening', 'farmer_id' => $this->other->id, 'milk_type' => 'cow',
        'quantity_litre' => '3', 'quality_status' => 'rejected', 'rejection_reason' => 'Sour']);
    FarmerAdvance::factory()->create(['farm_id' => $this->farm->id, 'farmer_id' => $this->farmer->id, 'amount' => '500', 'payment_mode_id' => $this->cash]);
    $this->service = app(FarmerPaymentService::class);
});

function makeCycle(string $from = '2026-11-01', string $to = '2026-11-10'): TestResponse
{
    return test()->post(route('farmer-payments.store'), ['from_date' => $from, 'to_date' => $to]);
}

it('makes draft statements: gross 8,000 − advance 500 + bonus 200 = 7,700 (spec acceptance)', function () {
    makeCycle()->assertSessionHasNoErrors()->assertRedirect();

    $cycle = FarmerPaymentCycle::sole();
    expect($cycle)->cycle_no->toBe('FPAY/2026-27/0001')->status->toBe(CycleStatus::Draft)
        ->and($cycle->payments()->count())->toBe(2); // the rejected-only day adds nothing

    $asha = FarmerPayment::where('farmer_id', $this->farmer->id)->sole();
    expect($asha)->total_qty->toBe('200.00')->avg_fat->toBe('4.00')->gross_amount->toBe('8000.00')
        ->advance_deduction->toBe('500.00')->net_payable->toBe('7500.00')
        ->and(MilkCollection::where('farmer_id', $this->farmer->id)->whereNull('farmer_payment_id')->count())->toBe(0)
        ->and(MilkCollection::where('quality_status', 'rejected')->sole()->farmer_payment_id)->toBeNull();

    $this->put(route('farmer-statements.update', $asha), ['bonus' => '200', 'advance_deduction' => '500', 'other_deduction' => '0'])
        ->assertSessionHasNoErrors();
    expect($asha->fresh()->net_payable)->toBe('7700.00');

    $this->get(route('farmer-payments.show', $cycle))->assertOk()->assertSee('₹7,700.00')->assertSee('Bala More');
    $this->get(route('farmer-payments.index'))->assertOk()->assertSee('FPAY/2026-27/0001');
});

it('refuses overlapping cycles, empty ranges, too big deductions and a negative net', function () {
    makeCycle()->assertSessionHasNoErrors();
    makeCycle('2026-11-10', '2026-11-12')->assertSessionHas('error'); // overlaps on the 10th
    makeCycle('2026-11-11', '2026-11-12')->assertSessionHas('error'); // no milk
    makeCycle('2026-11-11', '2026-11-13')->assertSessionHasErrors('to_date'); // future

    $asha = FarmerPayment::where('farmer_id', $this->farmer->id)->sole();
    $this->put(route('farmer-statements.update', $asha), ['advance_deduction' => '600'])->assertSessionHas('error'); // only 500 given
    $this->put(route('farmer-statements.update', $asha), ['other_deduction' => '9000'])->assertSessionHasErrors('deduction_note');
    $this->put(route('farmer-statements.update', $asha), ['other_deduction' => '9000', 'deduction_note' => 'Feed'])->assertSessionHas('error'); // net < 0
    expect($asha->fresh()->net_payable)->toBe('7500.00');
});

it('locks the milk of a cycle period: no new, changed or deleted entries (spec acceptance)', function () {
    makeCycle()->assertSessionHasNoErrors();
    $entry = MilkCollection::where('farmer_id', $this->farmer->id)->first();
    $rejected = MilkCollection::where('quality_status', 'rejected')->sole();
    $collections = app(CollectionService::class);

    expect(fn () => $collections->update($entry, ['collection_date' => '2026-11-01', 'shift' => 'morning', 'milk_type' => 'cow', 'quantity_litre' => '1']))
        ->toThrow(DomainException::class, 'in a farmer payment statement');
    expect(fn () => $collections->delete($rejected))->toThrow(DomainException::class, 'FPAY/2026-27/0001');
    expect(fn () => $collections->create(['collection_date' => '2026-11-05', 'shift' => 'evening', 'farmer_id' => $this->other->id,
        'milk_type' => 'cow', 'quantity_litre' => '5']))->toThrow(DomainException::class, 'cannot be added or changed');
    $collections->create(['collection_date' => '2026-11-11', 'shift' => 'morning', 'farmer_id' => $this->other->id, 'milk_type' => 'cow', 'quantity_litre' => '5']);
    expect(MilkCollection::whereNull('farmer_payment_id')->where('quality_status', 'accepted')->count())->toBe(1); // only the new one

    // deleting the draft unlocks
    $this->delete(route('farmer-payments.destroy', FarmerPaymentCycle::sole()), ['reason' => 'Wrong dates'])->assertSessionHasNoErrors();
    expect(FarmerPaymentCycle::count())->toBe(0)->and(FarmerPayment::count())->toBe(0)
        ->and(MilkCollection::whereNotNull('farmer_payment_id')->count())->toBe(0);
});

it('finalizes, pays one and all in cash, undoes a payment; advances and balance follow', function () {
    makeCycle()->assertSessionHasNoErrors();
    $cycle = FarmerPaymentCycle::sole();
    $asha = FarmerPayment::where('farmer_id', $this->farmer->id)->sole();
    $bala = FarmerPayment::where('farmer_id', $this->other->id)->sole();

    $this->post(route('farmer-statements.pay', $asha), ['paid_on' => '2026-11-12', 'payment_mode_id' => $this->cash])->assertSessionHas('error'); // still draft
    expect(app(FarmerAdvanceService::class)->outstanding($this->farmer->id))->toBe('500.00'); // draft does not recover yet

    $this->post(route('farmer-payments.finalize', $cycle))->assertSessionHasNoErrors();
    expect($cycle->fresh()->status)->toBe(CycleStatus::Finalized)
        ->and(app(FarmerAdvanceService::class)->outstanding($this->farmer->id))->toBe('0.00')
        ->and($this->service->balance($this->farmer->fresh()))->toBe('7500.00'); // 8,000 earned − 500 advance

    $this->put(route('farmer-statements.update', $asha), ['bonus' => '100'])->assertSessionHas('error'); // read-only now
    $this->delete(route('farmer-advances.destroy', FarmerAdvance::sole()), ['reason' => 'Mistake'])->assertSessionHas('error'); // recovered

    $bank = PaymentMode::where('code', 'BANK')->value('id');
    $this->post(route('farmer-statements.pay', $asha), ['paid_on' => '2026-11-12', 'payment_mode_id' => $bank, 'reference_no' => 'UTR123'])
        ->assertSessionHasNoErrors();
    expect($asha->fresh())->status->toBe(FarmerPaymentStatus::Paid)->reference_no->toBe('UTR123')
        ->and($this->service->balance($this->farmer->fresh()))->toBe('0.00');

    $this->post(route('farmer-payments.pay-all', $cycle), ['paid_on' => '2026-11-12'])->assertSessionHas('success', '1 statements marked paid in cash.');
    expect($bala->fresh()->payment_mode_id)->toBe($this->cash);

    $this->post(route('farmer-statements.unpay', $bala))->assertSessionHasErrors('reason');
    $this->post(route('farmer-statements.unpay', $bala), ['reason' => 'Not paid yet'])->assertSessionHasNoErrors();
    expect($bala->fresh()->status)->toBe(FarmerPaymentStatus::Pending);
    $this->delete(route('farmer-payments.destroy', $cycle), ['reason' => 'x x x'])->assertSessionHas('error'); // finalized
});

it('suggests the next range and keeps payments to farmer_payments users', function () {
    expect($this->service->suggestedRange())->toBe(['2026-11-01', '2026-11-10']); // first unpaid milk, 10 days
    makeCycle()->assertSessionHasNoErrors();
    expect($this->service->suggestedRange())->toBe(['2026-11-11', '2026-11-12']); // not past today

    $operator = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();
    $this->actingAs($operator)->get(route('farmer-payments.index'))->assertForbidden();
    makeCycle('2026-11-11', '2026-11-12')->assertForbidden();
});

it('prints a farmer statement PDF with day/shift rows, deductions and net', function () {
    makeCycle()->assertSessionHasNoErrors();
    $asha = FarmerPayment::where('farmer_id', $this->farmer->id)->sole();
    $this->put(route('farmer-statements.update', $asha), ['bonus' => '200', 'advance_deduction' => '500', 'other_deduction' => '0'])->assertSessionHasNoErrors();

    $this->get(route('farmer-statements.pdf', $asha))->assertOk()->assertHeader('content-type', 'application/pdf');

    $html = view('pdf.farmer-statement', ['s' => $asha->fresh(), 'rows' => $asha->collections()->orderBy('collection_date')->get()])->render();
    expect($html)->toContain('Asha Pawar')->toContain('01-11-2026')->toContain('10-11-2026')->toContain('DRAFT')
        ->toContain('₹8,000.00')->toContain('₹500.00')->toContain('₹7,700.00')
        ->and(substr_count($html, '₹40.00'))->toBe(10); // one row per day, rejected milk is not on it

    $operator = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();
    $this->actingAs($operator)->get(route('farmer-statements.pdf', $asha))->assertForbidden();
});

it('exports the bank sheet of a finalized cycle: only unpaid farmers, account numbers kept as text', function () {
    $this->farmer->update(['bank_account_name' => 'Asha Pawar', 'bank_account_no' => '001234567890123', 'ifsc' => 'SBIN0001234']);
    makeCycle()->assertSessionHasNoErrors();
    $cycle = FarmerPaymentCycle::sole();

    $this->get(route('farmer-payments.bank-sheet', $cycle))->assertSessionHas('error'); // draft

    $this->post(route('farmer-payments.finalize', $cycle))->assertSessionHasNoErrors();
    Excel::fake();
    $this->get(route('farmer-payments.bank-sheet', $cycle))->assertOk();
    Excel::assertDownloaded('FPAY-2026-27-0001-bank-sheet.xlsx', function (FarmerBankSheetExport $export) {
        $rows = $export->array();
        expect($rows[4])->toBe(['F-001', 'Asha Pawar', 'Asha Pawar', '001234567890123', 'SBIN0001234', null, '7500.00'])
            ->and($rows[5][1])->toBe('Bala More')
            ->and($rows[6][6])->toBe('7700.00')
            ->and(end($rows)[0])->toContain('1 farmer(s) have no bank account');

        $cell = (new Spreadsheet)->getActiveSheet()->getCell('D5');
        $export->bindValue($cell, '001234567890123');
        expect($cell->getValue())->toBe('001234567890123');

        return true;
    });

    $this->post(route('farmer-statements.pay', FarmerPayment::where('farmer_id', $this->other->id)->sole()), ['paid_on' => '2026-11-12', 'payment_mode_id' => $this->cash]);
    expect(app(FarmerPaymentService::class)->bankSheet($cycle->fresh())->rows)->toHaveCount(1); // Bala paid in cash → off the sheet
});
