<?php

namespace App\Actions\Orders;

use App\Enums\ClientStatus;
use App\Enums\SupplyPlanStatus;
use App\Exceptions\OrderPricesMissing;
use App\Models\Order;
use App\Models\SupplyPlan;
use App\Support\BusinessSettings;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class GenerateDueOrders
{
    public function __construct(
        private CreateOrder $createOrder,
        private WeekCalendar $calendar,
        private BusinessSettings $settings,
    ) {}

    /**
     * Create the orders of the cycles that are due: from the current week up to the lead time ahead.
     * Weeks already over are never created, and a week that already has an order (even cancelled) is left alone.
     *
     * @return array{created: int, incomplete: int}
     */
    public function handle(?CarbonInterface $today = null, ?SupplyPlan $only = null): array
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $horizon = $today->addDays((int) $this->settings->get('order_lead_days'));
        $currentWeek = $this->calendar->startOf($today);

        $created = 0;
        $incomplete = 0;

        $plans = SupplyPlan::query()
            ->where('status', SupplyPlanStatus::Active->value)
            ->whereHas('client', fn ($clients) => $clients->where('status', ClientStatus::Active->value))
            ->when($only !== null, fn ($query) => $query->whereKey($only->id))
            ->with(['client', 'kit'])
            ->get();

        foreach ($plans as $plan) {
            for ($cycle = 0; ; $cycle++) {
                $week = $plan->cycleWeek($cycle, $this->calendar);

                if ($week->gt($horizon)
                    || ($plan->ends_on !== null && $week->gt($plan->ends_on))
                    || ($plan->cycles_count !== null && $cycle >= $plan->cycles_count)) {
                    break;
                }

                if ($week->lt($currentWeek)
                    || Order::where('supply_plan_id', $plan->id)->whereDate('delivery_week', $week)->exists()) {
                    continue;
                }

                try {
                    $version = $plan->kit->versionForWeek($week) ?? throw OrderPricesMissing::forKit(null);

                    $this->createOrder->handle(
                        client: $plan->client,
                        version: $version,
                        kitsCount: $plan->kits_per_cycle,
                        deliveryWeek: $week,
                        advanceAmount: $plan->advance_amount,
                        plan: $plan,
                    );

                    $created++;
                } catch (OrderPricesMissing) {
                    $incomplete++;
                }
            }
        }

        return ['created' => $created, 'incomplete' => $incomplete];
    }
}
