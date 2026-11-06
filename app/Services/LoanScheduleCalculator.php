<?php

namespace App\Services;

use App\Enums\RepaymentMethod;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * EMI / interest schedule (spec 7.4A), bcmath only. Monthly rate r = annual % ÷ 12 ÷ 100.
 * - emi_reducing: EMI = P·r·(1+r)ⁿ ÷ ((1+r)ⁿ − 1) (r = 0 → P ÷ n); interest = opening × r, principal = EMI − interest.
 * - emi_flat: total interest = P × rate × n ÷ 1200, EMI = (P + total interest) ÷ n, equal interest each month.
 * - interest_only: interest = P × r every month, the whole principal with the last installment.
 * EMI is rounded to the rupee; the last installment takes the rounding difference so Σ principal = P exactly.
 * Due dates = first due date + k months (31st → last day of a short month).
 */
class LoanScheduleCalculator
{
    private const SCALE = 14;

    /**
     * @return array{emi: string, rows: list<array{installment_no: int, due_date: string, opening_principal: string, emi_amount: string, principal_part: string, interest_part: string, closing_principal: string}>}
     */
    public function schedule(RepaymentMethod $method, string $principal, string $annualRate, int $months, CarbonInterface $firstDue, int $firstNo = 1): array
    {
        $r = bcdiv($annualRate, '1200', self::SCALE);

        return match ($method) {
            RepaymentMethod::EmiReducing => $this->reducing($principal, $r, $months, $firstDue, $firstNo),
            RepaymentMethod::EmiFlat => $this->flat($principal, $annualRate, $months, $firstDue, $firstNo),
            RepaymentMethod::InterestOnly => $this->interestOnly($principal, $r, $months, $firstDue, $firstNo),
        };
    }

    /** EMI of a reducing-balance loan, rounded to the rupee. */
    public function reducingEmi(string $principal, string $monthlyRate, int $months): string
    {
        if (bccomp($monthlyRate, '0', self::SCALE) === 0) {
            return $this->rupee(bcdiv($principal, (string) $months, self::SCALE));
        }

        $f = bcpow(bcadd('1', $monthlyRate, self::SCALE), (string) $months, self::SCALE);

        return $this->rupee(bcdiv(bcmul(bcmul($principal, $monthlyRate, self::SCALE), $f, self::SCALE), bcsub($f, '1', self::SCALE), self::SCALE));
    }

    private function reducing(string $principal, string $r, int $months, CarbonInterface $firstDue, int $firstNo): array
    {
        $emi = $this->reducingEmi($principal, $r, $months);
        $rows = [];
        $opening = Money::round2($principal);

        for ($k = 1; $k <= $months; $k++) {
            $interest = Money::round2(bcmul($opening, $r, self::SCALE));
            $part = $k === $months ? $opening : $this->min(Money::sub($emi, $interest), $opening);
            $rows[] = $this->row($firstNo + $k - 1, $firstDue, $k, $opening, $part, $interest);
            $opening = Money::sub($opening, $part);
        }

        return ['emi' => $emi, 'rows' => $rows];
    }

    private function flat(string $principal, string $annualRate, int $months, CarbonInterface $firstDue, int $firstNo): array
    {
        $totalInterest = Money::round2(bcdiv(bcmul(bcmul($principal, $annualRate, self::SCALE), (string) $months, self::SCALE), '1200', self::SCALE));
        $emi = $this->rupee(bcdiv(bcadd($principal, $totalInterest, self::SCALE), (string) $months, self::SCALE));
        $monthlyInterest = Money::round2(bcdiv($totalInterest, (string) $months, self::SCALE));
        $rows = [];
        $opening = Money::round2($principal);

        for ($k = 1; $k <= $months; $k++) {
            $last = $k === $months;
            $interest = $last ? Money::sub($totalInterest, Money::mul($monthlyInterest, (string) ($months - 1))) : $monthlyInterest;
            $part = $last ? $opening : $this->min(Money::sub($emi, $interest), $opening);
            $rows[] = $this->row($firstNo + $k - 1, $firstDue, $k, $opening, $part, $interest);
            $opening = Money::sub($opening, $part);
        }

        return ['emi' => $emi, 'rows' => $rows];
    }

    private function interestOnly(string $principal, string $r, int $months, CarbonInterface $firstDue, int $firstNo): array
    {
        $interest = Money::round2(bcmul($principal, $r, self::SCALE));
        $opening = Money::round2($principal);
        $rows = [];

        for ($k = 1; $k <= $months; $k++) {
            $rows[] = $this->row($firstNo + $k - 1, $firstDue, $k, $opening, $k === $months ? $opening : '0.00', $interest);
        }

        return ['emi' => $interest, 'rows' => $rows];
    }

    /** @return array{installment_no: int, due_date: string, opening_principal: string, emi_amount: string, principal_part: string, interest_part: string, closing_principal: string} */
    private function row(int $no, CarbonInterface $firstDue, int $k, string $opening, string $principalPart, string $interest): array
    {
        return [
            'installment_no' => $no,
            'due_date' => $firstDue->copy()->addMonthsNoOverflow($k - 1)->toDateString(),
            'opening_principal' => $opening,
            'emi_amount' => Money::add($principalPart, $interest),
            'principal_part' => Money::round2($principalPart),
            'interest_part' => $interest,
            'closing_principal' => Money::sub($opening, $principalPart),
        ];
    }

    private function rupee(string $value): string
    {
        return Money::round2(Money::round($value, 0));
    }

    private function min(string $a, string $b): string
    {
        return Money::compare($a, $b) <= 0 ? $a : $b;
    }
}
