<?php

namespace App\Exceptions;

use App\Enums\MilkSource;
use App\Enums\MilkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * An outgoing movement needs more milk than the stock holds on that date and the farm does not allow
 * negative stock (spec: MilkStockService::recordOut). Shown to the user like a validation error on `quantity`.
 */
class InsufficientMilkStockException extends RuntimeException
{
    public function __construct(
        public readonly MilkSource $source,
        public readonly MilkType $milkType,
        public readonly string $date,
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct(__('Not enough :type milk in :source stock on :date: :available L available, :requested L needed.', [
            'type' => mb_strtolower($milkType->label()),
            'source' => $source->label(),
            'date' => fmt_date($date),
            'available' => fmt_qty($available),
            'requested' => fmt_qty($requested),
        ]));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'errors' => ['quantity' => [$this->getMessage()]]], 422);
        }

        return back()->withInput()->withErrors(['quantity' => $this->getMessage()]);
    }
}
