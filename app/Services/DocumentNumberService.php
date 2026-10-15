<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Support\CurrentFarm;
use App\Support\FinancialYear;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gap-free document numbers per farm, type and financial year: BILL/2026-27/0001 (spec Section 4.6).
 *
 * Call it inside the same DB transaction that saves the document: if that transaction rolls back,
 * the number is released too. The sequence row is locked with SELECT … FOR UPDATE, so two users
 * saving at the same moment never get the same number.
 */
class DocumentNumberService
{
    public function __construct(private readonly CurrentFarm $currentFarm) {}

    /** Next number for $type in the financial year of $date (the document date; today when null). */
    public function next(DocumentType $type, ?CarbonInterface $date = null): string
    {
        $farmId = $this->currentFarm->id()
            ?? throw new LogicException('No current farm: cannot issue a document number.');

        $financialYear = FinancialYear::label($date);

        $number = DB::transaction(function () use ($farmId, $type, $financialYear) {
            $keys = ['farm_id' => $farmId, 'type' => $type->value, 'financial_year' => $financialYear];

            // Create the row on first use; the unique index makes a concurrent insert a no-op.
            DocumentSequence::allFarms()->insertOrIgnore($keys + [
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::allFarms()->where($keys)->lockForUpdate()->firstOrFail();
            $sequence->increment('last_number');

            return $sequence->last_number;
        });

        return self::format($type, $financialYear, $number);
    }

    public static function format(DocumentType $type, string $financialYear, int $number): string
    {
        return $type->prefix().'/'.$financialYear.'/'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
