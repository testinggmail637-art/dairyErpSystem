<?php

use App\Enums\DocumentType;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\RepaymentMethod;
use App\Enums\RoleName;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\Loan;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\CurrentFarm;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
});

it('gives loan permissions to owner, admin and accountant only', function () {
    $loanPerms = ['loans.view', 'loans.manage', 'loan_payments.create', 'loan_payments.edit'];

    foreach ([RoleName::Owner, RoleName::Admin, RoleName::Accountant] as $role) {
        expect(Role::findByName($role->value)->hasAllPermissions($loanPerms))->toBeTrue();
    }
    foreach ([RoleName::HerdStaff, RoleName::CollectionOperator, RoleName::DeliveryBoy] as $role) {
        expect(Role::findByName($role->value)->hasAnyPermission($loanPerms))->toBeFalse();
    }
});

it('grants the loan permissions to existing admin / accountant roles and seeds Finance for existing farms', function () {
    Role::findByName('accountant')->revokePermissionTo(['loans.view', 'loan_payments.create']);
    ExpenseCategory::allFarms()->where('name', 'Finance')->each(function ($c) {
        $c->children()->delete();
        $c->delete();
    });
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    (require database_path('migrations/2026_11_06_200100_grant_loan_permissions_and_finance_category.php'))->up();

    expect(Role::findByName('accountant')->hasAllPermissions(['loans.view', 'loan_payments.create']))->toBeTrue()
        ->and(ExpenseCategory::allFarms()->where('farm_id', $this->farm->id)->where('name', 'Finance')->sole()
            ->children()->pluck('name')->all())->toBe(['Loan Interest', 'Bank Charges']);
});

it('seeds Finance → Loan Interest, Bank Charges for every farm', function () {
    $finance = ExpenseCategory::allFarms()->where('farm_id', $this->farm->id)->whereNull('parent_id')->where('name', 'Finance')->sole();

    expect($finance->children()->orderBy('sort_order')->pluck('name')->all())->toBe(['Loan Interest', 'Bank Charges']);
});

it('numbers loans LOAN/2026-27/0001 per farm and financial year', function () {
    $numbers = app(DocumentNumberService::class);

    expect($numbers->next(DocumentType::Loan, Carbon::parse('2026-11-06')))->toBe('LOAN/2026-27/0001')
        ->and($numbers->next(DocumentType::Loan, Carbon::parse('2027-01-10')))->toBe('LOAN/2026-27/0002')
        ->and($numbers->next(DocumentType::Loan, Carbon::parse('2027-04-01')))->toBe('LOAN/2027-28/0001');
});

it('stores loans per farm with enum and money casts and soft delete', function () {
    $loan = Loan::factory()->create(['farm_id' => $this->farm->id]);
    $od = Loan::factory()->overdraft()->create(['farm_id' => Farm::factory()->create()->id]);

    expect($loan->fresh())->loan_type->toBe(LoanType::BusinessLoan)->repayment_method->toBe(RepaymentMethod::EmiReducing)
        ->status->toBe(LoanStatus::Active)->principal_amount->toBe('100000.00')->interest_rate->toBe('12.00')
        ->and(Loan::pluck('id')->all())->toBe([$loan->id]) // other farm's overdraft not visible
        ->and(Loan::allFarms()->find($od->id)->isOverdraft())->toBeTrue();

    $loan->delete();
    expect(Loan::count())->toBe(0)->and(Loan::withTrashed()->count())->toBe(1);
});

it('shows the Loans menu item only to users with loans.view', function () {
    $owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();

    $this->actingAs($owner)->get(route('profile.edit'))->assertOk()->assertSee(route('loans.index'), false);
    $this->actingAs($herd)->get(route('profile.edit'))->assertOk()->assertDontSee(route('loans.index'), false);
});
