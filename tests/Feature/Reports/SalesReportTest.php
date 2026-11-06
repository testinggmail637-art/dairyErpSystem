<?php

use App\Enums\B2bCategory;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Farm;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesReportService;
use App\Support\CurrentFarm;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->route = DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Ward 4']);
    $this->cow = Product::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cow Milk']);
    $this->asha = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Asha', 'route_id' => $this->route->id]);
    $this->plant = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Big Plant', 'customer_type' => 'b2b', 'b2b_category' => B2bCategory::DairyPlant]);

    $sale = fn (Customer $c, array $a) => Delivery::factory()->create(array_merge([
        'customer_id' => $c->id, 'product_id' => $this->cow->id, 'status' => 'delivered', 'route_id' => $c->route_id,
    ], $a));
    $sale($this->asha, ['delivery_date' => '2026-10-01', 'shift' => 'morning', 'delivered_qty' => '2.00', 'rate' => '60.00', 'amount' => '120.00']);
    $sale($this->asha, ['delivery_date' => '2026-10-01', 'shift' => 'evening', 'delivered_qty' => '1.00', 'rate' => '60.00', 'amount' => '60.00']);
    $sale($this->asha, ['delivery_date' => '2026-10-02', 'shift' => 'morning', 'status' => 'skipped', 'delivered_qty' => '0.00', 'amount' => '0.00']);
    $sale($this->plant, ['delivery_date' => '2026-10-02', 'shift' => 'morning', 'stock_source' => 'collection', 'delivered_qty' => '300.00',
        'rate' => '48.00', 'amount' => '14400.00', 'fat' => '6.50', 'buyer_measured_qty' => '297.00', 'buyer_fat' => '6.30']);
    $sale($this->plant, ['delivery_date' => '2026-11-02', 'delivered_qty' => '100.00', 'amount' => '4800.00']); // outside the period
});

it('totals delivered sales per customer, route and product from the deliveries', function () {
    $reports = app(SalesReportService::class);

    $customers = $reports->customerWise('2026-10-01', '2026-10-31');
    expect($customers->rows[0])->toBe([$this->plant->code, 'Big Plant', 'Business (B2B)', 1, '300.00', '14400.00', '48.00'])
        ->and($customers->rows[1])->toBe([$this->asha->code, 'Asha', 'Home (B2C)', 2, '3.00', '180.00', '60.00'])
        ->and($customers->totals[5])->toBe('14580.00');

    $routes = $reports->routeWise('2026-10-01', '2026-10-31');
    expect($routes->rows)->toBe([
        ['Ward 4', 1, '2.00', '1.00', '3.00', '180.00'],
        ['No route (one-off / B2B)', 1, '300.00', '0.00', '300.00', '14400.00'],
    ]);

    $products = $reports->productWise('2026-10-01', '2026-10-31');
    expect(array_column($products->rows, 1))->toBe(['Own Farm', 'Collection Center'])
        ->and($products->totals[3])->toBe('303.00');
});

it('reconciles dairy plant dispatches against the plant readings', function () {
    $table = app(SalesReportService::class)->dairyPlantReconciliation('2026-10-01', '2026-10-31');

    expect($table->rows)->toHaveCount(1)
        ->and(array_slice($table->rows[0], 3, 6))->toBe(['300.00', '297.00', '-3.00', '-1.00', '6.50', '6.30'])
        ->and($table->totals[5])->toBe('-3.00');
});

it('shows the reports with filters, exports and the catalogue, for reports.view only', function () {
    $this->actingAs($this->accountant)->get(route('reports.index'))->assertOk()->assertSee('Customer-wise sales')->assertSee('Dairy plant reconciliation');
    $this->get(route('reports.sales', ['customers', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee('Big Plant')->assertSee('₹14,580.00');
    $this->get(route('reports.sales', ['routes', 'from' => '2026-10-01', 'to' => '2026-10-31', 'format' => 'excel']))->assertOk()->assertDownload();
    $this->get(route('reports.sales', ['dairy-plants', 'from' => '2026-10-01', 'to' => '2026-10-31', 'format' => 'pdf']))->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $boy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->actingAs($boy)->get(route('reports.sales', 'customers'))->assertForbidden();
});
