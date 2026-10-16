<?php

use App\Enums\RoleName;
use App\Exports\ExpensesExport;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\ExportReadyNotification;
use App\Services\ExpenseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    Storage::fake('local');
    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();

    $this->feed = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Feed']);
    $this->cattleFeed = ExpenseCategory::factory()->childOf($this->feed)->create(['name' => 'Cattle Feed']);
    $this->health = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Health']);
    $this->medicine = ExpenseCategory::factory()->childOf($this->health)->create(['name' => 'Medicine']);
    $this->herd = Section::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Own Herd']);
    $this->cash = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);
    $this->vendor = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds']);
});

function expensePayload(array $overrides = []): array
{
    $t = test();

    return array_merge([
        'expense_date' => now()->toDateString(),
        'amount' => '1,250.50',
        'category_id' => $t->feed->id,
        'subcategory_id' => $t->cattleFeed->id,
        'section_id' => $t->herd->id,
        'is_credit' => '0',
        'vendor_id' => '',
        'paid_to_text' => 'Local mandi',
        'payment_mode_id' => $t->cash->id,
        'quantity' => '5',
        'unit' => 'bag',
        'note' => 'Five bags',
    ], $overrides);
}

it('saves an expense directly (no approval) and shows it in the list and totals at once', function () {
    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload())
        ->assertRedirect(route('expenses.index'))
        ->assertSessionHas('success');

    $expense = Expense::firstOrFail();
    expect($expense->amount)->toBe('1250.50')
        ->and($expense->quantity)->toBe('5.00')
        ->and($expense->unit->value)->toBe('bag')
        ->and($expense->farm_id)->toBe($this->farm->id)
        ->and($expense->created_by)->toBe($this->accountant->id)
        ->and(array_keys($expense->getAttributes()))->not->toContain('status', 'approved_by', 'approved_at');

    $this->actingAs($this->accountant)->get(route('expenses.index'))
        ->assertOk()
        ->assertSee('Local mandi')
        ->assertSee('₹1,250.50');
});

it('needs a vendor for credit, a payment mode for paid, and clears the payment mode on credit', function () {
    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload(['is_credit' => '1', 'vendor_id' => '']))
        ->assertSessionHasErrors('vendor_id');
    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload(['payment_mode_id' => '']))
        ->assertSessionHasErrors('payment_mode_id');

    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload([
        'is_credit' => '1', 'vendor_id' => $this->vendor->id, 'payment_mode_id' => $this->cash->id,
    ]))->assertSessionDoesntHaveErrors();

    $expense = Expense::firstOrFail();
    expect($expense->is_credit)->toBeTrue()->and($expense->payment_mode_id)->toBeNull()
        ->and($expense->vendor_id)->toBe($this->vendor->id)->and($expense->paid_to_text)->toBeNull();
});

it('checks on the server that the subcategory belongs to the category', function () {
    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload(['subcategory_id' => $this->medicine->id]))
        ->assertSessionHasErrors(['subcategory_id' => __('This subcategory does not belong to the chosen category.')]);

    // a subcategory cannot be used as the main category
    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload(['category_id' => $this->cattleFeed->id, 'subcategory_id' => '']))
        ->assertSessionHasErrors('category_id');

    expect(Expense::count())->toBe(0);
});

it('refuses future dates, zero or badly formatted amounts, other farms and inactive lookups', function () {
    $otherFarm = Farm::factory()->create();
    $foreignSection = Section::factory()->create(['farm_id' => $otherFarm->id]);
    $inactiveMode = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'is_active' => false]);

    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload([
        'expense_date' => now()->addDay()->toDateString(), 'amount' => '0', 'section_id' => $foreignSection->id,
        'payment_mode_id' => $inactiveMode->id, 'quantity' => '2', 'unit' => '',
    ]))->assertSessionHasErrors(['expense_date', 'amount', 'section_id', 'payment_mode_id', 'unit']);

    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload(['amount' => '10.555']))
        ->assertSessionHasErrors('amount');
});

it('stores receipts on the private disk, scales big photos and serves them only through the app', function () {
    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload([
        'receipt' => UploadedFile::fake()->image('bill.jpg', 3000, 2000),
    ]))->assertSessionDoesntHaveErrors();

    $expense = Expense::firstOrFail();
    expect($expense->receipt_path)->toMatch('#^receipts/'.$this->farm->id.'/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#');
    Storage::disk('local')->assertExists($expense->receipt_path);

    [$w, $h] = getimagesize(Storage::disk('local')->path($expense->receipt_path));
    expect(max($w, $h))->toBe(1600);

    $this->actingAs($this->accountant)->get(route('expenses.receipt', $expense))->assertOk();

    // a PDF is stored as it is; replacing removes the old file
    $old = $expense->receipt_path;
    $this->actingAs($this->accountant)->put(route('expenses.update', $expense), expensePayload([
        'receipt' => UploadedFile::fake()->create('bill.pdf', 200, 'application/pdf'),
    ]))->assertSessionDoesntHaveErrors();
    Storage::disk('local')->assertMissing($old);
    expect($expense->fresh()->receipt_path)->toEndWith('.pdf');

    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload([
        'receipt' => UploadedFile::fake()->create('virus.exe', 10),
    ]))->assertSessionHasErrors('receipt');
});

