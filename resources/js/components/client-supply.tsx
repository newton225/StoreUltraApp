import { Link, router } from '@inertiajs/react';
import { show as showOrder } from '@/actions/App/Http/Controllers/OrderController';
import { update as updatePlan } from '@/actions/App/Http/Controllers/SupplyPlanController';
import ConfirmAction from '@/components/confirm-action';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { useVisitBusy } from '@/hooks/use-visit-busy';
import { StatusBadge } from '@/components/status-badge';
import SupplyPlanForm from '@/components/supply-plan-form';

export type Plan = {
    id: number;
    kit_name: string;
    kits_per_cycle: number;
    advance_amount: number;
    frequency: string;
    starts_on: string;
    ends_on: string | null;
    cycles_count: number | null;
    status: string;
};

export type ClientOrder = {
    id: number;
    reference: string | null;
    delivery_week: string;
    status: string;
    kits_count: number;
    total_sale: number;
};

type Props = {
    clientId: number;
    plans: Plan[];
    orders: ClientOrder[];
    canManageOrders: boolean;
    planDefaults: {
        kits_per_cycle: number;
        advance_amount: number;
        frequency: string;
        starts_on: string;
    } | null;
    kits: { id: number; name: string }[];
};

export default function ClientSupply({
    clientId,
    plans,
    orders,
    canManageOrders,
    planDefaults,
    kits,
}: Props) {
    const { t } = useTranslation();
    const busy = useVisitBusy();
    const { money, date } = useFormat();

    const setStatus = (plan: Plan, status: string): void => {
        router.patch(
            updatePlan.url(plan.id),
            { status },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <section className="space-y-4">
                <div className="space-y-1">
                    <h2 className="text-base font-semibold">
                        {t('orders.plans.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('orders.plans.description')}
                    </p>
                </div>

                {plans.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.plans.empty')}
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {plans.map((plan) => (
                            <li
                                key={plan.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                            >
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2 font-medium">
                                        {plan.kit_name}
                                        <StatusBadge
                                            group="orders.plans"
                                            status={plan.status}
                                        />
                                    </div>
                                    <p className="text-muted-foreground">
                                        {t(
                                            `orders.plans.frequencies.${plan.frequency}`,
                                        )}
                                        {' · '}
                                        {t('orders.plans.summary', {
                                            kits: plan.kits_per_cycle,
                                            advance: money(plan.advance_amount),
                                            start: date(plan.starts_on),
                                        })}
                                    </p>
                                </div>
                                {canManageOrders && plan.status !== 'ended' && (
                                    <div className="flex gap-2">
                                        {plan.status === 'active' ? (
                                            <Button
                                                disabled={busy}
                                                variant="warning"
                                                size="sm"
                                                onClick={() =>
                                                    setStatus(plan, 'paused')
                                                }
                                            >
                                                {t('orders.plans.pause')}
                                            </Button>
                                        ) : (
                                            <Button
                                                disabled={busy}
                                                variant="success"
                                                size="sm"
                                                onClick={() =>
                                                    setStatus(plan, 'active')
                                                }
                                            >
                                                {t('orders.plans.resume')}
                                            </Button>
                                        )}
                                        <ConfirmAction
                                            variant="ghost"
                                            size="sm"
                                            destructive
                                            label={t('orders.plans.end')}
                                            title={t(
                                                'orders.plans.end_confirm_title',
                                                { kit: plan.kit_name },
                                            )}
                                            description={t(
                                                'orders.plans.end_confirm_body',
                                            )}
                                            onConfirm={() =>
                                                setStatus(plan, 'ended')
                                            }
                                        />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {canManageOrders && planDefaults && (
                    <SupplyPlanForm
                        clientId={clientId}
                        defaults={planDefaults}
                        kits={kits}
                    />
                )}
            </section>

            <section className="space-y-3">
                <h2 className="text-base font-semibold">
                    {t('orders.plans.orders_title')}
                </h2>
                {orders.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.empty')}
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                        {orders.map((order) => (
                            <li
                                key={order.id}
                                className="flex flex-wrap items-center justify-between gap-2 px-4 py-2"
                            >
                                <Link
                                    href={showOrder(order.id)}
                                    className="font-medium underline underline-offset-4"
                                >
                                    {order.reference}
                                </Link>
                                <span className="text-muted-foreground">
                                    {date(order.delivery_week)} ·{' '}
                                    {order.kits_count} ·{' '}
                                    {money(order.total_sale)}
                                </span>
                                <StatusBadge
                                    group="orders"
                                    status={order.status}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
