<?php

namespace App\Actions\Cash;

use App\Exceptions\InvalidCashMovement;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\User;
use App\Support\CashBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CloseCashBox
{
    public function __construct(private readonly CashBalance $balance) {}

    /**
     * Close the cash box on a date: record what was counted against what the books expect. A difference is
     * written as one adjustment movement dated that day (money in for a surplus, out for a shortage), so the
     * books agree with the count. From then on nothing dated on or before that day can be recorded or
     * cancelled. The closing, its adjustment and its trace are written together.
     *
     * @param  array<string, mixed>  $data  closed_on, counted_amount, notes.
     *
     * @throws InvalidCashMovement when the date is in the future or not after the last closing
     */
    public function handle(array $data, ?User $user = null): CashClosing
    {
        $closedOn = Carbon::parse((string) $data['closed_on'])->startOfDay();

        return DB::transaction(function () use ($data, $user, $closedOn): CashClosing {
            if ($closedOn->greaterThan(today())) {
                throw new InvalidCashMovement('closed_on', 'cash.errors.closing_future');
            }

            $last = CashClosing::lastClosedOn();

            if ($last !== null && $closedOn->lessThanOrEqualTo($last)) {
                throw new InvalidCashMovement('closed_on', 'cash.errors.closing_not_after_last');
            }

            $expected = $this->balance->asOf($closedOn);
            $counted = (int) $data['counted_amount'];
            $difference = $counted - $expected;

            $closing = new CashClosing([
                'closed_on' => $closedOn,
                'expected_amount' => $expected,
                'counted_amount' => $counted,
                'difference' => $difference,
                'notes' => $data['notes'] ?? null,
                'closed_by' => $user?->id,
            ]);
            $closing->save();
            $closing->forceFill(['reference' => sprintf('CLO-%04d', $closing->id)])->save();

            if ($difference !== 0) {
                $adjustment = new CashMovement([
                    'closing_id' => $closing->id,
                    'direction' => $difference > 0 ? CashMovement::IN : CashMovement::OUT,
                    'amount' => abs($difference),
                    'occurred_on' => $closedOn,
                    'label' => __('cash.closing_adjustment', ['date' => $closedOn->toDateString()]),
                    'notes' => $data['notes'] ?? null,
                    'recorded_by' => $user?->id,
                ]);
                $adjustment->save();
                $adjustment->forceFill(['reference' => sprintf('CAI-%04d', $adjustment->id)])->save();
            }

            AuditLog::record('cash_closing.recorded', $closing, [], [
                'reference' => $closing->reference,
                'closed_on' => $closedOn->toDateString(),
                'expected_amount' => $expected,
                'counted_amount' => $counted,
                'difference' => $difference,
            ], $user);

            return $closing;
        });
    }
}
