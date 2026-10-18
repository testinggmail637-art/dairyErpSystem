<?php

use App\Enums\ImportBatchStatus;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseImportBatch;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-10-18 11:00');

    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(['name' => 'Asha Accountant']);

    $feed = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Feed']);
    ExpenseCategory::factory()->childOf($feed)->create(['name' => 'Cattle Feed']);
    Section::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Own Herd']);
    PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);

    $this->actingAs($this->accountant);
});

function importedBatch(int $rows = 3): ExpenseImportBatch
{
    $batch = uploadImport(importCsv(array_map(fn ($i) => importRow(['amount' => (string) (100 * $i)]), range(1, $rows))));
    test()->post(route('expense-imports.import', $batch))->assertSessionHas('success');

    return $batch->fresh();
}

it('lists uploads newest first with their counts, status and links', function () {
    $imported = importedBatch();
    $this->travel(5)->minutes();
    $previewed = uploadImport(importCsv([importRow(), importRow(['amount' => 'x'])]));

    $response = $this->get(route('expense-imports.index'))->assertOk()
        ->assertSeeInOrder([route('expense-imports.show', $previewed), route('expense-imports.show', $imported)], false)
        ->assertSee('Asha Accountant')
        ->assertSee('Checked, not imported yet')
        ->assertSee(route('expenses.index', ['import_batch_id' => $imported->id]), false)
        ->assertDontSee(route('expenses.index', ['import_batch_id' => $previewed->id]), false)
        ->assertSee(route('expense-imports.revert', $imported), false)
        ->assertDontSee(route('expense-imports.revert', $previewed), false);

    // AJAX paging returns only the history fragment
    $this->get(route('expense-imports.index', ['page' => 1]), ['X-Ajax-List' => '1'])
        ->assertOk()->assertHeader('X-Ajax-List-Fragment', '1')->assertDontSee('Download the template');
});

it('keeps the history page flat on queries as uploads grow', function () {
    importedBatch(1);
    DB::enableQueryLog();
    $this->get(route('expense-imports.index'))->assertOk();
    $few = count(DB::getQueryLog());

    foreach (range(1, 5) as $i) {
        importedBatch(1);
    }
    DB::flushQueryLog();
    $this->get(route('expense-imports.index'))->assertOk();

    expect(count(DB::getQueryLog()))->toBe($few);
});

it('links to the imported expenses through an expense list filter', function () {
    $batch = importedBatch(2);
    Expense::factory()->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-10-15', 'amount' => '999.00']);

    $this->get(route('expenses.index', ['import_batch_id' => $batch->id]))->assertOk()
        ->assertSee("Showing the expenses of import #{$batch->id}.")
        ->assertSee('data-clearable', false)
        ->assertSee('₹100.00')->assertSee('₹200.00')->assertDontSee('₹999.00');
});

it('reverts an imported upload: soft-deletes its expenses, with a reason in the audit log', function () {
    $batch = importedBatch(3);
    $other = Expense::factory()->create(['farm_id' => $this->farm->id]);

    $this->post(route('expense-imports.revert', $batch))->assertSessionHasErrors('reason');
    expect(Expense::count())->toBe(4);

    $this->post(route('expense-imports.revert', $batch), ['reason' => 'Uploaded the wrong month'])
        ->assertRedirect(route('expense-imports.show', $batch))
        ->assertSessionHas('success', 'Upload reverted: 3 expenses deleted.');

    $batch->refresh();
    expect($batch->status)->toBe(ImportBatchStatus::Reverted)
        ->and($batch->reverted_by)->toBe($this->accountant->id)
        ->and($batch->reverted_at)->not->toBeNull()
        ->and(Expense::pluck('id')->all())->toBe([$other->id])
        ->and(Expense::onlyTrashed()->where('import_batch_id', $batch->id)->count())->toBe(3);

    $log = Activity::query()->where('subject_type', $batch->getMorphClass())->latest('id')->first();
    expect($log->reason())->toBe('Uploaded the wrong month')
        ->and($log->properties['attributes']['status'])->toBe('reverted');

    $this->get(route('expense-imports.show', $batch))->assertOk()
        ->assertSee('Reverted on')
        ->assertDontSee(route('expense-imports.revert', $batch), false);

    // never twice
    $this->post(route('expense-imports.revert', $batch), ['reason' => 'Again'])
        ->assertSessionHas('error', 'Only an imported upload can be reverted.');
});

