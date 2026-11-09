<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Kind of money the business has borrowed (spec §6 Phase 3A); each belongs to one LoanFamily. */
enum LoanType: string
{
    use HasOptions;

    case PersonalLoan = 'personal_loan';
    case BusinessLoan = 'business_loan';
    case DairyAgriLoan = 'dairy_agri_loan';
    case VehicleLoan = 'vehicle_loan';
    case GoldLoan = 'gold_loan';
    case CardEmi = 'card_emi';
    case PrivateLoan = 'private_loan';
    case Other = 'other';
    case Overdraft = 'overdraft';
    case CashCredit = 'cash_credit';
    case Kcc = 'kcc';
    case CreditCard = 'credit_card';

    public function label(): string
    {
        return match ($this) {
            self::PersonalLoan => __('Personal loan'),
            self::BusinessLoan => __('Business loan'),
            self::DairyAgriLoan => __('Dairy / NABARD / agri loan'),
            self::VehicleLoan => __('Vehicle / tractor loan'),
            self::GoldLoan => __('Gold loan'),
            self::CardEmi => __('Credit card EMI'),
            self::PrivateLoan => __('Private / family loan'),
            self::Other => __('Other loan'),
            self::Overdraft => __('Overdraft (OD)'),
            self::CashCredit => __('Cash credit (CC)'),
            self::Kcc => __('Kisan Credit Card (KCC)'),
            self::CreditCard => __('Credit card'),
        };
    }

    public function family(): LoanFamily
    {
        return match ($this) {
            self::Overdraft, self::CashCredit, self::Kcc => LoanFamily::Revolving,
            self::CreditCard => LoanFamily::CreditCard,
            default => LoanFamily::Instalment,
        };
    }

    /** @return list<string> values of one family (form JS, validation) */
    public static function valuesOf(LoanFamily $family): array
    {
        return array_values(array_map(fn (self $t) => $t->value, array_filter(self::cases(), fn (self $t) => $t->family() === $family)));
    }
}