it('edits an expense (audit-logged) and soft-deletes it', function () {
    $this->actingAs($this->accountant)->post(route('expenses.store'), expensePayload());
    $expense = Expense::firstOrFail();

    $this->actingAs($this->accountant)->get(route('expenses.edit', $expense))->assertOk()->assertSee('Local mandi');

    $this->actingAs($this->accountant)->put(route('expenses.update', $expense), expensePayload(['amount' => '2000']))
        ->assertRedirect(route('expenses.index'));
    expect($expense->fresh()->amount)->toBe('2000.00');

    $log = Activity::where('subject_type', (new Expense)->getMorphClass())->where('event', 'updated')->firstOrFail();
    expect($log->properties['old']['amount'])->toBe('1250.50')->and($log->properties['attributes']['amount'])->toBe('2000.00');

    $this->actingAs($this->accountant)->delete(route('expenses.destroy', $expense))->assertRedirect(route('expenses.index'));
    expect(Expense::count())->toBe(0)->and(Expense::withTrashed()->count())->toBe(1);
    $this->actingAs($this->accountant)->get(route('expenses.index'))->assertDontSee('Local mandi');
});

it('does not allow editing or deleting auto-created expenses from the expense screen', function () {
    $expense = Expense::factory()->create(['farm_id' => $this->farm->id]);
    $expense->forceFill(['source_type' => 'salary_run', 'source_id' => 1])->saveQuietly();

    $this->actingAs($this->accountant)->get(route('expenses.edit', $expense))->assertRedirect(route('expenses.show', $expense));
    $this->actingAs($this->accountant)->put(route('expenses.update', $expense), expensePayload())->assertSessionHas('error');
    $this->actingAs($this->accountant)->delete(route('expenses.destroy', $expense))->assertSessionHas('error');
    expect(Expense::count())->toBe(1);
});