it('refuses to revert when an imported expense was edited after the import', function () {
    $batch = importedBatch(3);
    $this->travel(1)->minutes();
    Expense::where('import_batch_id', $batch->id)->first()->update(['note' => 'checked']);

    $this->post(route('expense-imports.revert', $batch), ['reason' => 'Wrong file'])
        ->assertSessionHas('error', '1 expense from this upload was edited after the import, so the upload cannot be reverted. Delete the expenses one by one instead.');

    expect(Expense::count())->toBe(3)->and($batch->fresh()->status)->toBe(ImportBatchStatus::Imported);
});

it('still reverts when some imported expenses were already deleted one by one', function () {
    $batch = importedBatch(3);
    $this->travel(1)->minutes();
    Expense::where('import_batch_id', $batch->id)->first()->delete();

    $this->post(route('expense-imports.revert', $batch), ['reason' => 'Wrong file'])
        ->assertSessionHas('success', 'Upload reverted: 2 expenses deleted.');

    expect(Expense::count())->toBe(0);
});

it('allows a revert only within 7 days of the import', function () {
    $batch = importedBatch(2); // imported 18-10-2026 11:00 → revert until 25-10-2026 11:00

    $this->get(route('expense-imports.show', $batch))->assertSee('It can be reverted until 25-10-2026');
    $this->get(route('expense-imports.index'))->assertSee('Revert until 25-10-2026');

    $this->travelTo('2026-10-25 11:01');
    $this->get(route('expense-imports.index'))->assertOk()->assertDontSee(route('expense-imports.revert', $batch), false);
    $this->get(route('expense-imports.show', $batch))->assertOk()
        ->assertSee('The 7-day revert period ended on 25-10-2026')
        ->assertDontSee(route('expense-imports.revert', $batch), false);

    $this->post(route('expense-imports.revert', $batch), ['reason' => 'Too late'])
        ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'An upload can be reverted only within 7 days of the import (until 25-10-2026'));
    expect(Expense::count())->toBe(2)->and($batch->fresh()->status)->toBe(ImportBatchStatus::Imported);
});

it('reverts on the last day of the window, and the window length comes from config', function () {
    $batch = importedBatch(1);

    $this->travelTo('2026-10-25 10:59');
    $this->post(route('expense-imports.revert', $batch), ['reason' => 'Just in time'])->assertSessionHas('success');

    config(['dairy.imports.revert_days' => 1]);
    $this->travelTo('2026-10-25 11:00');
    $second = importedBatch(1);
    $this->travelTo('2026-10-26 11:01');
    $this->post(route('expense-imports.revert', $second), ['reason' => 'Late'])->assertSessionHas('error');
});

it('only reverts imported uploads', function () {
    $previewed = uploadImport(importCsv([importRow()]));

    $this->post(route('expense-imports.revert', $previewed), ['reason' => 'Nope'])
        ->assertSessionHas('error', 'Only an imported upload can be reverted.');
});

it('needs expenses.import and expenses.delete to revert, and stays inside the farm', function () {
    $batch = importedBatch(1);

    $importOnly = Role::create(['name' => 'importer', 'guard_name' => 'web'])->givePermissionTo('expenses.import', 'expenses.view');
    $importer = User::factory()->forFarm($this->farm)->create();
    $importer->assignRole($importOnly);
    $this->flushSession();
    $this->actingAs($importer)->get(route('expense-imports.index'))->assertOk()->assertDontSee(route('expense-imports.revert', $batch), false);
    $this->actingAs($importer)->post(route('expense-imports.revert', $batch), ['reason' => 'Try'])->assertForbidden();

    $other = User::factory()->forFarm(Farm::factory()->create())->withRole(RoleName::Accountant)->create();
    $this->flushSession();
    $this->actingAs($other)->get(route('expense-imports.index'))->assertOk()->assertDontSee(route('expense-imports.show', $batch), false);
    $this->actingAs($other)->post(route('expense-imports.revert', $batch), ['reason' => 'Try'])->assertNotFound();

    expect(Expense::allFarms()->count())->toBe(1); // still there
});
