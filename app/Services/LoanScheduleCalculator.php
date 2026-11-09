<?php

namespace App\Services;

use App\Enums\InstallmentFrequency;
use App\Enums\MoratoriumInterest;
use App\Enums\RepaymentMethod;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * Instalment schedule (spec 7.4A), bcmath only. Period rate r = annual % × months per period ÷ 1200.
 * - emi_reducing: EMI = P·r·(1+r)ⁿ ÷ ((1+r)ⁿ − 1) (r = 0 → P ÷ n); interest = opening × r, principal = EMI − interest.
 * - emi_flat: total interest = P × rate × repayment months ÷ 1200, EMI = (P + total interest) ÷ n, equal interest.
 * - interest_only: interest = P × r every period, the whole principal with the last instalment.
 * - bullet: one instalment at maturity = P + P × rate × months ÷ 1200 (simple interest).
 * - Moratorium (tenure includes it): `paid` → interest-only instalments first; `capitalised` → the interest is added
 *   to the principal (compounded per period) and repayment starts after it.
 * - Broken-period interest → instalment 0 (interest only, due on the first due date).
 * EMI is rounded to the rupee; the last instalment takes the rounding difference so Σ principal = principal exactly.
 * Due dates = first due date + k periods (31st → last day of a short month).
 */
class LoanScheduleCalculator
{
    private const SCALE = 14;

    /**
     * @return array{emi: string, principal: string, rows: list<array{installment_no: int, due_date: string, opening_principal: string, emi_amount: string, principal_part: string, interest_part: string, closing_principal: string}>}
     */
    public function schedule(
        RepaymentMethod $method,
        string $principal,
        string $annualRate,
        int $tenureMonths,
        CarbonInterface $firstDue,
        InstallmentFrequency $frequency = InstallmentFrequency::Monthly,
        int $moratoriumMonths = 0,
        ?MoratoriumInterest $moratoriumInterest = null,
        string $brokenPeriodInterest = '0',
    ): array {
        $step = $frequency->months();
        $r = bcdiv(bcmul($annualRate, (string) $step, self::SCALE), '1200', self::SCALE);
        $principal = Money::round2($principal);

        if ($method === RepaymentMethod::Bullet) {
            $interest = Money::round2(bcdiv(bcmul(bcmul($principal, $annualRate, self::SCALE), (string) $tenureMonths, self::SCALE), '1200', self::SCALE));

            return ['emi' => Money::add($principal, $interest), 'principal' => $principal,
                'rows' => [$this->row(1, $firstDue, 0, 1, $principal, $principal, $interest)]];
        }

        $rows = [];
        $no = 1;
        $moratoriumPeriods = intdiv($moratoriumMonths, $step);

        if ($moratoriumPeriods > 0 && $moratoriumInterest === MoratoriumInterest::Capitalised) {
            $principal = Money::round2(bcmul($principal, bcpow(bcadd('1', $r, self::SCALE), (string) $moratoriumPeriods, self::SCALE), self::SCALE));
        } elseif ($moratoriumPeriods > 0) {
            $interest = Money::round2(bcmul($principal, $r, self::SCALE));
            for ($k = 0; $k < $moratoriumPeriods; $k++) {
                $rows[] = $this->row($no++, $firstDue, $k, $step, $principal, '0.00', $interest);
            }
        }

        // Capitalised: first_due_date is the first EMI; paid: repayment continues after the interest-only periods.
        $offset = $moratoriumInterest === MoratoriumInterest::Capitalised ? 0 : $moratoriumPeriods;
        $n = intdiv($tenureMonths - $moratoriumMonths, $step);

        $repayment = match ($method) {
            RepaymentMethod::EmiReducing => $this->reducing($principal, $r, $n),
            RepaymentMethod::EmiFlat => $this->flat($principal, $annualRate, $n, $n * $step),
            RepaymentMethod::InterestOnly => $this->interestOnly($principal, $r, $n),
        };

        foreach ($repayment['lines'] as $k => [$opening, $part, $interest]) {
            $rows[] = $this->row($no++, $firstDue, $offset + $k, $step, $opening, $part, $interest);
        }

        if (Money::compare($brokenPeriodInterest, '0') > 0) {
            array_unshift($rows, $this->row(0, $firstDue, 0, $step, $principal, '0.00', Money::round2($brokenPeriodInterest)));
        }

        return ['emi' => $repayment['emi'], 'principal' => $principal, 'rows' => $rows];
    }