it('filters the list and totals all filtered expenses in SQL', function () {
    $make = fn (array $a) => Expense::factory()->create(array_merge([
        'farm_id' => $this->farm->id, 'category_id' => $this->feed->id, 'section_id' => $this->herd->id,
        'payment_mode_id' => $this->cash->id,
    ], $a));

    $make(['expense_date' => '2026-09-01', 'amount' => '1000.00', 'paid_to_text' => 'Sept feed']);
    $make(['expense_date' => '2026-10-01', 'amount' => '250.25', 'category_id' => $this->health->id, 'paid_to_text' => 'Oct medicine']);
    Expense::factory()->credit($this->vendor)->create([
        'expense_date' => '2026-10-02', 'amount' => '10000.00', 'category_id' => $this->feed->id, 'section_id' => $this->herd->id,
    ]);
    Expense::factory()->create(['farm_id' => Farm::factory()->create()->id, 'paid_to_text' => 'Foreign expense']);

    $this->actingAs($this->accountant)->get(route('expenses.index', ['all' => 1]))
        ->assertSee('Sept feed')->assertDontSee('Foreign expense')
        ->assertSee('₹11,250.25');  // total of the three

    $this->actingAs($this->accountant)->get(route('expenses.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertDontSee('Sept feed')->assertSee('Oct medicine')->assertSee('₹10,250.25');

    $this->actingAs($this->accountant)->get(route('expenses.index', ['category_id' => $this->health->id]))
        ->assertSee('Oct medicine')->assertDontSee('Sept feed');

    $this->actingAs($this->accountant)->get(route('expenses.index', ['type' => 'credit']))
        ->assertSee('Sharma Feeds')->assertDontSee('Oct medicine');

    $this->actingAs($this->accountant)->get(route('expenses.index', ['vendor_id' => $this->vendor->id]))
        ->assertSee('Sharma Feeds')->assertDontSee('Sept feed');

    $totals = app(ExpenseService::class)->totals(
        app(ExpenseService::class)->applyFilters(Expense::query(), [])
    );
    expect($totals)->toBe(['count' => 3, 'total' => '11250.25', 'paid' => '1250.25', 'credit' => '10000.00']);
});

it('opens on the current month by default, and Clear (all=1) shows every date', function () {
    $make = fn (string $date, string $who) => Expense::factory()->create([
        'farm_id' => $this->farm->id, 'expense_date' => $date, 'paid_to_text' => $who,
    ]);
    $make(now()->startOfMonth()->toDateString(), 'This month payee');
    $make(now()->subMonthsNoOverflow(2)->toDateString(), 'Older payee');

    $this->actingAs($this->accountant)->get(route('expenses.index'))
        ->assertSee('This month payee')
        ->assertDontSee('Older payee')
        ->assertSee('value="'.now()->startOfMonth()->toDateString().'"', false)   // the From box shows the default
        ->assertSee('from='.now()->startOfMonth()->toDateString(), false);        // pagination/export keep it

    $this->actingAs($this->accountant)->get(route('expenses.index', ['all' => 1]))
        ->assertSee('This month payee')
        ->assertSee('Older payee');

    // an AJAX request with no filters (dates emptied in the form) also means "all dates"
    $this->actingAs($this->accountant)->withHeaders(['X-Ajax-List' => '1'])->get(route('expenses.index'))
        ->assertSee('Older payee');
});

it('exports the filtered list to Excel', function () {
    Excel::fake();
    Expense::factory()->count(2)->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-10-01']);

    $this->actingAs($this->accountant)->get(route('expenses.export', ['from' => '2026-10-01']))->assertOk();

    Excel::assertDownloaded('expenses-'.now()->format('Ymd-His').'.xlsx', function (ExpensesExport $export) {
        return $export->query()->count() === 2 && count($export->headings()) === 13;
    });
});

it('builds large exports in the background and notifies the user with a download link', function () {
    config(['dairy.exports.queue_above' => 1]);
    Notification::fake();
    Expense::factory()->count(3)->create(['farm_id' => $this->farm->id]);

    $this->actingAs($this->accountant)->from(route('expenses.index'))->get(route('expenses.export'))
        ->assertRedirect(route('expenses.index'))
        ->assertSessionHas('info');

    // queue is "sync" in tests: the file exists and the bell notification was sent
    Notification::assertSentTo($this->accountant, ExportReadyNotification::class, function ($n) {
        $url = $n->toArray($this->accountant)['url'];
        $file = basename($url);
        Storage::disk('local')->assertExists('exports/'.$this->accountant->id.'/'.$file);

        $this->actingAs($this->accountant)->get($url)->assertOk();

        $other = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
        $this->actingAs($other)->get($url)->assertNotFound(); // only the owner of the file

        return true;
    });
});

it('loads subcategories for the dependent dropdown and adds a vendor from the modal', function () {
    $this->actingAs($this->accountant)->getJson(route('expense-categories.children', $this->feed))
        ->assertOk()->assertExactJson([(string) $this->cattleFeed->id => 'Cattle Feed']);

    $this->actingAs($this->accountant)->postJson(route('vendors.quick-store'), ['name' => 'Gupta Medical', 'phone' => '9811122233'])
        ->assertCreated()->assertJsonPath('text', 'Gupta Medical');

    $this->actingAs($this->accountant)->postJson(route('vendors.quick-store'), ['name' => 'Gupta Medical'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('blocks deleting a category or vendor that expenses use', function () {
    Expense::factory()->credit($this->vendor)->create(['category_id' => $this->feed->id, 'subcategory_id' => $this->cattleFeed->id, 'section_id' => $this->herd->id]);

    $this->actingAs($this->accountant)->delete(route('expense-categories.destroy', $this->cattleFeed))->assertSessionHas('error');
    $this->actingAs($this->accountant)->delete(route('vendors.destroy', $this->vendor))->assertSessionHas('error');

    expect(ExpenseCategory::whereKey($this->cattleFeed->id)->exists())->toBeTrue()
        ->and(Vendor::whereKey($this->vendor->id)->exists())->toBeTrue();
});

it('enforces permissions: herd staff may add but not list, edit or delete; other farms are 404', function () {
    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $foreign = Expense::factory()->create(['farm_id' => Farm::factory()->create()->id]);

    $this->actingAs($herdStaff)->post(route('expenses.store'), expensePayload())
        ->assertRedirect(route('expenses.create', ['section_id' => $this->herd->id, 'category_id' => $this->feed->id]));
    $expense = Expense::firstOrFail();

    $this->actingAs($herdStaff)->get(route('expenses.index'))->assertForbidden();
    $this->actingAs($herdStaff)->get(route('expenses.edit', $expense))->assertForbidden();
    $this->actingAs($herdStaff)->delete(route('expenses.destroy', $expense))->assertForbidden();

    $this->actingAs($this->accountant)->get(route('expenses.show', $foreign))->assertNotFound();
    $this->actingAs($this->accountant)->get(route('expenses.receipt', $foreign))->assertNotFound();
});
