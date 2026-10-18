<?php

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\VendorLedgerService;
use App\Support\FinancialYear;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->feed = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Feed']);
    $this->section = Section::factory()->create(['farm_id' => $this->farm->id]);
    $this->cash = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);
    $this->vendor = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds', 'opening_balance' => '0.00']);
});

function creditPurchase(string $amount, string $date = '2026-10-01', array $extra = []): Expense
{
    $t = test();

    return Expense::factory()->credit($t->vendor)->create(array_merge([
        'amount' => $amount, 'expense_date' => $date, 'category_id' => $t->feed->id, 'section_id' => $t->section->id,
    ], $extra));
}

function payVendor(string $amount, string $date = '2026-10-05'): VendorPayment
{
    $t = test();

    return VendorPayment::factory()->create([
        'vendor_id' => $t->vendor->id, 'farm_id' => $t->farm->id, 'amount' => $amount,
        'payment_date' => $date, 'payment_mode_id' => $t->cash->id,
    ]);
}

function vendorBalance(): string
{
    return Vendor::query()->withoutGlobalScopes()->withBalance()->find(test()->vendor->id)->balance();
}

it('spec acceptance: ₹10,000 credit feed and a ₹4,000 payment leave a ₹6,000 balance; paid expenses do not count', function () {
    creditPurchase('10000.00');
    Expense::factory()->create([   // paid on the spot to the same vendor
        'farm_id' => $this->farm->id, 'vendor_id' => $this->vendor->id, 'paid_to_text' => null, 'is_credit' => false,
        'payment_mode_id' => $this->cash->id, 'amount' => '2500.00', 'category_id' => $this->feed->id, 'section_id' => $this->section->id,
    ]);

    $this->actingAs($this->accountant)->post(route('vendor-payments.store'), [
        'vendor_id' => $this->vendor->id, 'payment_date' => now()->toDateString(), 'amount' => '4,000',
        'payment_mode_id' => $this->cash->id, 'client_uuid' => (string) Str::uuid(),
    ])->assertRedirect(route('vendors.show', $this->vendor))->assertSessionHas('success');

    expect(vendorBalance())->toBe('6000.00');

    $this->actingAs($this->accountant)->get(route('vendors.index'))->assertSee('₹6,000.00');
    $this->actingAs($this->accountant)->get(route('vendors.show', $this->vendor))->assertSee('₹6,000.00');
});

it('includes the opening balance, ignores deleted expenses and allows an advance (negative balance)', function () {
    $this->vendor->update(['opening_balance' => '1500.00']);
    creditPurchase('1000.00');
    creditPurchase('9999.00')->delete();
    payVendor('3000.00');

    expect(vendorBalance())->toBe('-500.00');
});

it('lists the ledger in date order with a running balance', function () {
    $this->vendor->update(['opening_balance' => '500.00']);
    creditPurchase('1000.00', '2026-10-01');
    payVendor('300.00', '2026-10-02');
    creditPurchase('200.50', '2026-10-02');   // same day: purchases come before payments
    payVendor('1000.00', '2026-10-03');

    $ledger = app(VendorLedgerService::class)->ledger($this->vendor, null, null);

    expect($ledger['opening'])->toBe('500.00')
        ->and($ledger['rows']->getCollection()->map(fn ($r) => [$r->type, (string) $r->balance])->all())->toBe([
            ['expense', '1500.00'],
            ['expense', '1700.50'],
            ['payment', '1400.50'],
            ['payment', '400.50'],
        ])
        ->and($ledger['debit'])->toBe('1200.50')
        ->and($ledger['credit'])->toBe('1300.00')
        ->and($ledger['closing'])->toBe('400.50');
});