    /**
     * Reducing-balance rows with a fixed EMI until the principal is paid (prepayment / rate change "keep EMI"):
     * the last instalment is whatever is left. Null when the EMI does not even cover the first period's interest.
     *
     * @return list<array{installment_no: int, due_date: string, opening_principal: string, emi_amount: string, principal_part: string, interest_part: string, closing_principal: string}>|null
     */
    public function reducingWithEmi(string $principal, string $annualRate, string $emi, CarbonInterface $firstDue, InstallmentFrequency $frequency, int $maxPeriods = 480): ?array
    {
        $r = bcdiv(bcmul($annualRate, (string) $frequency->months(), self::SCALE), '1200', self::SCALE);
        $opening = Money::round2($principal);

        if (Money::compare($emi, Money::round2(bcmul($opening, $r, self::SCALE))) <= 0) {
            return null;
        }

        $rows = [];
        for ($k = 0; Money::compare($opening, '0') > 0 && $k < $maxPeriods; $k++) {
            $interest = Money::round2(bcmul($opening, $r, self::SCALE));
            $part = $this->min(Money::sub($emi, $interest), $opening);
            $rows[] = $this->row($k + 1, $firstDue, $k, $frequency->months(), $opening, $part, $interest);
            $opening = Money::sub($opening, $part);
        }

        return $rows;
    }

    /** Interest for the days between the start date and the start of the first instalment period (P × rate × days ÷ 36500). */
    public function brokenPeriodInterest(string $principal, string $annualRate, CarbonInterface $start, CarbonInterface $firstPeriodStart): string
    {
        $days = (int) $start->copy()->startOfDay()->diffInDays($firstPeriodStart->copy()->startOfDay(), false);

        return $days <= 0 ? '0.00'
            : Money::round2(bcdiv(bcmul(bcmul($principal, $annualRate, self::SCALE), (string) $days, self::SCALE), '36500', self::SCALE));
    }

    /** EMI of a reducing-balance loan, rounded to the rupee. */
    public function reducingEmi(string $principal, string $periodRate, int $periods): string
    {
        if (bccomp($periodRate, '0', self::SCALE) === 0) {
            return $this->rupee(bcdiv($principal, (string) $periods, self::SCALE));
        }

        $f = bcpow(bcadd('1', $periodRate, self::SCALE), (string) $periods, self::SCALE);

        return $this->rupee(bcdiv(bcmul(bcmul($principal, $periodRate, self::SCALE), $f, self::SCALE), bcsub($f, '1', self::SCALE), self::SCALE));
    }

    /** @return array{emi: string, lines: list<array{0: string, 1: string, 2: string}>} opening, principal part, interest */
    private function reducing(string $principal, string $r, int $n): array
    {
        $emi = $this->reducingEmi($principal, $r, $n);
        $lines = [];
        $opening = $principal;

        for ($k = 1; $k <= $n; $k++) {
            $interest = Money::round2(bcmul($opening, $r, self::SCALE));
            $part = $k === $n ? $opening : $this->min(Money::sub($emi, $interest), $opening);
            $lines[] = [$opening, $part, $interest];
            $opening = Money::sub($opening, $part);
        }

        return ['emi' => $emi, 'lines' => $lines];
    }

    /** @return array{emi: string, lines: list<array{0: string, 1: string, 2: string}>} */
    private function flat(string $principal, string $annualRate, int $n, int $months): array
    {
        $totalInterest = Money::round2(bcdiv(bcmul(bcmul($principal, $annualRate, self::SCALE), (string) $months, self::SCALE), '1200', self::SCALE));
        $emi = $this->rupee(bcdiv(bcadd($principal, $totalInterest, self::SCALE), (string) $n, self::SCALE));
        $periodInterest = Money::round2(bcdiv($totalInterest, (string) $n, self::SCALE));
        $lines = [];
        $opening = $principal;

        for ($k = 1; $k <= $n; $k++) {
            $last = $k === $n;
            $interest = $last ? Money::sub($totalInterest, Money::mul($periodInterest, (string) ($n - 1))) : $periodInterest;
            $part = $last ? $opening : $this->min(Money::sub($emi, $interest), $opening);
            $lines[] = [$opening, $part, $interest];
            $opening = Money::sub($opening, $part);
        }

        return ['emi' => $emi, 'lines' => $lines];
    }

    /** @return array{emi: string, lines: list<array{0: string, 1: string, 2: string}>} */
    private function interestOnly(string $principal, string $r, int $n): array
    {
        $interest = Money::round2(bcmul($principal, $r, self::SCALE));
        $lines = [];

        for ($k = 1; $k <= $n; $k++) {
            $lines[] = [$principal, $k === $n ? $principal : '0.00', $interest];
        }

        return ['emi' => $interest, 'lines' => $lines];
    }

    /** @return array{installment_no: int, due_date: string, opening_principal: string, emi_amount: string, principal_part: string, interest_part: string, closing_principal: string} */
    private function row(int $no, CarbonInterface $firstDue, int $k, int $step, string $opening, string $principalPart, string $interest): array
    {
        return [
            'installment_no' => $no,
            'due_date' => $firstDue->copy()->addMonthsNoOverflow($k * $step)->toDateString(),
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
