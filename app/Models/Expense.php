<?php

namespace App\Models;

use App\Enums\ExpenseUnit;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An expense (spec Section 6, Phase 1). Saved directly and counted in reports at once — no approval.
 * A credit expense (is_credit) has a vendor and adds to what we owe them; a paid one has a payment mode.
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use Auditable, BelongsToFarm, HasFactory, SoftDeletes, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'expense_date',
        'amount',
        'category_id',
        'subcategory_id',
        'section_id',
        'animal_id',
        'vendor_id',
        'paid_to_text',
        'is_credit',
        'payment_mode_id',
        'quantity',
        'unit',
        'receipt_path',
        'note',
        'import_batch_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'quantity' => 'decimal:2',
            'is_credit' => 'boolean',
            'unit' => ExpenseUnit::class,
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'subcategory_id');
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Animal the expense was for (treatment, feed of one animal …), optional.
     *
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PaymentMode, $this>
     */
    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }

    /** Treatment, feed purchase or salary run that created this expense (later phases). */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Auto-created expenses are changed from their source screen, never from the expense screen (spec 7.1). */
    public function isAutoCreated(): bool
    {
        return $this->source_type !== null;
    }

    /** "Sharma Feeds" or the free-text payee. */
    public function payee(): ?string
    {
        return $this->vendor?->name ?? $this->paid_to_text;
    }
}
