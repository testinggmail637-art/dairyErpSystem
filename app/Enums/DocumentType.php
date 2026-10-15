<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Numbered documents (spec Section 4.6): BILL/2026-27/0001 … */
enum DocumentType: string
{
    use HasOptions;

    case Bill = 'bill';
    case FarmerPayment = 'farmer_payment';
    case AnimalPurchase = 'animal_purchase';
    case AnimalSale = 'animal_sale';
    case Salary = 'salary';

    public function label(): string
    {
        return match ($this) {
            self::Bill => __('Customer Bill'),
            self::FarmerPayment => __('Farmer Payment'),
            self::AnimalPurchase => __('Animal Purchase'),
            self::AnimalSale => __('Animal Sale'),
            self::Salary => __('Salary Slip'),
        };
    }

    /** First part of the document number. */
    public function prefix(): string
    {
        return match ($this) {
            self::Bill => 'BILL',
            self::FarmerPayment => 'FPAY',
            self::AnimalPurchase => 'APUR',
            self::AnimalSale => 'ASAL',
            self::Salary => 'SAL',
        };
    }
}
