<?php

use App\Enums\AnimalStatus;
use App\Enums\RoleName;
use App\Exports\ReportTableExport;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\User;
use App\Services\MilkReportService;
use App\Support\CurrentFarm;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->travelTo('2026-10-20 18:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->reports = app(MilkReportService::class);

    $make = fn (string $tag, ?string $name = null) => Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => $tag, 'name' => $name]);
    $this->a = $make('C-1', 'Gauri');
    $this->b = $make('C-2');
    $this->c = Animal::factory()->buffalo()->create(['farm_id' => $this->farm->id, 'tag_no' => 'B-1', 'name' => null]);

    // 20-10: 8+6, 10+8 (cows), 6+5 (buffalo) = 43 L — the Phase 2 acceptance numbers
    $milk = fn (Animal $animal, string $date, string $shift, string $litres) => MilkProductionRecord::factory()->for($animal)
        ->create(['production_date' => $date, 'shift' => $shift, 'quantity_litre' => $litres]);
    foreach ([[$this->a, '8', '6'], [$this->b, '10', '8'], [$this->c, '6', '5']] as [$animal, $m, $e]) {
        $milk($animal, '2026-10-20', 'morning', $m);
        $milk($animal, '2026-10-20', 'evening', $e);
    }
    $milk($this->a, '2026-10-18', 'morning', '9.50');
    $milk($this->c, '2026-10-18', 'evening', '4.25');
    $milk($this->a, '2026-09-30', 'morning', '7'); // previous month

    // another farm's milk never shows
    $other = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'tag_no' => 'X-9']);
    MilkProductionRecord::factory()->for($other)->create(['production_date' => '2026-10-20', 'quantity_litre' => '50']);
});

it('builds the daily report: animal × shift with a subtotal per milk type (43 L)', function () {
    $t = $this->reports->daily('2026-10-20');

    expect($t->rows)->toBe([
        ['C-1 · Gauri', 'Cow', '8.00', '6.00', '14.00'],
        ['C-2', 'Cow', '10.00', '8.00', '18.00'],
        ['Cow total (2)', '', '18.00', '14.00', '32.00'],
        ['B-1', 'Buffalo', '6.00', '5.00', '11.00'],
        ['Buffalo total (1)', '', '6.00', '5.00', '11.00'],
    ])->and($t->strongRows)->toBe([2, 4])
        ->and($t->totals)->toBe(['Total (3)', '', '24.00', '19.00', '43.00'])
        ->and($t->period)->toBe('20-10-2026');

    $buffalo = $this->reports->daily('2026-10-20', 'buffalo');
    expect($buffalo->totals)->toBe(['Total (1)', '', '6.00', '5.00', '11.00'])->and($buffalo->period)->toBe('20-10-2026 · Buffalo');

    expect($this->reports->daily('2026-10-19')->rows)->toBe([]);
});

it('builds the monthly report day by day up to today, with zero days', function () {
    $t = $this->reports->monthly('2026-10');

    expect($t->rows)->toHaveCount(20) // 1st … 20th (today)
        ->and($t->rows[17])->toBe(['2026-10-18', '9.50', '4.25', '9.50', '4.25', '13.75', 2])
        ->and($t->rows[18])->toBe(['2026-10-19', '0.00', '0.00', '0.00', '0.00', '0.00', 0])
        ->and($t->rows[19])->toBe(['2026-10-20', '32.00', '11.00', '24.00', '19.00', '43.00', 3])
        ->and($t->totals)->toBe(['Total', '41.50', '15.25', '33.50', '23.25', '56.75', ''])
        ->and($t->period)->toBe('October 2026');

    expect($this->reports->monthly('2026-09')->rows)->toHaveCount(30)
        ->and($this->reports->monthly('2026-09')->totals[5])->toBe('7.00')
        ->and($this->reports->monthly('2026-10', 'cow')->totals)->toBe(['Total', '41.50', '0.00', '27.50', '14.00', '41.50', '']);
});

it('builds the animal-wise report, largest first, including sold animals', function () {
    $this->b->update(['status' => AnimalStatus::Sold]); // still has milk in the period

    $t = $this->reports->animalWise('2026-10-01', '2026-10-20');

    expect($t->rows)->toBe([
        ['C-1 · Gauri', 'Cow', 2, '17.50', '6.00', '23.50', '11.75'],
        ['C-2', 'Cow', 1, '10.00', '8.00', '18.00', '18.00'],
        ['B-1', 'Buffalo', 2, '6.00', '9.25', '15.25', '7.63'],
    ])->and($t->totals)->toBe(['Total (3)', '', '', '33.50', '23.25', '56.75', '']);

    expect($this->reports->animalWise('2026-10-01', '2026-10-20', 'buffalo')->rows)->toHaveCount(1);
});

it('shows the milk reports on screen with filters, and downloads Excel and PDF', function () {
    Excel::fake();
    $this->actingAs($this->accountant);

    $this->get(route('reports.index'))->assertOk()
        ->assertSee('Milk production')->assertSee(route('reports.milk', 'daily'), false)
        ->assertSee(route('reports.milk', 'animals'), false);

    $this->get(route('reports.milk', 'daily'))->assertOk() // defaults to today
        ->assertSee('name="date"', false)->assertSee('name="milk_type"', false)
        ->assertSeeInOrder(['C-1 · Gauri', 'C-2', 'Cow total (2)', '32.00', 'B-1', 'Buffalo total (1)', 'Total (3)', '43.00']);

    $this->get(route('reports.milk', ['monthly', 'month' => '2026-10']))->assertOk()
        ->assertSee('October 2026')->assertSee('19-10-2026')->assertSee('56.75');

    $this->withHeaders(['X-Ajax-List' => '1'])->get(route('reports.milk', ['animals', 'from' => '2026-10-01', 'to' => '2026-10-20', 'milk_type' => 'cow']))
        ->assertOk()->assertHeader('X-Ajax-List-Fragment', '1')->assertSee('C-2')->assertDontSee('B-1')
        ->assertSee(route('reports.milk', ['animals', 'from' => '2026-10-01', 'to' => '2026-10-20', 'milk_type' => 'cow', 'format' => 'excel']));

    $this->get(route('reports.milk', ['daily', 'date' => '2026-10-19']))->assertSee('No milk entries on this day.');

    $this->get(route('reports.milk', ['daily', 'date' => '2026-10-20', 'format' => 'excel']))->assertOk();
    Excel::assertDownloaded('daily-milk-production-20-10-2026.xlsx', function (ReportTableExport $export) {
        $rows = $export->array();

        return $rows[0] === ['Daily milk production'] && end($rows) === ['Total (3)', '', '24.00', '19.00', '43.00']
            && $export->columnFormats() === ['C' => '#,##0.00', 'D' => '#,##0.00', 'E' => '#,##0.00'];
    });

    $this->get(route('reports.milk', ['monthly', 'month' => '2026-10', 'format' => 'pdf']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('validates filters and needs reports.view', function () {
    $this->actingAs($this->accountant)->get(route('reports.milk', ['animals', 'from' => '2026-10-20', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
    $this->get(route('reports.milk', ['daily', 'milk_type' => 'goat']))->assertSessionHasErrors('milk_type');
    $this->get('/reports/milk/weekly')->assertNotFound();

    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($staff)->get(route('reports.milk', 'daily'))->assertForbidden();
});
