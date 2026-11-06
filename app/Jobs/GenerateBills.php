<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\User;
use App\Notifications\BillsGeneratedNotification;
use App\Services\BillingService;
use App\Support\CurrentFarm;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/** Bulk bill generation (spec: bulk work runs on the queue): a draft for every billable customer, then a bell notification. */
class GenerateBills implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public readonly Farm $farm,
        public readonly User $user,
        public readonly string $from,
        public readonly string $to,
        public readonly string $billDate,
    ) {}

    public function handle(BillingService $billing, CurrentFarm $currentFarm): void
    {
        $currentFarm->set($this->farm);
        Auth::setUser($this->user); // created_by / updated_by and the audit log

        $created = 0;
        $skipped = 0;

        foreach (array_chunk($billing->billableCustomerIds($this->from, $this->to), 100) as $ids) {
            foreach (Customer::query()->withTrashed()->whereKey($ids)->get() as $customer) {
                try {
                    $billing->generate($customer, $this->from, $this->to, $this->billDate);
                    $created++;
                } catch (DomainException) {
                    $skipped++; // e.g. a bill was made for this customer meanwhile
                }
            }
        }

        $this->user->notify(new BillsGeneratedNotification($created, $skipped, $this->from, $this->to));
    }
}