it('starts a period from the balance before it', function () {
    creditPurchase('1000.00', '2026-09-10');
    payVendor('400.00', '2026-09-20');
    creditPurchase('250.00', '2026-10-01');

    $this->actingAs($this->accountant)->get(route('vendors.show', [$this->vendor, 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertSee(__('Balance on :date', ['date' => '01-10-2026']))
        ->assertSeeInOrder(['₹600.00', '₹250.00', '₹850.00']);

    $this->actingAs($this->accountant)->get(route('vendors.show', [$this->vendor, 'from' => '2026-10-05', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});

it('opens the ledger on the current financial year and shows everything with All time', function () {
    $fyStart = FinancialYear::start();
    creditPurchase('700.00', $fyStart->subDays(10)->toDateString(), ['note' => 'Last year purchase']);
    creditPurchase('300.00', $fyStart->toDateString(), ['note' => 'This year purchase']);

    $this->actingAs($this->accountant)->get(route('vendors.show', $this->vendor))
        ->assertSee(__('Balance on :date', ['date' => $fyStart->format('d-m-Y')]))
        ->assertSee('This year purchase')
        ->assertDontSee('Last year purchase')
        ->assertSeeInOrder(['₹700.00', '₹300.00', '₹1,000.00']);

    $this->actingAs($this->accountant)->get(route('vendors.show', [$this->vendor, 'all' => 1]))
        ->assertSee(__('Opening balance'))
        ->assertSee('Last year purchase')
        ->assertSee('This year purchase');
});

it('records each payment once even if the form is submitted twice (client_uuid)', function () {
    $payload = [
        'vendor_id' => $this->vendor->id, 'payment_date' => now()->toDateString(), 'amount' => '500',
        'payment_mode_id' => $this->cash->id, 'client_uuid' => (string) Str::uuid(),
    ];

    $this->actingAs($this->accountant)->post(route('vendor-payments.store'), $payload)->assertSessionHas('success');
    $this->actingAs($this->accountant)->post(route('vendor-payments.store'), $payload)
        ->assertSessionHas('success', __('This payment was already recorded.'));

    expect(VendorPayment::count())->toBe(1);
});

it('validates payments', function () {
    $foreignVendor = Vendor::factory()->create(['farm_id' => Farm::factory()->create()->id]);

    $this->actingAs($this->accountant)->post(route('vendor-payments.store'), [
        'vendor_id' => $foreignVendor->id, 'payment_date' => now()->addDay()->toDateString(), 'amount' => '0', 'payment_mode_id' => '',
    ])->assertSessionHasErrors(['vendor_id', 'payment_date', 'amount', 'payment_mode_id']);
});

it('needs a reason to edit or delete a payment, and logs it', function () {
    $payment = payVendor('1000.00', now()->toDateString());
    $base = ['vendor_id' => $this->vendor->id, 'payment_date' => now()->toDateString(), 'amount' => '1200', 'payment_mode_id' => $this->cash->id];

    $this->actingAs($this->accountant)->get(route('vendor-payments.edit', $payment))->assertOk();
    $this->actingAs($this->accountant)->put(route('vendor-payments.update', $payment), $base)->assertSessionHasErrors('reason');
    $this->actingAs($this->accountant)->put(route('vendor-payments.update', $payment), $base + ['reason' => 'Paid 200 more'])
        ->assertRedirect(route('vendors.show', $this->vendor));
    expect($payment->fresh()->amount)->toBe('1200.00');

    $this->actingAs($this->accountant)->delete(route('vendor-payments.destroy', $payment))->assertSessionHasErrors('reason');
    $this->actingAs($this->accountant)->delete(route('vendor-payments.destroy', $payment), ['reason' => 'Bounced cheque'])
        ->assertRedirect(route('vendors.show', $this->vendor));

    expect(VendorPayment::count())->toBe(0)
        ->and(Activity::where('subject_type', (new VendorPayment)->getMorphClass())->where('event', 'deleted')->first()->reason())
        ->toBe('Bounced cheque');
});

it('applies the staff rule to payments and blocks deleting a vendor with payments', function () {
    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create(); // no payment permissions
    $cashier = User::factory()->forFarm($this->farm)->create();
    $cashier->givePermissionTo(['vendor_payments.create']);

    $this->actingAs($herdStaff)->get(route('vendor-payments.create', ['vendor_id' => $this->vendor->id]))->assertForbidden();

    $this->actingAs($cashier);
    $own = payVendor('100.00', now()->toDateString());
    $others = payVendor('100.00', now()->toDateString());
    $others->forceFill(['created_by' => $this->accountant->id])->saveQuietly();

    $reasoned = ['vendor_id' => $this->vendor->id, 'payment_date' => now()->toDateString(), 'amount' => '150', 'payment_mode_id' => $this->cash->id, 'reason' => 'Typo fix'];
    $this->actingAs($cashier)->put(route('vendor-payments.update', $own), $reasoned)->assertRedirect(route('home'));
    $this->actingAs($cashier)->put(route('vendor-payments.update', $others), $reasoned)->assertForbidden();

    $this->actingAs($this->accountant)->delete(route('vendors.destroy', $this->vendor))->assertSessionHas('error');
});
